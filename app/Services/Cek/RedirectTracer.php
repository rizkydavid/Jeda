<?php

namespace App\Services\Cek;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Mengikuti pengalihan (redirect) sebuah tautan untuk mengetahui tujuan akhirnya.
 *
 * Aturan keamanan:
 *  - setiap alamat (termasuk tiap lompatan) divalidasi NetworkGuard sebelum dibuka;
 *  - koneksi dikunci ke IP yang sudah lolos validasi (CURLOPT_RESOLVE), jadi DNS tidak bisa
 *    diubah di tengah jalan (DNS rebinding);
 *  - maksimal N lompatan, batas waktu total, ukuran unduhan dibatasi;
 *  - tanpa cookie, tanpa menjalankan JavaScript, tanpa mengirim data apa pun ke situs;
 *  - verifikasi SSL TIDAK dimatikan.
 */
final class RedirectTracer
{
    private const REDIRECT_CODES = [301, 302, 303, 307, 308];

    public function __construct(private readonly NetworkGuard $guard) {}

    /**
     * @return array{
     *   status:'ok'|'blocked'|'unavailable'|'too_many_redirects'|'invalid_redirect',
     *   reason:?string, chain:array<int,string>, final_url:?string, http_status:?int,
     *   content_type:?string, body:?string, blocked_ip?:?string
     * }
     */
    public function trace(string $startUrl): array
    {
        $max = (int) config('jeda_cek.redirect.max_hops', 5);
        $deadline = microtime(true) + (float) config('jeda_cek.redirect.total_seconds', 10);
        $chain = [$startUrl];
        $current = $startUrl;

        if (! extension_loaded('curl')) {
            // Tanpa cURL koneksi tidak bisa dikunci ke IP tervalidasi, jadi lebih aman tidak membuka apa pun.
            return $this->result('unavailable', $chain, 'curl_missing');
        }

        for ($hop = 0; $hop <= $max; $hop++) {
            $target = $this->guard->inspect($current);
            if (! $target['ok']) {
                return $this->result(in_array($target['reason'], ['dns_failed', 'no_ipv4'], true) ? 'unavailable' : 'blocked', $chain, $target['reason'], blockedIp: $target['ip'] ?? null);
            }

            $remaining = $deadline - microtime(true);
            if ($remaining < 0.5) {
                return $this->result('unavailable', $chain, 'timeout');
            }

            try {
                $response = $this->request($current, $target, $remaining);
            } catch (Throwable $e) {
                // cURL error 42 = unduhan sengaja dihentikan karena terlalu besar.
                $reason = str_contains($e->getMessage(), 'error 42') ? 'too_large' : 'unreachable';

                return $this->result($reason === 'too_large' ? 'ok' : 'unavailable', $chain, $reason, finalUrl: $current);
            }

            $status = $response->status();
            $location = $response->header('Location');
            if (in_array($status, self::REDIRECT_CODES, true) && $location !== '') {
                if ($hop === $max) {
                    return $this->result('too_many_redirects', $chain, 'too_many_redirects', httpStatus: $status);
                }
                $next = $this->resolveLocation($current, $location);
                if ($next === null) {
                    return $this->result('invalid_redirect', $chain, 'invalid_redirect', httpStatus: $status);
                }
                $chain[] = $next;
                $current = $next;

                continue;
            }

            return $this->finish($chain, $current, $response);
        }

        return $this->result('too_many_redirects', $chain, 'too_many_redirects');
    }

    private function request(string $url, array $target, float $remaining): Response
    {
        $abortAfter = (int) config('jeda_cek.redirect.abort_after_bytes', 1572864);
        $timeout = max(1, min((float) config('jeda_cek.redirect.hop_timeout', 5), $remaining));

        return Http::withOptions([
            'allow_redirects' => false,
            'decode_content' => false, // minta isi tanpa kompresi; hindari "gzip bomb"
            'curl' => [
                // Kunci koneksi ke IP yang sudah divalidasi.
                CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_PROXY => '', // abaikan proxy dari environment
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => fn ($handle, $downTotal, $downNow) => $downNow > $abortAfter ? 1 : 0,
            ],
        ])->withHeaders([
            'User-Agent' => 'JedaLinkChecker/1.0 (edukasi keamanan digital; tidak mengirim data)',
            'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5',
            'Accept-Encoding' => 'identity',
            'Range' => 'bytes=0-'.((int) config('jeda_cek.redirect.max_body_bytes', 262144) - 1),
        ])->connectTimeout(min(4, $timeout))->timeout($timeout)->get($this->fetchUrl($url));
    }

    private function finish(array $chain, string $finalUrl, Response $response): array
    {
        $contentType = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));
        $encoding = strtolower($response->header('Content-Encoding'));
        $isHtml = in_array($contentType, ['text/html', 'application/xhtml+xml'], true);

        $body = null;
        if ($isHtml && ($encoding === '' || $encoding === 'identity')) {
            $body = substr($response->body(), 0, (int) config('jeda_cek.redirect.max_body_bytes', 262144));
        }

        return [
            'status' => 'ok',
            'reason' => null,
            'chain' => $chain,
            'final_url' => $finalUrl,
            'http_status' => $response->status(),
            'content_type' => $contentType ?: null,
            'body' => $body,
        ];
    }

    private function result(string $status, array $chain, ?string $reason, ?int $httpStatus = null, ?string $finalUrl = null, ?string $blockedIp = null): array
    {
        return [
            'status' => $status,
            'reason' => $reason,
            'chain' => $chain,
            'final_url' => $finalUrl ?? ($status === 'ok' ? end($chain) : null),
            'http_status' => $httpStatus,
            'content_type' => null,
            'body' => null,
            'blocked_ip' => $blockedIp,
        ];
    }

    /** Alamat yang dibuka: tanpa fragmen (#) dan tanpa user:pass@. */
    private function fetchUrl(string $url): string
    {
        $p = parse_url($url);

        return ($p['scheme'] ?? 'https').'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '')
            .($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '');
    }

    /** Mengubah header Location (bisa relatif) menjadi alamat penuh. Hanya http(s) yang diterima. */
    public function resolveLocation(string $base, string $location): ?string
    {
        $location = trim($location);
        if ($location === '' || strlen($location) > 2048) {
            return null;
        }

        if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $location) === 1) {
            return preg_match('~^https?://~i', $location) === 1 ? $location : null; // tolak javascript:, data:, ftp:
        }

        $b = parse_url($base);
        if (! is_array($b) || empty($b['host']) || empty($b['scheme'])) {
            return null;
        }
        if (str_starts_with($location, '//')) {
            return $b['scheme'].':'.$location;
        }

        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        if (str_starts_with($location, '?')) {
            return $origin.($b['path'] ?? '/').$location;
        }

        return $origin.rtrim(dirname($b['path'] ?? '/'), '/').'/'.$location;
    }
}
