<?php

namespace App\Support;

/**
 * Aturan level dan EXP kuis Latihan. Murni perhitungan (tanpa database)
 * supaya mudah diuji. Angka-angkanya dibaca dari config/jeda_quiz.php.
 */
final class QuizProgression
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
    }

    public static function fromConfig(): self
    {
        return new self((array) config('jeda_quiz'));
    }

    public function maxLevel(): int
    {
        return max(array_keys($this->config['levels']));
    }

    public function questionsPerRound(): int
    {
        return (int) $this->config['questions_per_round'];
    }

    /** EXP untuk satu jawaban benar pada level tertentu. */
    public function xpPerCorrect(int $level): int
    {
        $level = max(1, min($level, $this->maxLevel()));

        return (int) $this->config['xp_per_correct_base'] + ($level - 1) * (int) $this->config['xp_per_correct_step'];
    }

    /**
     * Rincian EXP satu ronde.
     *
     * @return array{per_correct:int, correct:int, correct_xp:int, perfect_bonus:int, total:int}
     */
    public function roundBreakdown(int $level, int $correct, int $total): array
    {
        $per = $this->xpPerCorrect($level);
        $correctXp = $correct * $per;
        $bonus = ($total > 0 && $correct === $total) ? (int) $this->config['perfect_bonus'] : 0;

        return [
            'per_correct' => $per,
            'correct' => $correct,
            'correct_xp' => $correctXp,
            'perfect_bonus' => $bonus,
            'total' => $correctXp + $bonus,
        ];
    }

    public function roundXp(int $level, int $correct, int $total): int
    {
        return $this->roundBreakdown($level, $correct, $total)['total'];
    }

    /** Level yang dicapai dengan total EXP tertentu. */
    public function levelForXp(int $xp): int
    {
        $level = 1;
        foreach ($this->config['levels'] as $id => $info) {
            if ($xp >= (int) $info['xp_required']) {
                $level = (int) $id;
            }
        }

        return $level;
    }

    /**
     * Ringkasan posisi pemain untuk ditampilkan di UI.
     *
     * @return array{xp:int, level:int, max_level:int, is_max:bool, level_start:int, next_level_xp:?int, xp_into_level:int, xp_needed:int, percent:int}
     */
    public function summary(int $xp): array
    {
        $xp = max(0, $xp);
        $level = $this->levelForXp($xp);
        $isMax = $level >= $this->maxLevel();
        $start = (int) $this->config['levels'][$level]['xp_required'];
        $next = $isMax ? null : (int) $this->config['levels'][$level + 1]['xp_required'];
        $span = $next === null ? 0 : $next - $start;
        $into = $xp - $start;

        return [
            'xp' => $xp,
            'level' => $level,
            'max_level' => $this->maxLevel(),
            'is_max' => $isMax,
            'level_start' => $start,
            'next_level_xp' => $next,
            'xp_into_level' => $into,
            'xp_needed' => $next === null ? 0 : $next - $xp,
            'percent' => $isMax ? 100 : (int) floor($into / max(1, $span) * 100),
        ];
    }
}
