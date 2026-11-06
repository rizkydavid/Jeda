<?php

namespace Tests\Feature\Cek;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeminiExplanationTest extends CekTestCase
{
    private const GEMINI = 'generativelanguage.googleapis.com/*';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.gemini.key' => 'kunci-gemini',
            'services.gemini.model' => 'gemini-test',
            'services.gemini.thinking' => null,
            'jeda_cek.gemini.retry_sleep_ms' => 0, // tidak menunggu saat uji kegagalan
        ]);
    }

    /** Menjalankan pemeriksaan tautan (data simulasi) dan mengembalikan [respons, token, label tanda]. */
    private function phishingCheck(array $stubs = []): array
    {
        $this->fakeServices($stubs + [
            'links.next-go.site/*' => Http::response('', 302, ['Location' => 'https://masuk-klikbca.xyz/verifikasi']),
            'masuk-klikbca.xyz/*' => $this->html('KlikBCA Individual - Login', '<form action="/k"><input type="password" name="p"></form>'),
            'rdap.org/*' => Http::response($this->rdap(12)),
        ]);

        $response = $this->check('https://links.next-go.site/rjB80lK')->assertOk();
        $labels = array_column(array_filter($response->json('signals'), fn ($s) => $s['points'] > 0), 'label');

        return [$response, $response->json('explain_token'), $labels];
    }

    private function explain(string $token): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/jeda/api/cek-tautan/penjelasan', ['token' => $token]);
    }

    private function reply(array|string $content, int $status = 200)
    {
        $text = is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : $content;

        return Http::response(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]], $status);
    }

    private function good(array $labels, array $override = []): array
    {
        return $override + [
            'ringkasan' => 'Tautan ini menunjukkan beberapa tanda yang mirip penipuan, jadi sebaiknya tidak dibuka.',
            'penjelasan_indikator' => array_map(fn ($l) => ['indikator' => $l, 'penjelasan' => "Penjelasan sederhana untuk $l."], array_slice($labels, 0, 2)),
            'alasan_tingkat_risiko' => 'Tingkat risikonya Tinggi karena beberapa tanda muncul bersamaan.',
            'rekomendasi' => ['Jangan buka tautan ini.', 'Jangan isi kata sandi atau kode OTP.', 'Buka situs resmi lewat aplikasi atau pencarian sendiri.'],
            'peringatan_kelengkapan' => null,
        ];
    }

    /** Teks yang diterima Gemini (ringkasan JSON di dalam tag <data>), bukan JSON ter-escape. */
    private function sentText(Request $r): string
    {
        return (string) ($r->data()['contents'][0]['parts'][0]['text'] ?? '');
    }

    private function geminiCalls(): int
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'generativelanguage.googleapis.com'))->count();
    }

    // ---------------------------------------------------------------- privasi dan peran AI

    public function test_penjelasan_dibuat_dari_ringkasan_tanpa_alamat_dan_kunci_di_header(): void
    {
        [$check, $token, $labels] = $this->phishingCheck();
        $this->assertSame(40, strlen($token));
        $check->assertJsonPath('explanation_available', true);

        Http::fake([self::GEMINI => $this->reply($this->good($labels))]);
        $this->explain($token)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('cached', false)
            ->assertJsonCount(3, 'explanation.rekomendasi');

        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }
            $body = json_encode($r->data(), JSON_UNESCAPED_UNICODE); // seluruh isi permintaan

            return ! str_contains($body, 'next-go.site')        // alamat awal tidak ikut
                && ! str_contains($body, 'masuk-klikbca')        // tujuan akhir tidak ikut
                && ! str_contains($body, 'rjB80lK')
                && str_contains($this->sentText($r), '"risk_score":10') // skor berasal dari sistem
                && $r->header('x-goog-api-key') === ['kunci-gemini']
                && ! str_contains($r->url(), 'kunci-gemini');    // kunci tidak ada di URL
        });
    }

    public function test_hasil_utama_tidak_berubah_dan_penjelasan_dipakai_ulang(): void
    {
        [, $token, $labels] = $this->phishingCheck();
        Http::fake([self::GEMINI => $this->reply($this->good($labels))]);

        $this->explain($token)->assertOk()->assertJsonPath('cached', false);
        $this->explain($token)->assertOk()->assertJsonPath('cached', true);

        $this->assertSame(1, $this->geminiCalls()); // panggilan kedua tidak memakai kuota Gemini
        $row = DB::table('jeda_link_checks')->where('token', $token)->first();
        $this->assertSame(10, (int) $row->risk_score);
        $this->assertSame('Tinggi', $row->risk_level);
        $this->assertNotNull($row->explanation);
    }

    public function test_ancaman_terkonfirmasi_dan_kelengkapan_dikirim_sebagai_data_terstruktur(): void
    {
        [, $token, $labels] = $this->phishingCheck([
            'safebrowsing.googleapis.com/*' => Http::response(['matches' => [['threatType' => 'SOCIAL_ENGINEERING']]], 200),
        ]);
        Http::fake([self::GEMINI => $this->reply($this->good($labels))]);

        $this->explain($token)->assertOk();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'generativelanguage')
            && str_contains($this->sentText($r), '"confirmed_threat":true'));
    }

    // ---------------------------------------------------------------- kegagalan: hasil utama tetap ada

    public function test_gemini_gagal_tidak_mengganggu_hasil_utama(): void
    {
        [$check, $token] = $this->phishingCheck();
        Http::fake([self::GEMINI => Http::response(['error' => ['message' => 'unavailable']], 500)]);

        $this->explain($token)
            ->assertStatus(503)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'server_error');

        $check->assertJsonPath('level', 'Tinggi'); // hasil utama sudah diterima dan tidak terpengaruh
        $this->assertNull(DB::table('jeda_link_checks')->where('token', $token)->value('explanation'));
    }

    public function test_tanpa_api_key_gemini(): void
    {
        [, $token] = $this->phishingCheck();
        config(['services.gemini.key' => null]);

        $this->explain($token)->assertStatus(503)->assertJsonPath('reason', 'not_configured');
        $this->assertSame(0, $this->geminiCalls());
    }

    public function test_tombol_ai_tidak_ditawarkan_jika_gemini_belum_diaktifkan(): void
    {
        config(['services.gemini.key' => null]);
        $this->fakeServices(['contoh-lain.com/*' => $this->html('Contoh')]);

        $this->check('https://contoh-lain.com')->assertOk()->assertJsonPath('explanation_available', false);
    }

    public function test_token_tidak_dikenal_dan_input_tidak_valid(): void
    {
        $this->explain(Str::random(40))->assertStatus(404);
        $this->postJson('/jeda/api/cek-tautan/penjelasan', [])->assertStatus(422);
        $this->postJson('/jeda/api/cek-tautan/penjelasan', ['token' => 'terlalu-pendek'])->assertStatus(422);
        $this->postJson('/jeda/api/cek-tautan/penjelasan', ['token' => str_repeat('!', 40)])->assertStatus(422);
    }

    public function test_jawaban_yang_bukan_json_ditolak(): void
    {
        [, $token] = $this->phishingCheck();
        Http::fake([self::GEMINI => $this->reply('Maaf, saya tidak bisa membantu.')]);

        $this->explain($token)->assertStatus(503)->assertJsonPath('reason', 'bad_response');
    }

    public function test_json_dalam_blok_markdown_tetap_diterima(): void
    {
        [, $token, $labels] = $this->phishingCheck();
        Http::fake([self::GEMINI => $this->reply("```json\n".json_encode($this->good($labels))."\n```")]);

        $this->explain($token)->assertOk()->assertJsonPath('ok', true);
    }

    // ---------------------------------------------------------------- penjaga jawaban AI

    public function test_klaim_aman_ditolak(): void
    {
        [, $token, $labels] = $this->phishingCheck();
        Http::fake([self::GEMINI => $this->reply($this->good($labels, ['ringkasan' => 'Tautan ini aman untuk dibuka.']))]);

        $this->explain($token)->assertStatus(503)->assertJsonPath('reason', 'rejected');
        $this->assertNull(DB::table('jeda_link_checks')->where('token', $token)->value('explanation'));
    }

    public function test_kalimat_peringatan_yang_memuat_kata_aman_tidak_ditolak(): void
    {
        [, $token, $labels] = $this->phishingCheck();
        Http::fake([self::GEMINI => $this->reply($this->good($labels, [
            'ringkasan' => 'Jangan menganggap tautan ini aman, karena ada beberapa tanda bahaya.',
            'rekomendasi' => ['Pastikan alamat situs resmi sebelum memasukkan data.', 'Jangan buka tautan ini.', 'Hubungi pihak resmi lewat aplikasi.'],
        ]))]);

        $this->explain($token)->assertOk();
    }

    public function test_tingkat_risiko_yang_bertentangan_ditolak(): void
    {
        [, $token, $labels] = $this->phishingCheck();
        Http::fake([self::GEMINI => $this->reply($this->good($labels, ['ringkasan' => 'Risiko rendah, silakan lanjut.']))]);

        $this->explain($token)->assertStatus(503)->assertJsonPath('reason', 'rejected');
    }

    public function test_skor_yang_bertentangan_ditolak(): void
    {
        [, $token, $labels] = $this->phishingCheck();
        Http::fake([self::GEMINI => $this->reply($this->good($labels, ['alasan_tingkat_risiko' => 'Skornya 2 dari 10 karena sedikit tanda.']))]);

        $this->explain($token)->assertStatus(503)->assertJsonPath('reason', 'rejected');
    }

    public function test_indikator_karangan_dibuang(): void
    {
        [, $token, $labels] = $this->phishingCheck();
        $explanation = $this->good($labels);
        $explanation['penjelasan_indikator'][] = ['indikator' => 'Situs ini milik penipu internasional', 'penjelasan' => 'Temuan karangan.'];
        Http::fake([self::GEMINI => $this->reply($explanation)]);

        $response = $this->explain($token)->assertOk();
        $names = array_column($response->json('explanation.penjelasan_indikator'), 'indikator');

        $this->assertNotContains('Situs ini milik penipu internasional', $names);
        foreach ($names as $name) {
            $this->assertContains($name, $labels);
        }
    }

    public function test_peringatan_kelengkapan_selalu_ada_jika_pemeriksaan_belum_lengkap(): void
    {
        [, $token, $labels] = $this->phishingCheck(['safebrowsing.googleapis.com/*' => Http::response([], 500)]);
        // Gemini "lupa" memberi peringatan.
        Http::fake([self::GEMINI => $this->reply($this->good($labels, ['peringatan_kelengkapan' => null]))]);

        $response = $this->explain($token)->assertOk();

        $this->assertStringContainsString('belum lengkap', $response->json('explanation.peringatan_kelengkapan'));
        $this->assertStringContainsString('Google Safe Browsing', $response->json('explanation.peringatan_kelengkapan'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'generativelanguage')
            && str_contains($this->sentText($r), '"status":"sebagian"')
            && str_contains($this->sentText($r), 'Google Safe Browsing'));
    }

    public function test_tag_html_dari_jawaban_dibersihkan(): void
    {
        [, $token, $labels] = $this->phishingCheck();
        Http::fake([self::GEMINI => $this->reply($this->good($labels, ['ringkasan' => 'Hati-hati <script>alert(1)</script> dengan tautan ini.']))]);

        $summary = $this->explain($token)->assertOk()->json('explanation.ringkasan');

        $this->assertStringNotContainsString('<', $summary);
    }
}
