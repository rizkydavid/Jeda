<?php

namespace Tests\Feature\Cek;

use App\Services\Cek\UploadLimit;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Pemindaian antivirus opsional lewat VirusTotal. Ini SATU-SATUNYA jalur yang mengirim isi file,
 * jadi yang diuji terutama: wajib ada persetujuan, nama file tidak ikut dikirim, batas ukuran dan kuota dihormati,
 * dan status hanya bisa ditanyakan untuk pemindaian milik sesi sendiri.
 */
class FileScanTest extends CekTestCase
{
    private const ID = 'ZmlsZS1hbmFsaXNpcw==-1700000000';

    private const HASH = 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad';

    private function uploadScan(array $extra = [], ?UploadedFile $file = null): \Illuminate\Testing\TestResponse
    {
        return $this->post('/jeda/api/cek-file/pindai', $extra + [
            'file' => $file ?? UploadedFile::fake()->create('catatan-rahasia.txt', 1, 'text/plain'),
            'consent' => '1',
        ], ['Accept' => 'application/json']);
    }

    private function pollStatus(string $id = self::ID): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['jeda_scans' => [self::ID => self::HASH]])
            ->getJson('/jeda/api/cek-file/pindai/status?id='.urlencode($id));
    }

    private function analysisResponse(string $status, array $stats = [], array $results = [])
    {
        return Http::response(['data' => ['attributes' => ['status' => $status, 'stats' => $stats, 'results' => $results]]]);
    }

    private function fakeAnalysisResponse($response): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/analyses/*' => $response]);
    }

    // ---------------------------------------------------------------- unggah

    public function test_tanpa_persetujuan_tidak_ada_yang_dikirim(): void
    {
        $this->fakeServices();

        $this->uploadScan(['consent' => '0'])->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->post('/jeda/api/cek-file/pindai', ['file' => UploadedFile::fake()->create('a.txt', 1)], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('consent');

        Http::assertNothingSent();
    }

    public function test_file_diteruskan_ke_virustotal_tanpa_nama_aslinya(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files' => Http::response(['data' => ['id' => self::ID, 'type' => 'analysis']])]);

        $this->uploadScan()->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('analysis_id', self::ID);

        Http::assertSent(function (Request $r) {
            return $r->url() === 'https://www.virustotal.com/api/v3/files'
                && $r->method() === 'POST'
                && $r->isMultipart()
                && $r->hasHeader('x-apikey', 'kunci-uji')
                && str_contains($r->body(), 'filename="sample.bin"')
                && ! str_contains($r->body(), 'catatan-rahasia');
        });
    }

    public function test_file_terlalu_besar_ditolak_sebelum_dikirim(): void
    {
        config(['jeda_cek.file_upload.max_bytes' => 2048]);
        $this->fakeServices();

        $this->uploadScan([], UploadedFile::fake()->create('besar.bin', 10))
            ->assertStatus(422)->assertJsonValidationErrors('file');

        Http::assertNothingSent();
    }

    public function test_tanpa_api_key_dijelaskan_dan_tidak_ada_permintaan(): void
    {
        config(['services.virustotal.key' => null]);
        $this->fakeServices();

        $response = $this->uploadScan()->assertStatus(503)->assertJsonPath('status', 'error');
        $this->assertStringContainsString('belum diaktifkan', $response->json('message'));

        Http::assertNothingSent();
    }

    public function test_kuota_virustotal_penuh_tidak_mengirim_dan_dijelaskan(): void
    {
        $this->fakeServices();
        foreach (range(1, 4) as $i) {
            RateLimiter::hit('jeda:vt:minute', 60);
        }

        $response = $this->uploadScan()->assertStatus(429);
        $this->assertStringContainsString('Kuota', $response->json('message'));

        Http::assertNothingSent();
    }

    public function test_virustotal_menolak_unggahan_dilaporkan_ramah(): void
    {
        $this->fakeServices(['www.virustotal.com/api/v3/files' => Http::response([], 500)]);

        $this->uploadScan()->assertStatus(503)->assertJsonPath('status', 'error');
    }

    // ---------------------------------------------------------------- status

    public function test_status_hanya_untuk_pemindaian_milik_sesi_ini(): void
    {
        $this->fakeServices();

        $this->getJson('/jeda/api/cek-file/pindai/status?id='.urlencode(self::ID))->assertStatus(404);
        $this->pollStatus('QUJDREVGR0g=')->assertStatus(404);          // id valid tetapi bukan milik sesi
        $this->pollStatus('bukan id!!')->assertStatus(404);            // bentuk tidak valid

        Http::assertNothingSent();
    }

    public function test_status_menunggu_selama_antivirus_belum_selesai(): void
    {
        $this->fakeAnalysisResponse($this->analysisResponse('queued'));

        $this->pollStatus()->assertOk()->assertJsonPath('status', 'pending');
    }

    public function test_banyak_mesin_menandai_dinilai_terkonfirmasi_dan_disimpan_di_cache_hash(): void
    {
        $this->fakeAnalysisResponse($this->analysisResponse('completed',
            ['malicious' => 60, 'suspicious' => 0, 'harmless' => 5, 'undetected' => 5],
            [
                'Kaspersky' => ['category' => 'malicious', 'engine_name' => 'Kaspersky', 'result' => 'EICAR-Test-File (not a virus)'],
                'Lainnya' => ['category' => 'undetected', 'engine_name' => 'Lainnya', 'result' => null],
            ]));

        $response = $this->pollStatus()->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('verdict', 'flagged')
            ->assertJsonPath('confirmed', true)
            ->assertJsonPath('level', 'Tinggi')
            ->assertJsonPath('malicious', 60)
            ->assertJsonPath('total', 70)
            ->assertJsonPath('engines.0.engine', 'Kaspersky');
        $this->assertStringContainsString('EICAR-Test-File', $response->json('engines.0.name'));

        // Hasilnya diingat: Cek File untuk hash yang sama tidak lagi memanggil VirusTotal (stub /files tidak ada,
        // jadi permintaan nyasar akan membuat test gagal).
        $this->postJson('/jeda/api/cek-file', [
            'filename' => 'laporan.pdf', 'size' => 2048, 'sha256' => self::HASH,
            'detected_type' => 'pdf', 'content_scanned' => true, 'flags' => [],
        ])->assertOk()->assertJsonPath('checks.virustotal.result', 'flagged')->assertJsonPath('level', 'Tinggi');
    }

    public function test_satu_atau_dua_mesin_saja_tidak_dianggap_terkonfirmasi(): void
    {
        $this->fakeAnalysisResponse($this->analysisResponse('completed', ['malicious' => 2, 'suspicious' => 0, 'harmless' => 60, 'undetected' => 8]));

        $this->pollStatus()->assertOk()
            ->assertJsonPath('verdict', 'flagged')
            ->assertJsonPath('confirmed', false)
            ->assertJsonPath('level', 'Sedang');
    }

    public function test_bersih_dijelaskan_bukan_jaminan(): void
    {
        $this->fakeAnalysisResponse($this->analysisResponse('completed', ['malicious' => 0, 'suspicious' => 0, 'harmless' => 60, 'undetected' => 10]));

        $response = $this->pollStatus()->assertOk()
            ->assertJsonPath('verdict', 'clean')
            ->assertJsonPath('level', null);
        $this->assertStringContainsString('70 mesin', $response->json('headline'));
        $this->assertStringContainsString('bukan jaminan', $response->json('message'));
    }

    public function test_jenis_file_tidak_didukung_tidak_dianggap_bersih(): void
    {
        $this->fakeAnalysisResponse($this->analysisResponse('completed', ['malicious' => 0, 'suspicious' => 0, 'harmless' => 0, 'undetected' => 0, 'type-unsupported' => 70]));

        $this->pollStatus()->assertOk()->assertJsonPath('verdict', 'unknown')->assertJsonPath('level', null);
    }

    public function test_kuota_penuh_saat_menunggu_hasil_berarti_tunggu_bukan_gagal(): void
    {
        $this->fakeServices();
        foreach (range(1, 4) as $i) {
            RateLimiter::hit('jeda:vt:minute', 60);
        }

        $this->pollStatus()->assertOk()->assertJsonPath('status', 'busy');
        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------- batas ukuran

    public function test_batas_ukuran_tidak_pernah_melebihi_batas_virustotal_dan_php(): void
    {
        config(['jeda_cek.file_upload.max_bytes' => PHP_INT_MAX]);
        $this->assertLessThanOrEqual(32 * 1024 * 1024, UploadLimit::bytes());
        $this->assertGreaterThan(0, UploadLimit::bytes());

        $this->assertSame(2 * 1024 * 1024, UploadLimit::iniBytes('2M'));
        $this->assertSame(512 * 1024, UploadLimit::iniBytes('512K'));
        $this->assertSame(1024 ** 3, UploadLimit::iniBytes('1G'));
        $this->assertSame(0, UploadLimit::iniBytes('0'));
        $this->assertSame(0, UploadLimit::iniBytes('-1'));
        $this->assertSame(0, UploadLimit::iniBytes(''));
    }
}
