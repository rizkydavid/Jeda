<?php

namespace App\Services\Cek;

final class BedahScorer
{
    public const WEIGHTS = [
        'urgensi' => 15, 'kerahasiaan' => 15, 'uang' => 20, 'kode' => 30, 'iming' => 20,
        'pihak_resmi' => 15, 'ancaman' => 20, 'nomor_baru' => 10, 'tautan' => 10,
        'tautan_menyamar' => 25, 'unduh_aplikasi' => 25,
    ];

    public const AKSI = ['uang', 'kode', 'unduh_aplikasi', 'tautan_menyamar'];

    private const RANTAI_BONUS = 25;
    private const COMBO_MIN = 3;
    private const COMBO_BONUS = 15;
    private const GATED_MAX_SCORE = 10;

    public const TITLES = [
        'urgensi' => 'Tekanan waktu',
        'kerahasiaan' => 'Disuruh merahasiakan',
        'uang' => 'Diminta mengirim uang',
        'kode' => 'Diminta kode atau OTP',
        'iming' => 'Iming-iming tidak wajar',
        'pihak_resmi' => 'Mengaku pihak resmi',
        'ancaman' => 'Ancaman',
        'nomor_baru' => 'Mengaku nomor baru',
        'tautan' => 'Ada tautan',
        'tautan_menyamar' => 'Tautan menyamar',
        'unduh_aplikasi' => 'Diminta mengunduh atau memasang aplikasi',
        'rantai_jebakan' => 'Imbalan dipasangkan dengan permintaan aksi',
        'kombinasi' => 'Beberapa tanda sekaligus',
    ];

    private const DERIVED_NOTES = [
        'rantai_jebakan' => 'Pola klasik: dijanjikan bayaran atau hadiah, lalu diminta mengunduh, mengirim, atau membuka sesuatu.',
        'kombinasi' => 'Penipuan biasanya memakai beberapa tekanan sekaligus supaya kamu tidak sempat berpikir.',
    ];

    public static function allIds(): array
    {
        return array_merge(array_keys(self::WEIGHTS), array_keys(self::DERIVED_NOTES));
    }

    public static function evaluate(array $ids): array
    {
        $ids = array_values(array_unique(array_intersect($ids, array_keys(self::WEIGHTS))));
        $score = (int) array_sum(array_map(fn ($id) => self::WEIGHTS[$id], $ids));

        $aksi = array_values(array_intersect($ids, self::AKSI));
        $pendukung = array_values(array_diff($ids, $aksi));

        if ($ids !== [] && $aksi === [] && count($pendukung) < 2) {
            return [
                'score' => min($score, self::GATED_MAX_SCORE),
                'level' => 'Belum cukup konteks',
                'gated' => true,
                'ids' => $ids,
                'derived' => [],
            ];
        }

        $derived = [];
        if (in_array('iming', $ids, true) && $aksi !== []) {
            $score += self::RANTAI_BONUS;
            $derived[] = 'rantai_jebakan';
        }
        if (count($ids) >= self::COMBO_MIN) {
            $score += self::COMBO_BONUS;
            $derived[] = 'kombinasi';
        }
        $score = min(100, $score);

        return [
            'score' => $score,
            'level' => self::levelFor($score),
            'gated' => false,
            'ids' => $ids,
            'derived' => array_map(fn ($id) => [
                'id' => $id, 'judul' => self::TITLES[$id], 'penjelasan' => self::DERIVED_NOTES[$id],
            ], $derived),
        ];
    }

    public static function levelFor(int $score): string
    {
        return $score === 0 ? 'Belum terdeteksi' : ($score >= 65 ? 'Tinggi' : ($score >= 30 ? 'Sedang' : 'Perlu diperiksa'));
    }
}