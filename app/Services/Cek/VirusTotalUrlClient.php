<?php

namespace App\Services\Cek;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * VirusTotal v3 untuk URL.
 *
 *  - lookup(): mencari hasil analisis lewat SHA-256 dari URL. Yang dikirim hanya hash, bukan URL.
 *  - submit(): mengirim URL untuk dianalisis. HANYA dipanggil jika pengguna menyetujui,
 *    karena URL yang dikirim bisa terlihat oleh komunitas keamanan VirusTotal.
 *
 * API gratis dibatasi (4/menit, 500/hari), jadi ada cache dan pembatas permintaan sendiri.
 * Satu mesin yang menandai berbahaya sering salah (false positive), jadi baru dianggap
 * terkonfirmasi jika beberapa mesin sepakat (lihat LinkAnalysisService).
 */
final class VirusTotalUrlClient
{
    private const BASE = 'https://www.virustotal.com/api/v3';

    /**
     * @return array{state:'ok'|'pending'|'unavailable'|'not_configured', result:?string,
     *   malicious:int, suspicious:int, total:int, reason:?string}
     *   result: 'flagged' | 'clean' | 'unknown' (belum pernah dianalisis)
     */
    public function lookup(string $url): array
    {
        if (! config('services.virustotal.key')) {
            return $this->out('not_configured');
        }

        $id = $this->urlId($url);
        $cacheKey = 'jeda:vt:url:'.$id;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->call(fn () => $this->http()->get(self::BASE.'/urls/'.$id));
        if (is_array($response)) {
            return $response; // gagal: sudah berupa hasil 'unavailable'
        }

        if ($response->status() === 404) {
            $result = $this->out('ok', 'unknown');
            Cache::put($cacheKey, $result, 30 * 60);

            return $result;
        }

        $failure = $this->failure($response);
        if ($failure !== null) {
            return $failure;
        }

        $attributes = (array) $response->json('data.attributes', []);
        $result = $this->fromStats((array) ($attributes['last_analysis_stats'] ?? []), ! empty($attributes['last_analysis_date']));
        // Hasil yang masih berjalan jangan disimpan lama.
        Cache::put($cacheKey, $result, $result['state'] === 'pending' ? 60 : 6 * 3600);

        return $result;
    }

    /** Mengirim URL untuk dianalisis, lalu menengok status satu kali (tanpa menunggu/loop). */
    public function submit(string $url): array
    {
        if (! config('services.virustotal.key')) {
            return $this->out('not_configured');
        }

        $response = $this->call(fn () => $this->http()->asForm()->post(self::BASE.'/urls', ['url' => $this->normalize($url)]));
        if (is_array($response)) {
            return $response;
        }
        $failure = $this->failure($response);
        if ($failure !== null) {
            return $failure;
        }

        $analysisId = (string) $response->json('data.id', '');
        if ($analysisId === '' || preg_match('/^[A-Za-z0-9_\-]+$/', $analysisId) !== 1) {
            return $this->out('unavailable', reason: 'server_error');
        }

        $analysis = $this->call(fn () => $this->http()->get(self::BASE.'/analyses/'.$analysisId));
        if (is_array($analysis) || $this->failure($analysis) !== null) {
            return $this->out('pending'); // terkirim, hasil belum bisa dibaca
        }

        if ($analysis->json('data.attributes.status') === 'completed') {
            $stats = (array) $analysis->json('data.attributes.stats', []);
            $result = $this->fromStats($stats, true);
            Cache::put('jeda:vt:url:'.$this->urlId($url), $result, 6 * 3600);

            return $result;
        }

        return $this->out('pending'); // queued / in-progress
    }

    /** ID URL di VirusTotal = SHA-256 dari URL yang sudah dinormalkan. */
    public function urlId(string $url): string
    {
        return hash('sha256', $this->normalize($url));
    }

    /** Huruf kecil pada host, tanpa fragmen, port bawaan dibuang, path minimal "/". */
    public function normalize(string $url): string
    {
        $p = parse_url($url) ?: [];
        $scheme = strtolower($p['scheme'] ?? 'https');
        $host = strtolower($p['host'] ?? '');
        $port = $p['port'] ?? null;
        if ($port === null || ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        return $scheme.'://'.$host.($port ? ':'.$port : '').($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '');
    }

    private function fromStats(array $stats, bool $analyzed): array
    {
        $malicious = (int) ($stats['malicious'] ?? 0);
        $suspicious = (int) ($stats['suspicious'] ?? 0);
        $total = $malicious + $suspicious + (int) ($stats['harmless'] ?? 0) + (int) ($stats['undetected'] ?? 0);

        // Objek ada tetapi belum ada hasil: analisis masih berjalan.
        if (! $analyzed || $total === 0) {
            return $this->out('pending');
        }

        return $this->out('ok', ($malicious + $suspicious) > 0 ? 'flagged' : 'clean', $malicious, $suspicious, $total);
    }

    private function http()
    {
        return Http::timeout(5)->connectTimeout(3)->acceptJson()
            ->withHeaders(['x-apikey' => (string) config('services.virustotal.key')]);
    }

    /** Menjalankan satu panggilan API dengan pembatas kuota gratis. Mengembalikan Response atau hasil 'unavailable'. */
    private function call(callable $request): Response|array
    {
        $perMinute = (int) config('jeda_cek.virustotal_limits.per_minute', 4);
        $perDay = (int) config('jeda_cek.virustotal_limits.per_day', 450);
        if (RateLimiter::tooManyAttempts('jeda:vt:minute', $perMinute) || RateLimiter::tooManyAttempts('jeda:vt:day', $perDay)) {
            return $this->out('unavailable', reason: 'rate_limited');
        }
        RateLimiter::hit('jeda:vt:minute', 60);
        RateLimiter::hit('jeda:vt:day', 86400);

        try {
            return $request();
        } catch (Throwable $e) {
            Log::warning('VirusTotal tidak dapat dihubungi', ['error' => $e::class]);

            return $this->out('unavailable', reason: 'unreachable');
        }
    }

    private function failure(Response $response): ?array
    {
        if ($response->successful()) {
            return null;
        }
        $status = $response->status();
        $reason = match (true) {
            $status === 429 => 'rate_limited',
            in_array($status, [401, 403], true) => 'access_denied',
            default => 'server_error',
        };
        Log::warning('VirusTotal gagal', ['http_status' => $status, 'reason' => $reason]);

        return $this->out('unavailable', reason: $reason);
    }

    private function out(string $state, ?string $result = null, int $malicious = 0, int $suspicious = 0, int $total = 0, ?string $reason = null): array
    {
        return ['state' => $state, 'result' => $result, 'malicious' => $malicious, 'suspicious' => $suspicious, 'total' => $total, 'reason' => $reason];
    }
}
