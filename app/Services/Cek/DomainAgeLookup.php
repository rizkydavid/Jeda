<?php

namespace App\Services\Cek;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Informasi domain lewat RDAP (gratis, tanpa API key). Yang dikirim hanya nama domain.
 * Cakupan data berbeda-beda per akhiran domain, jadi hasil "unknown" itu normal.
 * Domain baru hanyalah SATU indikator risiko, bukan bukti berbahaya.
 */
final class DomainAgeLookup
{
    /**
     * @return array{state:'ok'|'unavailable', result:?string, days:?int, registered_at:?string, registrar:?string, reason:?string}
     *   result: 'known' | 'unknown'
     */
    public function lookup(string $registrableDomain): array
    {
        $cacheKey = 'jeda:rdap:'.$registrableDomain;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(4)
                ->connectTimeout(3)
                ->withHeaders(['Accept' => 'application/rdap+json, application/json'])
                ->get('https://rdap.org/domain/'.rawurlencode($registrableDomain));
        } catch (Throwable $e) {
            Log::warning('RDAP tidak dapat dihubungi', ['error' => $e::class]);

            return $this->out('unavailable', reason: 'unreachable');
        }

        if ($response->status() === 404) {
            return $this->remember($cacheKey, $this->out('ok', 'unknown'), 3600);
        }
        if (! $response->successful()) {
            Log::warning('RDAP gagal', ['http_status' => $response->status()]);

            return $this->out('unavailable', reason: $response->status() === 429 ? 'rate_limited' : 'server_error');
        }

        $json = (array) $response->json();
        $registrar = $this->registrar($json);

        foreach ((array) ($json['events'] ?? []) as $event) {
            if (($event['eventAction'] ?? null) === 'registration' && ! empty($event['eventDate'])) {
                try {
                    $registered = Carbon::parse($event['eventDate']);
                } catch (Throwable) {
                    continue;
                }
                $days = max(0, (int) floor($registered->diffInDays(now(), false)));

                return $this->remember($cacheKey, $this->out('ok', 'known', $days, $registered->toDateString(), $registrar), 86400);
            }
        }

        return $this->remember($cacheKey, $this->out('ok', 'unknown', registrar: $registrar), 6 * 3600);
    }

    private function registrar(array $json): ?string
    {
        foreach ((array) ($json['entities'] ?? []) as $entity) {
            if (! in_array('registrar', (array) ($entity['roles'] ?? []), true)) {
                continue;
            }
            foreach ((array) ($entity['vcardArray'][1] ?? []) as $field) {
                if (($field[0] ?? null) === 'fn' && ! empty($field[3])) {
                    return Str::limit(strip_tags((string) $field[3]), 80, '…');
                }
            }
        }

        return null;
    }

    private function remember(string $key, array $value, int $seconds): array
    {
        Cache::put($key, $value, $seconds);

        return $value;
    }

    private function out(string $state, ?string $result = null, ?int $days = null, ?string $registeredAt = null, ?string $registrar = null, ?string $reason = null): array
    {
        return ['state' => $state, 'result' => $result, 'days' => $days, 'registered_at' => $registeredAt, 'registrar' => $registrar, 'reason' => $reason];
    }
}
