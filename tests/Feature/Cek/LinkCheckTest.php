<?php

namespace Tests\Feature\Cek;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class LinkCheckTest extends CekTestCase
{
    // ---------------------------------------------------------------- input

    public function test_input_kosong_dan_tidak_valid_ditolak(): void
    {
        $this->fakeServices();

        $this->check('')->assertStatus(422);
        foreach (['javascript:alert(1)', 'ftp://contoh.com', 'localhost', 'a b.com'] as $bad) {
            $this->check($bad)->assertStatus(422)->assertJsonPath('ok', false);
        }
        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------- skenario utama

    public function test_youtube_resmi_tidak_dibuka_dan_tidak_ada_indikator(): void
    {
        $this->fakeServices();

        $response = $this->check('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->assertOk()
            ->assertJsonPath('score', 0)
            ->assertJsonPath('level', 'Belum ada indikator')
            ->assertJsonPath('official_brand', 'YouTube')
            ->assertJsonPath('coverage.status', 'lengkap')
            ->assertJsonPath('checks.redirect.state', 'skipped');

        // Skor 0 tidak boleh dinyatakan sebagai jaminan aman.
        $this->assertStringContainsString('bukan jaminan', $response->json('message'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'youtube.com'));
    }

    public function test_formulir_google_tiruan_bank_tetap_dibuka_dan_dibaca(): void
    {
        // Data SIMULASI. Domain google.com resmi, tetapi formulirnya dibuat orang lain.
        $form = '<form action="/kirim" method="post"><input type="text" name="user"><input type="password" name="pass"></form>';
        $this->fakeServices(['docs.google.com/*' => $this->html('KlikBCA Individual - Login', $form)]);

        $response = $this->check('https://docs.google.com/forms/d/e/abc123/viewform')
            ->assertOk()
            ->assertJsonPath('official_brand', 'Google')
            ->assertJsonPath('official_shared', true)
            ->assertJsonPath('checks.redirect.state', 'ok')   // TIDAK dilewati
            ->assertJsonPath('checks.page.state', 'ok');

        $ids = $this->signalIds($response);
        foreach (['shared_platform', 'password_form', 'brand_in_page_mismatch'] as $expected) {
            $this->assertContains($expected, $ids, "Sinyal $expected seharusnya ada");
        }
        $this->assertNotSame('Belum ada indikator', $response->json('level'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'docs.google.com'));
    }

    public function test_pengalih_resmi_ke_domain_merek_yang_sama_tidak_dianggap_domain_lain(): void
    {
        $this->fakeServices([
            'youtu.be/*' => Http::response('', 302, ['Location' => 'https://www.youtube.com/watch?v=abc']),
            'www.youtube.com/*' => $this->html('Video'),
        ]);

        $response = $this->check('https://youtu.be/abc')
            ->assertOk()
            ->assertJsonPath('official_shared', true)
            ->assertJsonPath('final_hostname', 'www.youtube.com')
            ->assertJsonPath('checks.redirect.state', 'ok')
            ->assertJsonPath('checks.page.state', 'skipped'); // tujuan akhir domain resmi biasa

        $ids = $this->signalIds($response);
        $this->assertNotContains('redirect_cross_domain', $ids);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'youtu.be'));
    }

    public function test_situs_hosting_gratis_diberi_tanda_dan_umur_domain_penyedia_tidak_dipakai(): void
    {
        $this->fakeServices(['bca-promo.pages.dev/*' => $this->html('Promo')]);

        $response = $this->check('https://bca-promo.pages.dev/')
            ->assertOk()
            ->assertJsonPath('checks.domain_age.state', 'skipped');

        $ids = $this->signalIds($response);
        $this->assertContains('free_hosting', $ids);
        $this->assertContains('brand_lookalike', $ids);
        // Umur "pages.dev" milik penyedia tidak boleh dijadikan alasan bahwa halamannya tepercaya.
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'rdap.org'));
    }

    public function test_phishing_yang_belum_dikenal_google_tetap_terdeteksi_lewat_kombinasi_tanda(): void
    {
        // Data di bawah SIMULASI. Tidak ada situs sungguhan yang dibuka.
        $form = '<form action="/kirim" method="post"><input type="text" name="user"><input type="password" name="pass"></form>';
        $this->fakeServices([
            'links.next-go.site/*' => Http::response('', 302, ['Location' => 'https://masuk-klikbca.xyz/verifikasi']),
            'masuk-klikbca.xyz/*' => $this->html('KlikBCA Individual - Login', $form),
            'rdap.org/*' => Http::response($this->rdap(12)),
        ]);

        $response = $this->check('https://links.next-go.site/rjB80lK')
            ->assertOk()
            ->assertJsonPath('level', 'Tinggi')
            ->assertJsonPath('final_hostname', 'masuk-klikbca.xyz')
            ->assertJsonPath('redirect_hosts', ['links.next-go.site', 'masuk-klikbca.xyz'])
            // Google tidak menemukan apa-apa, tetapi hasilnya TIDAK dianggap aman.
            ->assertJsonPath('checks.safe_browsing.result', 'clean')
            ->assertJsonPath('coverage.status', 'lengkap');

        $ids = $this->signalIds($response);
        foreach (['brand_lookalike', 'brand_in_page_mismatch', 'password_form', 'redirect_cross_domain', 'domain_new', 'redirector_pattern'] as $expected) {
            $this->assertContains($expected, $ids, "Sinyal $expected seharusnya ada");
        }
        $this->assertNotContains('safe_browsing', $ids);
    }

    public function test_url_uji_malware_resmi_google_terkonfirmasi(): void
    {
        $this->fakeServices([
            'safebrowsing.googleapis.com/*' => Http::response(['matches' => [['threatType' => 'MALWARE']]], 200),
            'testsafebrowsing.appspot.com/*' => $this->html('Uji'),
        ]);

        $this->check('https://testsafebrowsing.appspot.com/s/malware.html')
            ->assertOk()
            ->assertJsonPath('score', 10)
            ->assertJsonPath('level', 'Tinggi')
            ->assertJsonPath('confirmed', true)
            ->assertJsonPath('checks.safe_browsing.result', 'flagged');
    }

    public function test_redirect_diikuti_dan_dicatat(): void
    {
        $this->fakeServices([
            'bit.ly/*' => Http::response('', 301, ['Location' => 'https://contoh-toko.com/promo']),
            'contoh-toko.com/*' => $this->html('Toko'),
        ]);

        $response = $this->check('https://bit.ly/abc123')
            ->assertOk()
            ->assertJsonPath('redirect_hosts', ['bit.ly', 'contoh-toko.com'])
            ->assertJsonPath('checks.redirect.count', 1)
            ->assertJsonPath('level', 'Rendah');

        $this->assertContains('shortener', $this->signalIds($response));
        $this->assertContains('redirect_cross_domain', $this->signalIds($response));
    }

    public function test_domain_baru_hanya_satu_indikator_dan_registrar_dibaca(): void
    {
        $this->fakeServices([
            'toko-baru-sekali.com/*' => $this->html('Toko'),
            'rdap.org/*' => Http::response($this->rdap(3, 'Contoh Registrar')),
        ]);

        $response = $this->check('https://toko-baru-sekali.com')
            ->assertOk()
            ->assertJsonPath('checks.domain_age.domains.0.days', 3)
            ->assertJsonPath('checks.domain_age.domains.0.registrar', 'Contoh Registrar')
            ->assertJsonPath('level', 'Rendah'); // domain baru saja bukan bukti berbahaya

        $this->assertSame(['domain_new'], $this->signalIds($response));
    }

    // ---------------------------------------------------------------- kegagalan layanan = belum lengkap, bukan aman

    public function test_url_tidak_dapat_dihubungi_ditandai_belum_lengkap(): void
    {
        $this->fakeServices(['situs-mati.example/*' => fn () => throw new ConnectionException('Connection refused')]);

        $response = $this->check('https://situs-mati.example')
            ->assertOk()
            ->assertJsonPath('checks.redirect.state', 'unavailable')
            ->assertJsonPath('checks.page.state', 'skipped')
            ->assertJsonPath('coverage.status', 'sebagian')
            ->assertJsonPath('score', 0);

        $this->assertStringContainsString('jangan dianggap aman', $response->json('coverage_note'));
    }

    public function test_api_key_tidak_valid_tidak_dianggap_aman(): void
    {
        $this->fakeServices([
            'safebrowsing.googleapis.com/*' => Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT']], 400),
            'contoh-lain.com/*' => $this->html('Contoh'),
        ]);

        $response = $this->check('https://contoh-lain.com')
            ->assertOk()
            ->assertJsonPath('checks.safe_browsing.state', 'unavailable')
            ->assertJsonPath('coverage.status', 'sebagian');

        $this->assertStringContainsString('API key', $response->json('checks.safe_browsing.note'));
        $this->assertNotNull($response->json('coverage_note'));
    }

    public function test_timeout_api_ditangani(): void
    {
        $this->fakeServices([
            'safebrowsing.googleapis.com/*' => fn () => throw new ConnectionException('cURL error 28: timeout'),
            'rdap.org/*' => fn () => throw new ConnectionException('cURL error 28: timeout'),
            'contoh-lain.com/*' => $this->html('Contoh'),
        ]);

        $this->check('https://contoh-lain.com')
            ->assertOk()
            ->assertJsonPath('checks.safe_browsing.state', 'unavailable')
            ->assertJsonPath('checks.domain_age.state', 'unavailable')
            ->assertJsonPath('coverage.status', 'sebagian');
    }

    public function test_tanpa_api_key_status_belum_diaktifkan(): void
    {
        config(['services.safe_browsing.key' => null, 'services.virustotal.key' => null]);
        $this->fakeServices(['contoh-lain.com/*' => $this->html('Contoh')]);

        $this->check('https://contoh-lain.com')
            ->assertOk()
            ->assertJsonPath('checks.safe_browsing.state', 'not_configured')
            ->assertJsonPath('checks.virustotal.state', 'not_configured')
            ->assertJsonPath('coverage.status', 'sebagian');
    }

    // ---------------------------------------------------------------- VirusTotal

    public function test_virustotal_analisis_belum_selesai_ditandai_pending(): void
    {
        $this->fakeServices([
            'www.virustotal.com/api/v3/urls/*' => Http::response(['data' => ['attributes' => [
                'last_analysis_stats' => ['malicious' => 0, 'suspicious' => 0, 'harmless' => 0, 'undetected' => 0],
            ]]], 200),
            'contoh-lain.com/*' => $this->html('Contoh'),
        ]);

        $this->check('https://contoh-lain.com')
            ->assertOk()
            ->assertJsonPath('checks.virustotal.state', 'pending')
            ->assertJsonPath('coverage.status', 'sebagian');
    }

    public function test_virustotal_banyak_mesin_menandai_terkonfirmasi(): void
    {
        $this->fakeServices([
            'www.virustotal.com/api/v3/urls/*' => Http::response(['data' => ['attributes' => [
                'last_analysis_date' => 1700000000,
                'last_analysis_stats' => ['malicious' => 8, 'suspicious' => 1, 'harmless' => 60, 'undetected' => 20],
            ]]], 200),
            'contoh-lain.com/*' => $this->html('Contoh'),
        ]);

        $this->check('https://contoh-lain.com')
            ->assertOk()
            ->assertJsonPath('level', 'Tinggi')
            ->assertJsonPath('confirmed', true);
    }

    public function test_satu_mesin_virustotal_bukan_bukti_kuat(): void
    {
        $this->fakeServices([
            'www.virustotal.com/api/v3/urls/*' => Http::response(['data' => ['attributes' => [
                'last_analysis_date' => 1700000000,
                'last_analysis_stats' => ['malicious' => 1, 'suspicious' => 0, 'harmless' => 70, 'undetected' => 20],
            ]]], 200),
            'contoh-lain.com/*' => $this->html('Contoh'),
        ]);

        $this->check('https://contoh-lain.com')
            ->assertOk()
            ->assertJsonPath('level', 'Rendah')
            ->assertJsonPath('confirmed', false);
    }

    public function test_alamat_tidak_dikirim_ke_virustotal_tanpa_izin(): void
    {
        $this->fakeServices(['contoh-lain.com/*' => $this->html('Contoh')]);

        $this->check('https://contoh-lain.com')->assertOk()->assertJsonPath('checks.virustotal.result', 'unknown');

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), 'virustotal.com'));
    }

    public function test_alamat_dikirim_ke_virustotal_jika_diizinkan_dan_status_pending_ditangani(): void
    {
        $this->fakeServices([
            'www.virustotal.com/api/v3/urls' => Http::response(['data' => ['type' => 'analysis', 'id' => 'u-abc123-1700000000']], 200),
            'www.virustotal.com/api/v3/analyses/*' => Http::response(['data' => ['attributes' => ['status' => 'queued']]], 200),
            'contoh-lain.com/*' => $this->html('Contoh'),
        ]);

        $this->check('https://contoh-lain.com', ['submit_to_virustotal' => true])
            ->assertOk()
            ->assertJsonPath('checks.virustotal.state', 'pending')
            ->assertJsonPath('coverage.status', 'sebagian');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/v3/urls'));
    }

    public function test_batas_permintaan_virustotal_dihormati(): void
    {
        config(['jeda_cek.virustotal_limits.per_minute' => 1]);
        $this->fakeServices([
            'contoh-lain.com/*' => $this->html('Contoh'),
            'contoh-ketiga.com/*' => $this->html('Contoh'),
        ]);

        $this->check('https://contoh-lain.com')->assertOk()->assertJsonPath('checks.virustotal.state', 'ok');
        $this->check('https://contoh-ketiga.com')->assertOk()->assertJsonPath('checks.virustotal.state', 'unavailable');

        // Pembatas internal menahan panggilan kedua: hanya SATU permintaan yang sampai ke VirusTotal.
        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), 'virustotal.com')));
    }

    // ---------------------------------------------------------------- keamanan (SSRF)

    public function test_alamat_localhost_dan_ip_internal_tidak_pernah_dibuka(): void
    {
        $this->fakeServices();

        $response = $this->check('http://127.0.0.1/admin')
            ->assertOk()
            ->assertJsonPath('checks.redirect.state', 'blocked');

        $this->assertContains('internal_target', $this->signalIds($response));
        Http::assertNothingSent(); // alamat internal tidak dikirim ke layanan eksternal mana pun
    }

    public function test_nama_domain_yang_oleh_dns_diarahkan_ke_ip_privat_tidak_dibuka_tetapi_tetap_diperiksa(): void
    {
        $this->app->instance(\App\Services\Cek\HostResolver::class, new FakeHostResolver(['internal.contoh.com' => ['10.0.0.5']]));
        $this->fakeServices();

        $response = $this->check('https://internal.contoh.com/data')
            ->assertOk()
            ->assertJsonPath('checks.redirect.state', 'blocked')
            // Nama domain publik: pemeriksaan eksternal TETAP berjalan (bukan "dilewati semua").
            ->assertJsonPath('checks.safe_browsing.state', 'ok')
            ->assertJsonPath('checks.virustotal.state', 'ok')
            ->assertJsonPath('checks.domain_age.state', 'ok')
            ->assertJsonPath('coverage.status', 'sebagian');

        $this->assertStringContainsString('10.0.0.5', $response->json('checks.redirect.note'));
        $this->assertNotContains('internal_target', $this->signalIds($response)); // tidak menuduh tanpa bukti
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'internal.contoh.com'));
    }

    public function test_dns_sinkhole_atau_fake_ip_tidak_membuat_semua_pemeriksaan_dilewati(): void
    {
        foreach (['0.0.0.0', '198.18.0.15'] as $ip) {
            $this->app->instance(\App\Services\Cek\HostResolver::class, new FakeHostResolver(['links.contoh.com' => [$ip]]));
            $this->fakeServices();

            $this->check('https://links.contoh.com/abc123')
                ->assertOk()
                ->assertJsonPath('checks.safe_browsing.state', 'ok')
                ->assertJsonPath('checks.domain_age.state', 'ok');
        }
    }

    public function test_jawaban_dns64_tidak_dianggap_alamat_internal(): void
    {
        $this->app->instance(\App\Services\Cek\HostResolver::class, new FakeHostResolver(['dns64.contoh.com' => ['93.184.216.34', '64:ff9b::5db8:d822']]));
        $this->fakeServices(['dns64.contoh.com/*' => $this->html('Contoh')]);

        $this->check('https://dns64.contoh.com/')
            ->assertOk()
            ->assertJsonPath('checks.redirect.state', 'ok')
            ->assertJsonPath('checks.page.state', 'ok');
    }

    public function test_redirect_ke_metadata_cloud_diblokir_di_lompatan_berikutnya(): void
    {
        $this->fakeServices([
            'pengalih.contoh.com/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        ]);

        $this->check('https://pengalih.contoh.com/x')
            ->assertOk()
            ->assertJsonPath('checks.redirect.state', 'blocked');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '169.254.169.254'));
    }

    public function test_port_selain_web_standar_tidak_dibuka(): void
    {
        $this->fakeServices();

        $this->check('https://contoh.com:8443/')->assertOk()->assertJsonPath('checks.redirect.state', 'blocked');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '8443'));
    }

    public function test_host_internal_localhost_ditolak(): void
    {
        $this->fakeServices();

        $this->check('http://admin.localhost/')
            ->assertOk()
            ->assertJsonPath('checks.redirect.state', 'blocked')
            ->assertJsonPath('checks.safe_browsing.state', 'skipped')
            ->assertJsonPath('checks.domain_age.state', 'skipped');

        // Nama host internal tidak boleh bocor ke RDAP, Google, atau VirusTotal.
        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------- privasi

    public function test_parameter_url_tidak_dikirim_ke_google(): void
    {
        $this->fakeServices(['contoh-toko.com/*' => $this->html('Toko')]);

        $this->check('https://user:pass@contoh-toko.com/p?token=RAHASIA#bagian')->assertOk();

        Http::assertSent(function (Request $r) {
            return str_contains($r->url(), 'safebrowsing.googleapis.com')
                && $r['threatInfo']['threatEntries'][0]['url'] === 'https://contoh-toko.com/p';
        });
    }

    public function test_url_lengkap_tidak_disimpan_di_database(): void
    {
        $this->fakeServices(['contoh-toko.com/*' => $this->html('Toko')]);

        $this->check('https://contoh-toko.com/p?token=RAHASIA')->assertOk()->assertJsonPath('saved', true);

        $row = (array) DB::table('jeda_link_checks')->first();
        $this->assertSame('contoh-toko.com', $row['hostname']);
        $this->assertStringNotContainsString('RAHASIA', json_encode($row));
        $this->assertSame('lengkap', $row['coverage']);
    }
}
