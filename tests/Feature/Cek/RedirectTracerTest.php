<?php

namespace Tests\Feature\Cek;

use App\Services\Cek\NetworkGuard;
use App\Services\Cek\RedirectTracer;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

class RedirectTracerTest extends CekTestCase
{
    private function tracer(array $dns = []): RedirectTracer
    {
        return new RedirectTracer(new NetworkGuard(new FakeHostResolver($dns)));
    }

    public static function lokasi(): array
    {
        $base = 'https://a.com/x/y?z=1';

        return [
            'path mutlak' => [$base, '/abs', 'https://a.com/abs'],
            'protokol relatif' => [$base, '//b.com/p', 'https://b.com/p'],
            'relatif' => [$base, 'rel', 'https://a.com/x/rel'],
            'hanya query' => [$base, '?q=2', 'https://a.com/x/y?q=2'],
            'alamat penuh' => [$base, 'https://c.com/z', 'https://c.com/z'],
            'javascript ditolak' => [$base, 'javascript:alert(1)', null],
            'data ditolak' => [$base, 'data:text/html,x', null],
            'ftp ditolak' => [$base, 'ftp://x.com/', null],
            'kosong ditolak' => [$base, '', null],
        ];
    }

    #[DataProvider('lokasi')]
    public function test_resolve_location(string $base, string $location, ?string $expected): void
    {
        $this->assertSame($expected, $this->tracer()->resolveLocation($base, $location));
    }

    public function test_tanpa_redirect(): void
    {
        $this->fakeServices(['contoh.com/*' => $this->html('Halo')]);

        $r = $this->tracer()->trace('https://contoh.com/a');

        $this->assertSame('ok', $r['status']);
        $this->assertSame(['https://contoh.com/a'], $r['chain']);
        $this->assertSame('https://contoh.com/a', $r['final_url']);
        $this->assertStringContainsString('Halo', $r['body']);
    }

    public function test_redirect_beruntun_dibatasi_lima_lompatan(): void
    {
        Http::fake(['loop.example.com/*' => Http::response('', 302, ['Location' => '/lagi'])]);

        $r = $this->tracer()->trace('https://loop.example.com/a');

        $this->assertSame('too_many_redirects', $r['status']);
        Http::assertSentCount(6); // 1 permintaan awal + 5 lompatan, tidak lebih
    }

    public function test_lokasi_berbahaya_ditolak(): void
    {
        Http::fake(['contoh.com/*' => Http::response('', 302, ['Location' => 'javascript:alert(1)'])]);

        $this->assertSame('invalid_redirect', $this->tracer()->trace('https://contoh.com/')['status']);
    }

    public function test_isi_bukan_html_atau_terkompresi_tidak_dibaca(): void
    {
        Http::fake([
            'berkas.com/*' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
            'gzip.com/*' => Http::response('xx', 200, ['Content-Type' => 'text/html', 'Content-Encoding' => 'gzip']),
        ]);

        $this->assertNull($this->tracer()->trace('https://berkas.com/a.pdf')['body']);
        $this->assertNull($this->tracer()->trace('https://gzip.com/')['body']);
    }

    public function test_domain_yang_tidak_ditemukan_dns(): void
    {
        Http::fake();

        $r = $this->tracer(['hilang.example' => []])->trace('https://hilang.example/');

        $this->assertSame('unavailable', $r['status']);
        $this->assertSame('dns_failed', $r['reason']);
        Http::assertNothingSent();
    }

    public function test_redirect_ke_ip_internal_berhenti_sebelum_dibuka(): void
    {
        Http::fake(['luar.com/*' => Http::response('', 307, ['Location' => 'http://10.0.0.7/rahasia'])]);

        $r = $this->tracer()->trace('https://luar.com/');

        $this->assertSame('blocked', $r['status']);
        $this->assertSame('ip_internal', $r['reason']);
        $this->assertSame('10.0.0.7', $r['blocked_ip']);
        $this->assertSame(['https://luar.com/', 'http://10.0.0.7/rahasia'], $r['chain']);
        Http::assertSentCount(1);
    }
}
