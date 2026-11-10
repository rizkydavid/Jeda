<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Membaca progres kuis (EXP, level, statistik per level) milik satu kunci sesi. */
final class QuizProgressStore
{
    public const TABLE = 'jeda_quiz_attempts';

    public function __construct(private QuizProgression $progression)
    {
    }

    public static function make(): self
    {
        return new self(QuizProgression::fromConfig());
    }

    public function totalExp(string $key): int
    {
        return (int) DB::table(self::TABLE)->where('session_key', $key)->sum('exp_earned');
    }

    public function hasAttempts(string $key): bool
    {
        return DB::table(self::TABLE)->where('session_key', $key)->exists();
    }

    /** @return array<string, mixed> */
    public function payload(string $key, string $date): array
    {
        $rows = DB::table(self::TABLE)->where('session_key', $key)->orderByDesc('id')->get(['level', 'score', 'quiz_date', 'exp_earned']);

        $levels = [];
        foreach ($rows->groupBy('level') as $level => $attempts) {
            $levels[(int) $level] = [
                'best_score' => (int) $attempts->max('score') * 10,
                'latest_score' => (int) $attempts->first()->score * 10,
                'attempts' => $attempts->count(),
                'rounds_today' => $attempts->where('quiz_date', $date)->count(),
            ];
        }

        return [
            'ok' => true,
            'xp' => $this->progression->summary((int) $rows->sum('exp_earned')),
            'levels' => $levels,
            'recovery' => $this->recoveryInfo($key),
        ];
    }

    /** @return array{has_code: bool, created_at: ?string} */
    private function recoveryInfo(string $key): array
    {
        try {
            $row = DB::table('jeda_recovery_codes')->where('session_key', $key)->orderByDesc('id')->first(['created_at']);
        } catch (\Throwable) {
            $row = null; // tabel belum dimigrasi: progres kuis tetap berjalan
        }

        return ['has_code' => $row !== null, 'created_at' => $row?->created_at ? (string) $row->created_at : null];
    }
}
