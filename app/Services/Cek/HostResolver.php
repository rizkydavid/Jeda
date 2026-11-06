<?php

namespace App\Services\Cek;

/**
 * Mencari alamat IP dari nama domain. Dipisah supaya bisa diganti saat pengujian.
 */
class HostResolver
{
    /** @return array<int,string> daftar IPv4 dan IPv6 */
    public function resolve(string $host): array
    {
        $ips = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }

        if (function_exists('dns_get_record')) {
            $v6 = @dns_get_record($host, DNS_AAAA);
            foreach (is_array($v6) ? $v6 : [] as $record) {
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
