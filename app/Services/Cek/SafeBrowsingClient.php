<?php

namespace App\Services\Cek;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google Safe Browsing (Lookup API v4). Gratis untuk non-komersial, butuh API key.
 *
 * PENTING: respons kosong berarti "tidak ada di daftar Google", BUKAN "aman".
 * Situs phishing yang baru dibuat sering belum masuk daftar.
 * Hanya alamat tanpa parameter (?...) yang dikirim ke Google.
 */
final class SafeBrowsingClient
{
    private const THREAT_LABELS = [
        'MALWARE' => 'Malware',
        'SOCIAL_ENGINEERING' => 'Phishing / penipuan',
        'UNWANTED_SOFTWARE' => 'Perangkat lunak tidak diinginkan',
    ];

    /**
     * @param  array<int,string>  $urls  maksimal beberapa alamat (awal dan tujuan akhir)
     * @return array{state:'ok'|'unavailable'|'not_configured', result:?string, threats:array<int,string>, reason:?string}
     *   result: 'flagged' | 'clean'.  reason: invalid_request | access_denied | rate_limited | server_error | unreachable
     */
    public function check(array $urls): array
    {
        $key = config('services.safe_browsing.key');
        if (! $key) {
            return $this->out('not_configured');
        }

        $urls = array_values(array_unique(array_slice($urls, 0, 5)));
        $cacheKey = 'jeda:sb:'.sha1(implode('|', $urls));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(4)
                ->connectTimeout(3)
                ->acceptJson()
                ->post('https://safebrowsing.googleapis.com/v4/threatMatches:find?key='.urlencode($key), [
                    'client' => ['clientId' => 'jeda', 'clientVersion' => '1.0'],
                    'threatInfo' => [
                        'threatTypes' => array_keys(self::THREAT_LABELS),
                        'platformTypes' => ['ANY_PLATFORM'],
                        'threatEntryTypes' => ['URL'],
                        'threatEntries' => array_map(fn ($u) => ['url' => $u], $urls),
                    ],
                ]);
        } catch (ConnectionException $e) {
            // Pesan error sengaja tidak dicatat: bisa memuat URL beserta API key.
            Log::warning('Safe Browsing tidak dapat dihubungi', ['error' => $e::class]);

            return $this->out('unavailable', reason: 'unreachable');
        } catch (Throwable $e) {
            Log::warning('Safe Browsing error', ['error' => $e::class]);

            return $this->out('unavailable', reason: 'unreachable');
        }

        if (! $response->successful()) {
            $status = $response->status();
            $reason = match (true) {
                $status === 400 => 'invalid_request',   // sering berarti API key tidak valid
                in_array($status, [401, 403], true) => 'access_denied', // API belum diaktifkan atau key dibatasi
                $status === 429 => 'rate_limited',
                default => 'server_error',
            };
            Log::warning('Safe Browsing gagal', ['http_status' => $status, 'reason' => $reason]);

            return $this->out('unavailable', reason: $reason);
        }

        $threats = [];
        foreach ((array) $response->json('matches', []) as $match) {
            $threats[] = self::THREAT_LABELS[$match['threatType'] ?? ''] ?? 'Ancaman lain';
        }
        $threats = array_values(array_unique($threats));

        $result = $this->out('ok', $threats ? 'flagged' : 'clean', $threats);
        Cache::put($cacheKey, $result, 30 * 60);

        return $result;
    }

    private function out(string $state, ?string $result = null, array $threats = [], ?string $reason = null): array
    {
        return ['state' => $state, 'result' => $result, 'threats' => $threats, 'reason' => $reason];
    }
}
