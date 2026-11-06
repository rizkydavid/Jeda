<?php

namespace Tests\Feature\Cek;

use App\Services\Cek\HostResolver;
use App\Services\Cek\NetworkGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NetworkGuardTest extends TestCase
{
    public static function ipPublik(): array
    {
        return array_map(fn ($ip) => [$ip], [
            '8.8.8.8', '1.1.1.1', '93.184.216.34', '64:ff9b::808:808', '64:ff9b::5db8:d822', '172.15.255.255', '172.32.0.1', '100.63.255.255',
            '100.128.0.1', '223.255.255.255', '2606:4700:4700::1111', '::ffff:8.8.8.8',
        ]);
    }

    public static function ipInternal(): array
    {
        return array_map(fn ($ip) => [$ip], [
            '127.0.0.1', '127.255.255.254', '10.0.0.1', '10.255.255.255', '172.16.0.0', '172.31.255.255',
            '192.168.1.1', '169.254.169.254', '0.0.0.0', '100.64.0.1', '100.127.255.255', '192.0.2.5',
            '198.18.0.1', '224.0.0.1', '240.0.0.1', '255.255.255.255',
            '::1', '::', 'fe80::1', 'fc00::1', 'fd12:3456::1', 'ff02::1', '::ffff:10.0.0.1',
            '::ffff:127.0.0.1', '64:ff9b::7f00:1', '64:ff9b::a00:1', '64:ff9b::c0a8:101', '2002::1', '2001:db8::1',
        ]);
    }

    #[DataProvider('ipPublik')]
    public function test_ip_publik_diizinkan(string $ip): void
    {
        $this->assertTrue((new NetworkGuard(new HostResolver))->isPublicIp($ip), $ip);
    }

    #[DataProvider('ipInternal')]
    public function test_ip_internal_ditolak(string $ip): void
    {
        $this->assertFalse((new NetworkGuard(new HostResolver))->isPublicIp($ip), $ip);
    }

    public function test_bukan_ip_ditolak(): void
    {
        $this->assertFalse((new NetworkGuard(new HostResolver))->isPublicIp('bukan-ip'));
    }

    private function guard(array $map = [], array $default = ['93.184.216.34']): NetworkGuard
    {
        return new NetworkGuard(new FakeHostResolver($map, $default));
    }

    public function test_alasan_penolakan(): void
    {
        $this->assertSame('scheme', $this->guard()->inspect('ftp://contoh.com/')['reason']);
        $this->assertSame('scheme', $this->guard()->inspect('javascript:alert(1)')['reason']);
        $this->assertSame('host_internal', $this->guard()->inspect('http://localhost/')['reason']);
        $this->assertSame('host_internal', $this->guard()->inspect('http://localhost./')['reason']);
        $this->assertSame('host_internal', $this->guard()->inspect('http://printer.local/')['reason']);
        $this->assertSame('ip_internal', $this->guard()->inspect('http://[::1]/')['reason']);
        $this->assertSame('ip_internal', $this->guard()->inspect('http://[fe80::1]/')['reason']);
        $this->assertSame('ip_internal', $this->guard()->inspect('http://127.0.0.1/')['reason']);
        $this->assertSame('port', $this->guard()->inspect('https://contoh.com:8080/')['reason']);
        $this->assertSame('dns_failed', $this->guard(['x.com' => []])->inspect('https://x.com/')['reason']);
    }

    public function test_campuran_ip_publik_dan_internal_ditolak(): void
    {
        $r = $this->guard(['licik.com' => ['8.8.8.8', '10.0.0.1']])->inspect('https://licik.com/');

        $this->assertFalse($r['ok']);
        $this->assertSame('dns_internal', $r['reason']); // nama domain, bukan alamat internal tertulis di tautan
        $this->assertSame('10.0.0.1', $r['ip']);
    }

    public function test_dns_yang_menjawab_alamat_internal_dibedakan_dari_alamat_internal_di_tautan(): void
    {
        foreach (['0.0.0.0', '127.0.0.1', '198.18.0.15'] as $ip) { // pola sinkhole DNS dan mode fake-IP VPN
            $r = $this->guard(['situs.contoh.com' => [$ip]])->inspect('https://situs.contoh.com/');
            $this->assertSame('dns_internal', $r['reason'], $ip);
            $this->assertSame($ip, $r['ip']);
        }
        $this->assertSame('ip_internal', $this->guard()->inspect('http://10.1.2.3/')['reason']);
    }

    public function test_jawaban_ipv6_sintetis_dns64_tidak_menolak_domain_yang_punya_ipv4_publik(): void
    {
        $r = $this->guard(['dns64.contoh.com' => ['64:ff9b::5db8:d822', '93.184.216.34']])->inspect('https://dns64.contoh.com/');
        $this->assertTrue($r['ok']);
        $this->assertSame('93.184.216.34', $r['ip']);

        // Alamat IPv6 yang tidak publik tidak dipakai menyambung, jadi tidak menentukan.
        $r = $this->guard(['v6.contoh.com' => ['fe80::1', '8.8.8.8']])->inspect('https://v6.contoh.com/');
        $this->assertTrue($r['ok']);
        $this->assertSame('8.8.8.8', $r['ip']);
    }

    public function test_hanya_ipv6_tidak_dibuka(): void
    {
        $this->assertSame('no_ipv4', $this->guard(['v6.com' => ['2606:4700:4700::1111']])->inspect('https://v6.com/')['reason']);
    }

    public function test_alamat_sah_diterima_dengan_ip_terkunci(): void
    {
        $r = $this->guard()->inspect('https://contoh.com/a');
        $this->assertTrue($r['ok']);
        $this->assertSame('93.184.216.34', $r['ip']);
        $this->assertSame(443, $r['port']);

        $ip = $this->guard()->inspect('http://8.8.8.8/');
        $this->assertTrue($ip['ok']);
        $this->assertSame(80, $ip['port']);
    }
}
