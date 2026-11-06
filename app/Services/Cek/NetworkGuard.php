<?php

namespace App\Services\Cek;

/**
 * Penjaga keamanan sebelum server membuka sebuah alamat (pencegah SSRF).
 *
 * SSRF = penyerang menyuruh server kita membuka alamat internal (localhost, jaringan kantor,
 * metadata cloud 169.254.169.254) lewat tautan yang tampak biasa.
 * Penjaga ini menolak alamat internal, port selain 80/443, dan nama host internal.
 */
final class NetworkGuard
{
    private const BLOCKED_V4 = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ];

    private const BLOCKED_V6 = [
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '100::/64', '2001::/32',
        '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10', 'ff00::/8',
    ];

    private const BLOCKED_HOST_SUFFIXES = ['.localhost', '.local', '.internal', '.lan', '.home.arpa', '.corp', '.intranet'];

    public function __construct(private readonly HostResolver $resolver) {}

    /**
     * @return array{ok:bool,reason?:string,host?:string,port?:int,ip?:string,scheme?:string}
     *   reason: scheme | host_internal | ip_internal | dns_internal | port | dns_failed | no_ipv4
     *   (pada penolakan ip_internal/dns_internal, 'ip' berisi alamat yang ditolak)
     */
    public function inspect(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return ['ok' => false, 'reason' => 'scheme'];
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return ['ok' => false, 'reason' => 'scheme'];
        }

        // Host IPv6 ditulis dengan kurung siku, mis. http://[::1]/ . Buang kurungnya agar dikenali sebagai IP.
        $host = strtolower(trim(rtrim($parts['host'], '.'), '[]'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $allowedPorts = config('jeda_cek.redirect.allowed_ports', [80, 443]);

        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;

        if (! $isIp) {
            if ($host === 'localhost' || $this->endsWithAny($host, self::BLOCKED_HOST_SUFFIXES)) {
                return ['ok' => false, 'reason' => 'host_internal'];
            }
        } elseif (! $this->isPublicIp($host)) {
            return ['ok' => false, 'reason' => 'ip_internal', 'ip' => $host];
        }

        if (! in_array($port, $allowedPorts, true)) {
            return ['ok' => false, 'reason' => 'port'];
        }

        $ips = $isIp ? [$host] : $this->resolver->resolve($host);
        if ($ips === []) {
            return ['ok' => false, 'reason' => 'dns_failed'];
        }

        // Server hanya terhubung lewat satu IPv4 yang dikunci (CURLOPT_RESOLVE). Jawaban IPv6 dari DNS
        // (mis. alamat sintetis DNS64) tidak pernah dipakai untuk menyambung, jadi tidak ikut menentukan.
        $candidates = $isIp ? $ips : array_values(array_filter(
            $ips,
            fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
        ));

        // Satu saja IPv4 internal sudah cukup untuk menolak (cegah campuran IP publik dan internal).
        // ip_internal   = alamat internal tertulis langsung di tautan.
        // dns_internal  = nama domain publik yang oleh DNS diterjemahkan ke alamat internal
        //                 (bisa karena VPN/pemblokir DNS di jaringan server, atau memang disengaja penyerang).
        foreach ($candidates as $ip) {
            if (! $this->isPublicIp($ip)) {
                return ['ok' => false, 'reason' => $isIp ? 'ip_internal' : 'dns_internal', 'ip' => $ip];
            }
        }

        $ipv4 = null;
        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $ipv4 = $ip;
                break;
            }
        }
        if ($ipv4 === null) {
            return ['ok' => false, 'reason' => 'no_ipv4'];
        }

        return ['ok' => true, 'host' => $host, 'port' => $port, 'ip' => $ipv4, 'scheme' => $scheme];
    }

    public function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        // IPv4-mapped IPv6.
        if (
            strlen($packed) === 16 &&
            str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")
        ) {
            $ipv4 = inet_ntop(substr($packed, 12, 4));

            return $ipv4 !== false && $this->isPublicIp($ipv4);
        }

        // NAT64 standar: 64:ff9b::/96. Yang dinilai adalah IPv4 yang tertanam di dalamnya.
        $nat64Prefix = inet_pton('64:ff9b::');

        if (
            strlen($packed) === 16 &&
            $nat64Prefix !== false &&
            substr($packed, 0, 12) === substr($nat64Prefix, 0, 12)
        ) {
            $ipv4 = inet_ntop(substr($packed, 12, 4));

            return $ipv4 !== false && $this->isPublicIp($ipv4);
        }

        $ranges = strlen($packed) === 4
            ? self::BLOCKED_V4
            : self::BLOCKED_V6;

        foreach ($ranges as $cidr) {
            if ($this->inCidr($ip, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $bits = (int) $bits;
        $a = inet_pton($ip);
        $b = inet_pton($network);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $rest = $bits % 8;
        if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        if ($rest > 0) {
            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($a[$bytes]) & $mask) !== (ord($b[$bytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }

    private function endsWithAny(string $host, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
