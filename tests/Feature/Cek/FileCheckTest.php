<?php

namespace Tests\Feature\Cek;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Cek File. Isi file tidak pernah sampai ke server: yang dikirim browser hanyalah ringkasan + SHA-256.
 * Semua layanan eksternal diganti Http::fake (lihat CekTestCase).
 */
class FileCheckTest extends CekTestCase
{
    private const HASH = 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad';

    private function checkFile(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/jeda/api/cek-file', $payload + [
            'size' => 2048, 'sha256' => self::HASH, 'detected_type' => 'unknown', 'content_scanned' => true, 'flags' => [],
        ]);
    }

    private function ids(\Illuminate\Testing\TestResponse $response): array
    {
        return array_column($response->json('signals') ?? [], 'id');
    }

    private function vt(array $stats = null, int $status = 200)
    {
        if ($status !== 200) {
            return Http::response([], $status);
        }

        return Http::response(['data' => ['attributes' => [
            'last_analysis_date' => 1700000000,
            'last_analysis_stats' => $stats ?? ['malicious' => 0, 'suspicious' => 0, 'harmless' => 60, 'undetected' => 10],
        ]]]);
    }

    public function test_teks_eicar_dinilai_tinggi_tetapi_dijelaskan_bukan_virus_sungguhan(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => Http::response([], 404)]);

        $response = $this->checkFile(['filename' => 'laporan.pdf', 'detected_type' => 'pdf', 'flags' => ['eicar_test' => true]])
            ->assertOk()
            ->assertJsonPath('level', 'Tinggi')
            ->assertJsonPath('confirmed', false) // dinilai dari teks, bukan vonis banyak mesin
            ->assertJsonPath('summary.headline', 'File uji antivirus EICAR terdeteksi (bukan virus sungguhan)');

        $this->assertContains('eicar_test', $this->ids($response));
        $this->assertStringContainsString('tidak merusak', implode(' ', $response->json('summary.steps')));
    }

    public function test_tanpa_flag_eicar_pdf_biasa_tidak_terpengaruh(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt()]);

        $response = $this->checkFile(['filename' => 'laporan.pdf', 'detected_type' => 'pdf'])->assertOk();

        $this->assertNotContains('eicar_test', $this->ids($response));
        $this->assertNotSame('Tinggi', $response->json('level'));
    }

    // ---------------------------------------------------------------- file besar

    public function test_pdf_besar_yang_dibaca_sebagian_tidak_diklaim_aman_dan_tidak_tampak_lengkap(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt()]);

        $response = $this->checkFile(['filename' => 'laporan.pdf', 'size' => 80 * 1024 * 1024, 'detected_type' => 'pdf', 'content_partial' => true])
            ->assertOk()
            ->assertJsonPath('checks.content.state', 'partial')
            ->assertJsonPath('coverage.status', 'sebagian')
            ->assertJsonPath('content_partial', true)
            ->assertJsonPath('summary.headline', 'Belum ada tanda bahaya, tetapi pemeriksaan belum lengkap');

        // Klaim "tidak ditemukan" hanya sah bila seluruh isi dipindai.
        $this->assertEmpty(array_filter($response->json('summary.passed'), fn ($p) => str_contains($p, 'JavaScript')));
        $this->assertNotEmpty(array_filter($response->json('summary.unchecked'), fn ($u) => str_contains($u['note'] ?? '', 'bagian awalnya')));
    }

    public function test_pdf_kecil_dibaca_penuh_tetap_boleh_diklaim_tidak_memuat_javascript(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt()]);

        $response = $this->checkFile(['filename' => 'laporan.pdf', 'detected_type' => 'pdf'])
            ->assertOk()
            ->assertJsonPath('checks.content.state', 'ok')
            ->assertJsonPath('content_partial', false);

        $this->assertNotEmpty(array_filter($response->json('summary.passed'), fn ($p) => str_contains($p, 'JavaScript')));
    }

    public function test_apk_besar_dengan_daftar_isi_lengkap_tetap_dianggap_terbaca_penuh(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt()]);

        $response = $this->checkFile([
            'filename' => 'tagihan.apk', 'size' => 120 * 1024 * 1024, 'detected_type' => 'apk',
            'apk_package' => 'com.contoh.app', 'apk_permissions' => ['android.permission.READ_SMS'],
        ])->assertOk()
            ->assertJsonPath('checks.content.state', 'ok')
            ->assertJsonPath('checks.apk_permissions.state', 'ok');

        $this->assertStringContainsString('bertahap', $response->json('checks.content.note'));
        $this->assertContains('apk_perm_sms', $this->ids($response)); // izin berbahaya tetap terbaca dari APK besar
    }

    public function test_file_melebihi_batas_maksimum_hanya_diperiksa_namanya_dan_dijelaskan(): void
    {
        $response = $this->checkFile([
            'filename' => 'undangan.apk', 'size' => 3 * 1024 * 1024 * 1024,
            'sha256' => null, 'detected_type' => null, 'content_scanned' => false,
        ])->assertOk()->assertJsonPath('checks.content.state', 'skipped');

        $this->assertStringContainsString('terlalu besar', $response->json('checks.content.note'));
        $this->assertNotSame('lengkap', $response->json('coverage.status'));
    }

    public function test_file_di_antara_50_mb_dan_2_gb_tidak_lagi_dilewati_sepenuhnya(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt()]);

        // Dulu: size > 50 MB selalu content_scanned=false. Sekarang browser mengirim hash dan hasil baca sebagian.
        $this->checkFile(['filename' => 'game.zip', 'size' => 900 * 1024 * 1024, 'detected_type' => 'zip'])
            ->assertOk()
            ->assertJsonPath('checks.content.state', 'ok')
            ->assertJsonPath('checks.virustotal.state', 'ok');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), self::HASH));
    }

    // ---------------------------------------------------------------- input

    public function test_input_tidak_valid_ditolak(): void
    {
        $this->fakeServices();

        $this->checkFile(['filename' => ''])->assertStatus(422);
        $this->checkFile(['filename' => 'a.pdf', 'sha256' => 'bukan-hash'])->assertStatus(422);
        $this->checkFile(['filename' => 'a.pdf', 'detected_type' => '<script>'])->assertStatus(422);
        $this->checkFile(['filename' => 'a.pdf', 'size' => -1])->assertStatus(422);
        $this->checkFile(['filename' => str_repeat('a', 300)])->assertStatus(422);
        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------- skenario penipuan

    public function test_apk_undangan_hanya_nama_tetap_tinggi_tetapi_hasil_belum_lengkap(): void
    {
        $this->fakeServices();

        $response = $this->checkFile(['filename' => 'Undangan Pernikahan.apk', 'sha256' => null, 'detected_type' => null, 'content_scanned' => false])
            ->assertOk()
            ->assertJsonPath('level', 'Tinggi')
            ->assertJsonPath('coverage.status', 'terbatas');

        foreach (['apk_file', 'scam_pretext_name'] as $id) {
            $this->assertContains($id, $this->ids($response));
        }
        $this->assertStringContainsString('jangan dianggap aman', $response->json('coverage_note'));
        Http::assertNothingSent(); // tanpa hash, tidak ada permintaan ke VirusTotal
    }

    public function test_ekstensi_ganda_dan_karakter_pembalik_arah(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt()]);

        $ganda = $this->checkFile(['filename' => 'Tagihan.pdf.exe', 'detected_type' => 'pe'])->assertOk()->assertJsonPath('level', 'Tinggi');
        $this->assertContains('double_extension', $this->ids($ganda));
        $this->assertTrue($ganda->json('double_extension'));

        $rlo = $this->checkFile(['filename' => "foto\u{202E}gpj.exe", 'detected_type' => 'pe'])->assertOk()->assertJsonPath('level', 'Tinggi');
        $this->assertContains('bidi_control', $this->ids($rlo));
    }

    public function test_isi_program_bernama_gambar_terkonfirmasi_disamarkan(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt()]);

        $response = $this->checkFile(['filename' => 'foto.jpg', 'detected_type' => 'pe'])
            ->assertOk()
            ->assertJsonPath('level', 'Tinggi')
            ->assertJsonPath('confirmed', false);

        $this->assertContains('type_mismatch_executable', $this->ids($response));
        $this->assertGreaterThanOrEqual(7, $response->json('score'));
    }

    public function test_penanda_isi_file_dari_browser_dinilai(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt()]);

        $response = $this->checkFile([
            'filename' => 'surat.pdf', 'detected_type' => 'pdf',
            'flags' => ['pdf_javascript' => true, 'pdf_launch' => true],
        ])->assertOk()->assertJsonPath('level', 'Sedang');

        $this->assertContains('pdf_launch', $this->ids($response));
    }

    public function test_penanda_dan_jenis_yang_tidak_dikenal_ditolak_atau_diabaikan(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt()]);

        $this->checkFile(['filename' => 'a.pdf', 'detected_type' => 'pdf', 'flags' => ['hack' => true]])
            ->assertOk()
            ->assertJsonPath('score', 0);
    }

    // ---------------------------------------------------------------- VirusTotal (hash)

    public function test_file_bersih_belum_dikenal_virustotal_bukan_dianggap_aman(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => Http::response([], 404)]);

        $response = $this->checkFile(['filename' => 'laporan.pdf', 'detected_type' => 'pdf'])
            ->assertOk()
            ->assertJsonPath('score', 0)
            ->assertJsonPath('level', 'Belum ada indikator')
            ->assertJsonPath('coverage.status', 'lengkap')
            ->assertJsonPath('checks.virustotal.result', 'unknown');

        $this->assertStringContainsString('bukan jaminan', $response->json('message'));
        // Hanya SHA-256 yang dikirim ke VirusTotal. Tidak ada nama file di permintaan.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/files/'.self::HASH) && $r->method() === 'GET');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'laporan'));
    }

    public function test_banyak_mesin_virustotal_menandai_berbahaya(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt(['malicious' => 20, 'suspicious' => 2, 'harmless' => 0, 'undetected' => 48])]);

        $this->checkFile(['filename' => 'alat.pdf', 'detected_type' => 'pdf'])
            ->assertOk()
            ->assertJsonPath('score', 9)
            ->assertJsonPath('level', 'Tinggi')
            ->assertJsonPath('confirmed', true);
    }

    public function test_satu_mesin_saja_tidak_dianggap_terkonfirmasi(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt(['malicious' => 1, 'suspicious' => 0, 'harmless' => 60, 'undetected' => 9])]);

        $response = $this->checkFile(['filename' => 'a.pdf', 'detected_type' => 'pdf'])
            ->assertOk()
            ->assertJsonPath('level', 'Rendah')
            ->assertJsonPath('confirmed', false);

        $this->assertContains('virustotal_file_some', $this->ids($response));
        $this->assertNotContains('virustotal_file_malicious', $this->ids($response));
    }

    public function test_virustotal_gagal_dilaporkan_sebagai_belum_lengkap_bukan_aman(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt(null, 500)]);

        $response = $this->checkFile(['filename' => 'a.pdf', 'detected_type' => 'pdf'])
            ->assertOk()
            ->assertJsonPath('coverage.status', 'sebagian')
            ->assertJsonPath('checks.virustotal.state', 'unavailable');

        $this->assertStringContainsString('jangan dianggap aman', $response->json('coverage_note'));
    }

    public function test_tanpa_api_key_virustotal_dilaporkan_belum_diaktifkan(): void
    {
        config(['services.virustotal.key' => null]);
        $this->fakeServices();

        $this->checkFile(['filename' => 'a.pdf', 'detected_type' => 'pdf'])
            ->assertOk()
            ->assertJsonPath('checks.virustotal.state', 'not_configured')
            ->assertJsonPath('coverage.status', 'sebagian');
        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------- privasi penyimpanan

    public function test_nama_file_dan_hash_tidak_disimpan(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => $this->vt()]);

        $this->checkFile(['filename' => 'Rahasia Gaji Karyawan.pdf', 'detected_type' => 'pdf'])->assertOk()->assertJsonPath('saved', true);

        $row = DB::table('jeda_file_checks')->first();
        $this->assertSame('pdf', $row->extension);
        $this->assertSame('pdf', $row->detected_type);
        $dump = json_encode((array) $row);
        $this->assertStringNotContainsString('Rahasia', $dump);
        $this->assertStringNotContainsString(self::HASH, $dump);
    }

    // ---------------------------------------------------------------- APK: izin + ringkasan

    private const A = 'android.permission.';

    public function test_apk_undangan_yang_meminta_sms_dan_aksesibilitas_tinggi_dengan_ringkasan(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => Http::response([], 404)]);

        $response = $this->checkFile([
            'filename' => 'Undangan Pernikahan.apk', 'detected_type' => 'apk', 'apk_package' => 'com.undangan.digital',
            'apk_permissions' => [self::A.'READ_SMS', self::A.'BIND_ACCESSIBILITY_SERVICE', self::A.'SYSTEM_ALERT_WINDOW', self::A.'INTERNET', 'com.vendor.permission.X'],
        ])->assertOk()
            ->assertJsonPath('level', 'Tinggi')
            ->assertJsonPath('summary.headline', 'Jangan pasang aplikasi ini')
            ->assertJsonPath('summary.kind', 'apk')
            ->assertJsonPath('apk.readable', true)
            ->assertJsonPath('apk.package', 'com.undangan.digital')
            ->assertJsonPath('apk.other_count', 1)
            ->assertJsonPath('apk.permissions.0.kind', 'sms'); // yang paling berbahaya tampil dulu

        foreach (['apk_perm_sms', 'apk_perm_accessibility', 'apk_perm_overlay'] as $id) {
            $this->assertContains($id, $this->ids($response));
        }
        $this->assertStringContainsString('mode pesawat', implode(' ', $response->json('summary.steps')));
        $this->assertCount(3, $response->json('summary.reasons'));
    }

    public function test_apk_biasa_tanpa_izin_berbahaya_hanya_sedang(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => Http::response([], 404)]);

        $response = $this->checkFile(['filename' => 'kalkulator.apk', 'detected_type' => 'apk', 'apk_permissions' => [self::A.'INTERNET']])
            ->assertOk()
            ->assertJsonPath('level', 'Sedang');

        $this->assertNotContains('apk_perm_sms', $this->ids($response));
        $this->assertNotEmpty(array_filter($response->json('summary.passed'), fn ($p) => str_contains($p, 'izin')));
    }

    public function test_manifest_apk_tidak_terbaca_dilaporkan_belum_lengkap(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => Http::response([], 404)]);

        $this->checkFile(['filename' => 'aneh.apk', 'detected_type' => 'apk', 'flags' => ['apk_unreadable' => true]])
            ->assertOk()
            ->assertJsonPath('coverage.status', 'sebagian')
            ->assertJsonPath('checks.apk_permissions.state', 'skipped')
            ->assertJsonPath('apk.readable', false);
    }

    public function test_nama_izin_yang_tidak_valid_dibuang_tanpa_menolak_permintaan(): void
    {
        $this->fakeServices();

        $response = $this->checkFile(['filename' => 'a.apk', 'detected_type' => 'apk', 'apk_permissions' => [
            '<script>alert(1)</script>', 'bukan izin!', '', 42, str_repeat('a', 121), self::A.'READ_SMS', 'com.vendor.permission.X',
        ]])->assertOk()
            ->assertJsonPath('apk.readable', true)
            ->assertJsonPath('apk.permissions.0.code', self::A.'READ_SMS')
            ->assertJsonPath('apk.other_count', 1); // hanya yang valid dan tidak dikenal

        $this->assertContains('apk_perm_sms', $this->ids($response)); // izin berisiko tetap dinilai
        $this->assertStringNotContainsString('<script>', $response->getContent());
    }

    public function test_nama_paket_tidak_valid_dibuang_tanpa_menolak_permintaan(): void
    {
        $this->fakeServices();

        $this->checkFile(['filename' => 'a.apk', 'detected_type' => 'apk', 'apk_package' => 'bukan paket!', 'apk_permissions' => [self::A.'READ_SMS']])
            ->assertOk()
            ->assertJsonPath('apk.package', null)
            ->assertJsonPath('apk.readable', true);
        $this->checkFile(['filename' => 'a.apk', 'detected_type' => 'apk', 'apk_package' => ['x'], 'apk_permissions' => [self::A.'READ_SMS']])
            ->assertOk()->assertJsonPath('apk.package', null);
        $this->checkFile(['filename' => 'a.apk', 'detected_type' => 'apk', 'apk_package' => 'com.contoh.app', 'apk_permissions' => [self::A.'READ_SMS']])
            ->assertOk()->assertJsonPath('apk.package', 'com.contoh.app');
    }

    public function test_daftar_izin_bukan_array_diperlakukan_sebagai_tidak_terbaca(): void
    {
        $this->fakeServices();

        $this->checkFile(['filename' => 'a.apk', 'detected_type' => 'apk', 'apk_permissions' => 'READ_SMS'])
            ->assertOk()
            ->assertJsonPath('apk.readable', false);
    }

    public function test_izin_sampai_500_diterima_dan_izin_berisiko_tidak_terdorong_keluar_oleh_izin_pengisi(): void
    {
        $this->fakeServices();

        // 150 izin (batas lama) dan 500 izin (batas baru) diterima.
        foreach ([151, 500] as $n) {
            $junk = array_map(fn ($i) => "com.junk.P$i", range(1, $n));
            $this->checkFile(['filename' => 'a.apk', 'detected_type' => 'apk', 'apk_permissions' => $junk])->assertOk();
        }

        // 700 izin pengisi lebih dulu, izin berbahaya di urutan terakhir: tetap dinilai.
        $junk = array_map(fn ($i) => "com.junk.P$i", range(1, 700));
        $response = $this->checkFile(['filename' => 'undangan.apk', 'detected_type' => 'apk',
            'apk_permissions' => array_merge($junk, [self::A.'READ_SMS', self::A.'BIND_ACCESSIBILITY_SERVICE'])])
            ->assertOk()
            ->assertJsonPath('apk.permissions.0.kind', 'sms');

        $this->assertContains('apk_perm_sms', $this->ids($response));
        $this->assertContains('apk_perm_accessibility', $this->ids($response));
        $this->assertSame(498, $response->json('apk.other_count')); // 500 dikirim: 2 dikenal + 498 lainnya
        $this->assertLessThanOrEqual(500, count($response->json('apk.permissions')) + $response->json('apk.other_count'));
    }

    public function test_izin_apk_normal_tanpa_izin_berbahaya_tidak_berubah(): void
    {
        $this->fakeServices();

        $response = $this->checkFile(['filename' => 'kalkulator.apk', 'detected_type' => 'apk', 'apk_permissions' => [self::A.'INTERNET', self::A.'INTERNET']])
            ->assertOk()->assertJsonPath('apk.readable', true);

        $this->assertNotContains('apk_perm_sms', $this->ids($response));
        $this->assertCount(1, $response->json('apk.permissions')); // duplikat digabung
    }

    public function test_ringkasan_file_bersih_menyebut_yang_sudah_dicek_dan_yang_belum(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files/*' => Http::response([], 404)]);

        $response = $this->checkFile(['filename' => 'laporan.pdf', 'detected_type' => 'pdf'])
            ->assertOk()
            ->assertJsonPath('summary.headline', 'Tidak ada tanda bahaya yang terdeteksi');

        $this->assertGreaterThanOrEqual(3, count($response->json('summary.passed')));
        $this->assertContains('Reputasi di VirusTotal', array_column($response->json('summary.unchecked'), 'label'));
    }
}
