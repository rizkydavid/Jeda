<?php

namespace App\Services\Cek;

/**
 * Mesin skor risiko 0 sampai 10.
 *
 *  0      = tidak ditemukan indikator (BUKAN jaminan aman)
 *  1 - 3  = risiko rendah
 *  4 - 6  = risiko sedang
 *  7 - 10 = risiko tinggi
 *
 * Aturan penting:
 *  - id temuan yang sama hanya dihitung sekali (tidak ada skor ganda);
 *  - tanda lemah (kata "login", dll.) hanya dihitung jika ada tanda lain;
 *  - tiap kelompok temuan punya batas bobot (config jeda_cek.group_caps);
 *  - tanda dari 3 kelompok berbeda atau lebih memberi tambahan kecil (kombinasi);
 *  - temuan terkonfirmasi (mis. Safe Browsing) memaksa skor minimal sesuai "floor";
 *  - KELENGKAPAN pemeriksaan dilaporkan terpisah dari skor. API gagal bukan berarti aman.
 */
final class RiskScorer
{
    /** Pemeriksaan yang harus berhasil agar hasil dianggap lengkap. */
    public const CRITICAL_CHECKS = ['safe_browsing', 'virustotal', 'domain_age', 'redirect'];

    /** Status pemeriksaan yang berarti "belum berhasil dilakukan". */
    public const INCOMPLETE_STATES = ['unavailable', 'not_configured', 'pending', 'blocked'];

    /**
     * @param  array<int,Signal>  $signals
     * @param  array<string,array{label:string,state:string,note?:?string}>  $checks
     * @return array{score:int,level:string,signals:array<int,array>,confirmed:bool,
     *   coverage:array{status:string,missing:array<int,array{label:string,note:?string}>},
     *   message:string,coverage_note:?string}
     */
    public function evaluate(array $signals, array $checks): array
    {
        // 1) Satu id hanya sekali (ambil yang bobotnya terbesar).
        $unique = [];
        foreach ($signals as $signal) {
            if (! isset($unique[$signal->id]) || $signal->points > $unique[$signal->id]->points) {
                $unique[$signal->id] = $signal;
            }
        }

        // 2) Tanda lemah hanya dihitung jika ada tanda lain yang bernilai.
        $hasStrong = false;
        foreach ($unique as $signal) {
            if (! $signal->needsOther && $signal->points > 0) {
                $hasStrong = true;
                break;
            }
        }
        $adjusted = array_map(
            fn (Signal $s) => ($s->needsOther && ! $hasStrong) ? $s->withPoints(0) : $s,
            $unique
        );

        // 3) Jumlah per kelompok dengan batas bobot.
        $caps = config('jeda_cek.group_caps', []);
        $byGroup = [];
        foreach ($adjusted as $signal) {
            $byGroup[$signal->group] = ($byGroup[$signal->group] ?? 0) + $signal->points;
        }
        $sum = 0;
        $activeGroups = 0;
        foreach ($byGroup as $group => $points) {
            if ($points <= 0) {
                continue;
            }
            $activeGroups++;
            $sum += min($points, $caps[$group] ?? 10);
        }

        // 4) Kombinasi beberapa kelompok tanda.
        $final = array_values($adjusted);
        if ($activeGroups >= 3) {
            $final[] = new Signal('combo', 'combo', 'Beberapa jenis tanda muncul bersamaan',
                'Tanda dari beberapa kelompok berbeda muncul sekaligus, yang lebih mencurigakan daripada satu tanda saja.', 1);
            $sum += 1;
        }

        // 5) Temuan terkonfirmasi memaksa skor minimal.
        $floor = 0;
        foreach ($unique as $signal) {
            $floor = max($floor, $signal->floor);
        }
        $score = min(10, max($sum, $floor));
        $level = self::levelFor($score);
        $coverage = $this->coverage($checks);

        usort($final, fn (Signal $a, Signal $b) => $b->points <=> $a->points);

        return [
            'score' => $score,
            'level' => $level,
            'signals' => array_map(fn (Signal $s) => $s->toArray(), $final),
            'confirmed' => $floor >= 9,
            'coverage' => $coverage,
            'message' => self::adviceFor($level),
            'coverage_note' => $coverage['status'] === 'lengkap' ? null : $this->coverageNote($coverage),
        ];
    }

    public static function levelFor(int $score): string
    {
        return match (true) {
            $score >= 7 => 'Tinggi',
            $score >= 4 => 'Sedang',
            $score >= 1 => 'Rendah',
            default => 'Belum ada indikator',
        };
    }

    public static function adviceFor(string $level): string
    {
        return match ($level) {
            'Tinggi' => 'Sebaiknya jangan buka tautan ini. Kalau sudah terlanjur dibuka, jangan isi data apa pun dan lihat langkah di halaman Jam Emas.',
            'Sedang' => 'Ada beberapa tanda yang perlu diwaspadai. Pastikan kamu kenal pengirimnya, dan buka situs resminya lewat aplikasi atau pencarian sendiri.',
            'Rendah' => 'Ada tanda kecil yang layak dicek ulang sebelum kamu lanjut.',
            default => 'Belum ada indikator dari pemeriksaan yang berhasil. Itu bukan jaminan tautan ini aman.',
        };
    }

    /**
     * Status kelengkapan, terpisah dari skor.
     *  lengkap = semua pemeriksaan utama berhasil;
     *  sebagian = sebagian gagal atau belum selesai;
     *  terbatas = tidak ada satu pun pemeriksaan utama yang berhasil (hanya pola alamat).
     */
    public function coverage(array $checks): array
    {
        $missing = [];
        $done = 0;
        foreach (self::CRITICAL_CHECKS as $key) {
            $check = $checks[$key] ?? null;
            if ($check === null) {
                continue;
            }
            if (in_array($check['state'], self::INCOMPLETE_STATES, true)) {
                $missing[] = ['label' => $check['label'], 'note' => $check['note'] ?? null];
            } else {
                $done++;
            }
        }

        $status = $missing === [] ? 'lengkap' : ($done === 0 ? 'terbatas' : 'sebagian');

        return ['status' => $status, 'missing' => $missing];
    }

    private function coverageNote(array $coverage): string
    {
        $labels = implode(', ', array_column($coverage['missing'], 'label'));

        return "Pemeriksaan belum lengkap ($labels). Hasil di atas hanya berdasarkan pemeriksaan yang berhasil, jadi jangan dianggap aman.";
    }
}
