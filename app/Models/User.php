<?php

namespace App\Models;

use App\Support\QuizProgression;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'exp',
        'level',
    ];

    /**
     * Status level pengguna dari EXP-nya. Aturan level ada di config/jeda_quiz.php
     * (satu sumber yang sama dengan halaman Latihan).
     */
    public function getLevelDataInfo(): array
    {
        $summary = QuizProgression::fromConfig()->summary((int) ($this->exp ?? 0));
        $title = config("jeda_quiz.levels.{$summary['level']}.name");

        return [
            'level'            => $summary['level'],
            'title'            => $title,
            'exp'              => $summary['xp'],
            'min_exp'          => $summary['level_start'],
            'max_exp'          => $summary['next_level_xp'],
            'progress_percent' => $summary['percent'],
            'exp_to_next'      => $summary['xp_needed'],
        ];
    }
}
