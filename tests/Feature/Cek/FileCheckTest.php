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
}
