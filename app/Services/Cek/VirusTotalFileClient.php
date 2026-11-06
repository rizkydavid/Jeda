<?php

namespace App\Services\Cek;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * VirusTotal v3 untuk FILE, hanya lewat hash SHA-256.
 *
 * File TIDAK PERNAH diunggah ke VirusTotal. Yang dikirim hanya sidik jari (hash) file, dan hasilnya hanya ada
 * jika ada orang lain yang pernah mengunggah file yang persis sama. File baru (mis. APK penipuan yang baru dibuat)
 * wajar belum dikenal, jadi hasil "unknown" BUKAN berarti aman.
 *
 * Pembatas kuota memakai kunci yang SAMA dengan VirusTotalUrlClient, karena kuota gratis (4/menit, 500/hari)
 * dipakai bersama oleh pemeriksaan tautan dan file.
 */
final class VirusTotalFileClient
{
    private const BASE = 'https://www.virustotal.com/api/v3';

    /**
     * @return array{state:'ok'|'pending'|'unavailable'|'not_configured', result:?string,
     *   malicious:int, suspicious:int, total:int, label:?string, reason:?string}
     *   result: 'flagged' | 'clean' | 'unknown' (belum pernah dianalisis)
     */
    public function lookup(string $sha256): array
    {
        if (! config('services.virustotal.key')) {
            return $this->out('not_configured');
        }
        $sha256 = strtolower($sha256);
        if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
            return $this->out('unavailable', reason: 'invalid_request');
        }

        $cacheKey = 'jeda:vt:file:'.$sha256;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->call(fn () => $this->http()->get(self::BASE.'/files/'.$sha256));
        if (is_array($response)) {
            return $response;
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
        $result = $this->fromStats(
            (array) ($attributes['last_analysis_stats'] ?? []),
            ! empty($attributes['last_analysis_date']),
            $this->label($attributes),
        );
        Cache::put($cacheKey, $result, $result['state'] === 'pending' ? 60 : 6 * 3600);

        return $result;
    }

    /** Label ancaman populer (mis. "trojan.agent"), dibersihkan karena berasal dari pihak luar. */
    private function label(array $attributes): ?string
    {
        $label = $attributes['popular_threat_classification']['suggested_threat_label'] ?? null;
        if (! is_string($label) || $label === '') {
            return null;
        }
        $label = preg_replace('/[^A-Za-z0-9 ._\/\-]/', '', $label) ?? '';

        return $label !== '' ? mb_substr($label, 0, 60) : null;
    }

    private function fromStats(array $stats, bool $analyzed, ?string $label): array
    {
        $malicious = (int) ($stats['malicious'] ?? 0);
        $suspicious = (int) ($stats['suspicious'] ?? 0);
        $total = $malicious + $suspicious + (int) ($stats['harmless'] ?? 0) + (int) ($stats['undetected'] ?? 0);

        if (! $analyzed || $total === 0) {
            return $this->out('pending');
        }

        return $this->out('ok', ($malicious + $suspicious) > 0 ? 'flagged' : 'clean', $malicious, $suspicious, $total, $label);
    }

    private function http()
    {
        return Http::timeout(5)->connectTimeout(3)->acceptJson()
            ->withHeaders(['x-apikey' => (string) config('services.virustotal.key')]);
    }

    /** Satu panggilan API dengan pembatas kuota gratis bersama. Mengembalikan Response atau hasil 'unavailable'. */
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
            Log::warning('VirusTotal (file) tidak dapat dihubungi', ['error' => $e::class]);

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
        Log::warning('VirusTotal (file) gagal', ['http_status' => $status, 'reason' => $reason]);

        return $this->out('unavailable', reason: $reason);
    }

    private function out(string $state, ?string $result = null, int $malicious = 0, int $suspicious = 0, int $total = 0, ?string $label = null, ?string $reason = null): array
    {
        return ['state' => $state, 'result' => $result, 'malicious' => $malicious, 'suspicious' => $suspicious, 'total' => $total, 'label' => $label, 'reason' => $reason];
    }
}
