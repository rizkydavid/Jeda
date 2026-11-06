<?php

namespace Tests\Feature\Cek;

use App\Services\Cek\RiskScorer;
use App\Services\Cek\Signal;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RiskScorerTest extends TestCase
{
    private function scorer(): RiskScorer
    {
        return new RiskScorer;
    }

    private function sig(string $id, string $group, int $points, int $floor = 0, bool $weak = false): Signal
    {
        return new Signal($id, $group, $id, "detail $id", $points, $floor, $weak);
    }

    public static function ambang(): array
    {
        return [[0, 'Belum ada indikator'], [1, 'Rendah'], [3, 'Rendah'], [4, 'Sedang'], [6, 'Sedang'], [7, 'Tinggi'], [10, 'Tinggi']];
    }

    #[DataProvider('ambang')]
    public function test_ambang_tingkat_risiko(int $score, string $level): void
    {
        $this->assertSame($level, RiskScorer::levelFor($score));
    }

    public function test_id_yang_sama_hanya_dihitung_sekali(): void
    {
        $r = $this->scorer()->evaluate([$this->sig('x', 'url_pattern', 3), $this->sig('x', 'url_pattern', 3)], []);

        $this->assertSame(3, $r['score']);
        $this->assertCount(1, $r['signals']);
    }

    public function test_tanda_lemah_sendirian_tidak_dihitung(): void
    {
        $r = $this->scorer()->evaluate([$this->sig('bait', 'keyword', 1, 0, true)], []);

        $this->assertSame(0, $r['score']);
        $this->assertSame('Belum ada indikator', $r['level']);
    }

    public function test_tanda_lemah_dihitung_bersama_tanda_lain(): void
    {
        $r = $this->scorer()->evaluate([$this->sig('bait', 'keyword', 1, 0, true), $this->sig('ip', 'url_pattern', 3)], []);

        $this->assertSame(4, $r['score']);
        $this->assertSame('Sedang', $r['level']);
    }

    public function test_batas_bobot_per_kelompok(): void
    {
        $r = $this->scorer()->evaluate([
            $this->sig('a', 'url_pattern', 4), $this->sig('b', 'url_pattern', 4), $this->sig('c', 'url_pattern', 4),
        ], []);

        $this->assertSame(6, $r['score']); // batas kelompok url_pattern = 6
    }

    public function test_tiga_kelompok_berbeda_menambah_poin_kombinasi(): void
    {
        $r = $this->scorer()->evaluate([
            $this->sig('a', 'url_pattern', 1), $this->sig('b', 'page', 1), $this->sig('c', 'domain', 1),
        ], []);

        $this->assertSame(4, $r['score']); // 1 + 1 + 1 + kombinasi 1
        $this->assertContains('combo', array_column($r['signals'], 'id'));
    }

    public function test_temuan_terkonfirmasi_memaksa_risiko_tinggi(): void
    {
        $r = $this->scorer()->evaluate([$this->sig('sb', 'reputation', 10, 10)], []);

        $this->assertSame(10, $r['score']);
        $this->assertSame('Tinggi', $r['level']);
        $this->assertTrue($r['confirmed']);
    }

    public function test_skor_tidak_pernah_lebih_dari_sepuluh(): void
    {
        $r = $this->scorer()->evaluate([
            $this->sig('a', 'url_pattern', 6), $this->sig('b', 'page', 6), $this->sig('c', 'redirect', 3), $this->sig('d', 'domain', 2),
        ], []);

        $this->assertSame(10, $r['score']);
    }

    public function test_kelengkapan_terpisah_dari_skor(): void
    {
        $gagal = fn (string $label) => ['label' => $label, 'state' => 'unavailable', 'note' => 'gagal'];
        $r = $this->scorer()->evaluate([], [
            'safe_browsing' => $gagal('Google Safe Browsing'),
            'virustotal' => $gagal('VirusTotal'),
            'domain_age' => $gagal('Umur domain'),
            'redirect' => $gagal('Pengalihan'),
        ]);

        $this->assertSame(0, $r['score']);                       // skor 0 ...
        $this->assertSame('terbatas', $r['coverage']['status']);  // ... tetapi pemeriksaan tidak lengkap
        $this->assertStringContainsString('jangan dianggap aman', $r['coverage_note']);
    }

    public function test_status_kelengkapan_sebagian_dan_lengkap(): void
    {
        $ok = fn (string $label) => ['label' => $label, 'state' => 'ok', 'note' => null];
        $skip = fn (string $label) => ['label' => $label, 'state' => 'skipped', 'note' => null];

        $sebagian = $this->scorer()->coverage([
            'safe_browsing' => $ok('SB'), 'virustotal' => ['label' => 'VT', 'state' => 'pending', 'note' => null],
            'domain_age' => $ok('RDAP'), 'redirect' => $ok('Redirect'),
        ]);
        $this->assertSame('sebagian', $sebagian['status']);
        $this->assertSame('VT', $sebagian['missing'][0]['label']);

        $lengkap = $this->scorer()->coverage([
            'safe_browsing' => $ok('SB'), 'virustotal' => $ok('VT'), 'domain_age' => $skip('RDAP'), 'redirect' => $skip('Redirect'),
        ]);
        $this->assertSame('lengkap', $lengkap['status']);
    }
}
