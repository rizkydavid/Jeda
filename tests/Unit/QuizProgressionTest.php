<?php

namespace Tests\Unit;

use App\Support\QuizProgression;
use PHPUnit\Framework\TestCase;

class QuizProgressionTest extends TestCase
{
    private function progression(): QuizProgression
    {
        return new QuizProgression(require __DIR__.'/../../config/jeda_quiz.php');
    }

    public function test_has_ten_levels_with_increasing_xp_thresholds(): void
    {
        $config = require __DIR__.'/../../config/jeda_quiz.php';

        $this->assertSame(10, $this->progression()->maxLevel());
        $this->assertSame(0, $config['levels'][1]['xp_required']);

        $previous = -1;
        foreach ($config['levels'] as $level) {
            $this->assertGreaterThan($previous, $level['xp_required']);
            $previous = $level['xp_required'];
        }
    }

    public function test_xp_per_correct_answer_grows_with_level(): void
    {
        $p = $this->progression();

        $this->assertSame(6, $p->xpPerCorrect(1));
        $this->assertSame(8, $p->xpPerCorrect(2));
        $this->assertSame(24, $p->xpPerCorrect(10));
    }

    public function test_round_xp_includes_perfect_bonus_only_for_full_score(): void
    {
        $p = $this->progression();

        $this->assertSame(0, $p->roundXp(1, 0, 10));
        $this->assertSame(42, $p->roundXp(1, 7, 10));
        $this->assertSame(70, $p->roundXp(1, 10, 10));    // 60 + bonus 10
        $this->assertSame(250, $p->roundXp(10, 10, 10));  // 240 + bonus 10

        $breakdown = $p->roundBreakdown(3, 10, 10);
        $this->assertSame(10, $breakdown['per_correct']);
        $this->assertSame(100, $breakdown['correct_xp']);
        $this->assertSame(10, $breakdown['perfect_bonus']);
        $this->assertSame(110, $breakdown['total']);
    }

    public function test_level_is_derived_from_total_xp_at_exact_thresholds(): void
    {
        $p = $this->progression();

        $this->assertSame(1, $p->levelForXp(0));
        $this->assertSame(1, $p->levelForXp(99));
        $this->assertSame(2, $p->levelForXp(100));
        $this->assertSame(3, $p->levelForXp(250));
        $this->assertSame(9, $p->levelForXp(2699));
        $this->assertSame(10, $p->levelForXp(2700));
        $this->assertSame(10, $p->levelForXp(999999));
    }

    public function test_summary_reports_progress_toward_next_level(): void
    {
        $p = $this->progression();

        $s = $p->summary(175); // Level 2 (100..250)
        $this->assertSame(2, $s['level']);
        $this->assertSame(75, $s['xp_into_level']);
        $this->assertSame(75, $s['xp_needed']);
        $this->assertSame(250, $s['next_level_xp']);
        $this->assertSame(50, $s['percent']);
        $this->assertFalse($s['is_max']);

        $max = $p->summary(5000);
        $this->assertTrue($max['is_max']);
        $this->assertNull($max['next_level_xp']);
        $this->assertSame(100, $max['percent']);
    }
}
