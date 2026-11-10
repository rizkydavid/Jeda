<?php

namespace Tests\Feature\Cek;

use App\Services\Cek\UrlHeuristics;
use Tests\TestCase;

class UrlHeuristicsTest extends TestCase
{
    private function ids(string $url): array
    {
        $parsed = (new UrlHeuristics)->parse($url);
        $this->assertNotNull($parsed, "parse($url) seharusnya valid");

        return array_map(fn ($s) => $s->id, (new UrlHeuristics)->analyze($parsed)['signals']);
    }

    public function test_input_tidak_valid(): void
    {
        $h = new UrlHeuristics;
        foreach (['', '   ', 'javascript:alert(1)', 'ftp://x.com', 'localhost', 'a b.com', 'https://', '999.999.999.999', 'tanpa-titik'] as $bad) {
            $this->assertNull($h->parse($bad), "parse('$bad') seharusnya null");
        }
    }

    public function test_parse_menyusun_alamat_untuk_layanan_eksternal(): void
    {
        $p = (new UrlHeuristics)->parse('https://user:pw@Contoh.COM:8443/a/b?x=1#frag');

        $this->assertSame('contoh.com', $p['host']);
        $this->assertTrue($p['has_userinfo']);
        $this->assertSame(8443, $p['port']);
        $this->assertSame('https://contoh.com:8443/a/b', $p['safe_url']);        // tanpa query
        $this->assertSame('https://contoh.com:8443/a/b?x=1', $p['fetch_url']);   // tanpa fragmen dan user:pass
    }

    public function test_domain_utama(): void
    {
        $h = new UrlHeuristics;
        $this->assertSame('bca.co.id', $h->registrableOf('www.bca.co.id'));
        $this->assertSame('example.com', $h->registrableOf('a.b.example.com'));
        $this->assertSame('next-go.site', $h->registrableOf('links.next-go.site'));
    }

    public function test_domain_resmi_tidak_dicurigai(): void
    {
        $this->assertSame(['official_domain'], $this->ids('https://www.bca.co.id/id'));
        $this->assertSame(['official_domain'], $this->ids('https://www.googleapis.com/x'));
        $this->assertSame(['official_domain'], $this->ids('https://www.youtube.com/watch?v=1'));
    }

    public function test_layanan_berbagi_konten_resmi_tidak_dianggap_aman(): void
    {
        // Domainnya milik merek resmi, tetapi isinya bisa dibuat siapa saja.
        $this->assertContains('shared_platform', $this->ids('https://docs.google.com/forms/d/e/abc/viewform'));
        $this->assertNotContains('official_domain', $this->ids('https://docs.google.com/forms/d/e/abc/viewform'));
        $this->assertContains('shared_platform', $this->ids('https://storage.googleapis.com/bucket/file.html'));
        $this->assertContains('shared_platform', $this->ids('https://wa.me/628123456789'));
        $this->assertContains('shared_platform', $this->ids('https://fb.me/abc'));

        // Nama merek lain di path halaman layanan berbagi.
        $ids = $this->ids('https://sites.google.com/view/klikbca-resmi');
        $this->assertContains('shared_platform', $ids);
        $this->assertContains('brand_in_path', $ids);

        // Nama pemilik layanan sendiri tidak dihitung, begitu pula kode pendek satu segmen.
        $this->assertNotContains('brand_in_path', $this->ids('https://sites.google.com/view/google-tugas'));
        $this->assertNotContains('brand_in_path', $this->ids('https://youtu.be/dQw4w9WgXcQ'));

        // Domain resmi biasa tidak berubah.
        $this->assertSame(['official_domain'], $this->ids('https://www.youtube.com/watch?v=1'));
        $this->assertSame(['official_domain'], $this->ids('https://www.googleapis.com/x'));
    }

    public function test_hosting_gratis_dihitung_per_subdomain(): void
    {
        $h = new UrlHeuristics;
        $this->assertSame('bca-login.pages.dev', $h->registrableOf('bca-login.pages.dev'));
        $this->assertSame('toko.github.io', $h->registrableOf('www.toko.github.io'));
        $this->assertSame('pages.dev', $h->registrableOf('pages.dev'));

        $this->assertTrue($h->isFreeHosting('toko-baru.pages.dev'));
        $this->assertFalse($h->isFreeHosting('pages.dev'));
        $this->assertFalse($h->isFreeHosting('toko-baru.com'));

        $this->assertContains('free_hosting', $this->ids('https://toko-baru.vercel.app'));
        $this->assertNotContains('free_hosting', $this->ids('https://toko-baru.com'));
        $this->assertContains('brand_lookalike', $this->ids('https://bca-login.pages.dev'));
    }

    public function test_tiruan_merek(): void
    {
        $this->assertContains('brand_lookalike', $this->ids('https://bca-login.xyz'));
        $this->assertContains('brand_lookalike', $this->ids('https://tokopedla.com'));      // salah ketik
        $this->assertContains('brand_lookalike', $this->ids('https://g0pay-promo.com'));    // angka pengganti huruf
        $this->assertContains('brand_lookalike', $this->ids('https://bca.co.id.evil.com')); // merek di subdomain
        $this->assertNotContains('brand_lookalike', $this->ids('https://toko-baru-sekali.com'));
    }

    public function test_tanda_at_dan_ip(): void
    {
        $this->assertContains('userinfo_at', $this->ids('https://bca.co.id@evil.com/'));
        $this->assertContains('ip_host', $this->ids('http://8.8.8.8/login'));
        $this->assertContains('http_only', $this->ids('http://contoh.com'));
    }

    public function test_punycode_homograf(): void
    {
        if (! function_exists('idn_to_ascii')) {
            $this->markTestSkipped('Ekstensi intl tidak aktif.');
        }
        $this->assertContains('punycode', $this->ids('https://bcа.co.id')); // "а" huruf Kiril
    }

    public function test_kata_pancingan_adalah_tanda_lemah(): void
    {
        $parsed = (new UrlHeuristics)->parse('https://login-toko.com/verifikasi');
        $signals = (new UrlHeuristics)->analyze($parsed)['signals'];

        $weak = array_filter($signals, fn ($s) => $s->group === 'keyword');
        $this->assertNotEmpty($weak);
        foreach ($weak as $s) {
            $this->assertTrue($s->needsOther, "{$s->id} harus tanda lemah");
        }
    }

    public function test_parameter_mencurigakan(): void
    {
        $this->assertContains('query_embedded_url', $this->ids('https://contoh.com/r?u=https://evil.com'));
        $this->assertContains('query_credentials', $this->ids('https://contoh.com/f?otp=123456'));
    }

    public function test_pola_tautan_pengalih(): void
    {
        $this->assertContains('redirector_pattern', $this->ids('https://links.next-go.site/rjB80lK'));
        $this->assertNotContains('redirector_pattern', $this->ids('https://www.contoh.com/halaman-panjang-sekali'));
    }

    public function test_nama_merek_di_teks_halaman(): void
    {
        $h = new UrlHeuristics;
        $this->assertSame('BCA', $h->brandInText('KlikBCA Individual - Login')['brand']);
        $this->assertNull($h->brandInText('Resep nasi goreng enak'));
    }
}
