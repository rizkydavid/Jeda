<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LatihanQuizTest extends TestCase
{
    use RefreshDatabase;

    private string $sessionId;

    protected function setUp(): void
    {
        parent::setUp();
        // Satu sesi tamu yang sama untuk semua request di tes ini.
        $this->startSession();
        $this->sessionId = session()->getId();
        $this->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class);
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        // Request JSON tidak membawa cookie bawaan, jadi cookie sesi disisipkan manual.
        $cookies[session()->getName()] = $this->sessionId;

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /** Payload ronde 10 soal dengan $correct jawaban benar. */
    private function round(int $level, int $correct): array
    {
        $answers = [];
        for ($i = 1; $i <= 10; $i++) {
            $isCorrect = $i <= $correct;
            $answers[] = [
                'question_id' => sprintf('%d-%02d', $level, $i),
                'selected' => $isCorrect ? 1 : 2,
                'answer' => 1,
                'correct' => $isCorrect,
                'topic' => 'Uji',
            ];
        }

        return [
            'level' => $level, 'score' => $correct, 'total_questions' => 10, 'correct_count' => $correct,
            'quiz_date' => '2026-11-05', 'answers' => $answers,
        ];
    }

    public function test_new_player_starts_at_level_one_with_no_xp(): void
    {
        $this->getJson(route('jeda.api.quiz-progress'))
            ->assertOk()
            ->assertJsonPath('xp.level', 1)
            ->assertJsonPath('xp.xp', 0)
            ->assertJsonPath('xp.next_level_xp', 100);
    }

    public function test_perfect_round_gives_xp_but_does_not_level_up_immediately(): void
    {
        $this->postJson(route('jeda.api.quiz-attempt'), $this->round(1, 10))
            ->assertOk()
            ->assertJsonPath('xp_earned', 70)
            ->assertJsonPath('xp.xp', 70)
            ->assertJsonPath('xp.level', 1)
            ->assertJsonPath('leveled_up', false)
            ->assertJsonPath('xp_breakdown.perfect_bonus', 10);
    }

    public function test_xp_accumulates_across_rounds_until_level_up(): void
    {
        $this->postJson(route('jeda.api.quiz-attempt'), $this->round(1, 10))->assertOk();

        $this->postJson(route('jeda.api.quiz-attempt'), $this->round(1, 10))
            ->assertOk()
            ->assertJsonPath('xp.xp', 140)
            ->assertJsonPath('xp.level', 2)
            ->assertJsonPath('leveled_up', true);
    }

    public function test_level_unlocks_by_xp_even_when_scores_are_below_70_percent(): void
    {
        // Aturan lama (nilai minimal 70%) sudah dihapus: 3 ronde 6/10 = 108 EXP membuka Level 2.
        foreach (range(1, 3) as $_) {
            $this->postJson(route('jeda.api.quiz-attempt'), $this->round(1, 6))->assertOk();
        }

        $this->getJson(route('jeda.api.quiz-progress'))
            ->assertJsonPath('xp.xp', 108)
            ->assertJsonPath('xp.level', 2)
            ->assertJsonPath('levels.1.best_score', 60);

        $this->postJson(route('jeda.api.quiz-attempt'), $this->round(2, 5))->assertOk();
    }

    public function test_locked_level_is_rejected(): void
    {
        $this->postJson(route('jeda.api.quiz-attempt'), $this->round(2, 10))
            ->assertStatus(403);
        $this->postJson(route('jeda.api.quiz-attempt'), $this->round(10, 10))
            ->assertStatus(403);
    }

    public function test_higher_levels_give_more_xp_per_correct_answer(): void
    {
        // Ronde sempurna Level 1 (70 EXP) dua kali, lalu Level 2 (90 EXP) dua kali.
        foreach ([1, 1, 2, 2] as $level) {
            $this->postJson(route('jeda.api.quiz-attempt'), $this->round($level, 10))->assertOk();
        }
        // 70 + 70 + 90 + 90 = 320 → Level 3 (butuh 250).
        $this->getJson(route('jeda.api.quiz-progress'))->assertJsonPath('xp.level', 3)->assertJsonPath('xp.xp', 320);
    }

    public function test_level_above_ten_is_invalid(): void
    {
        $this->postJson(route('jeda.api.quiz-attempt'), $this->round(11, 5))->assertStatus(422);
    }

    public function test_tampered_correct_flags_are_rejected(): void
    {
        $payload = $this->round(1, 5);
        $payload['answers'][9]['correct'] = true; // ditandai benar padahal selected != answer

        $this->postJson(route('jeda.api.quiz-attempt'), $payload)->assertStatus(422);
        $this->getJson(route('jeda.api.quiz-progress'))->assertJsonPath('xp.xp', 0);
    }

    public function test_question_ids_must_belong_to_the_chosen_level(): void
    {
        $payload = $this->round(1, 10);
        $payload['answers'][0]['question_id'] = '7-01';

        $this->postJson(route('jeda.api.quiz-attempt'), $payload)->assertStatus(422);
    }

    public function test_client_cannot_inject_xp(): void
    {
        $payload = $this->round(1, 0) + ['xp_earned' => 99999];

        $this->postJson(route('jeda.api.quiz-attempt'), $payload)
            ->assertOk()
            ->assertJsonPath('xp_earned', 0);
    }

    public function test_progress_reports_rounds_played_today_for_question_rotation(): void
    {
        $this->postJson(route('jeda.api.quiz-attempt'), $this->round(1, 4))->assertOk();
        $this->postJson(route('jeda.api.quiz-attempt'), $this->round(1, 8))->assertOk();

        $this->getJson(route('jeda.api.quiz-progress', ['date' => '2026-11-05']))
            ->assertJsonPath('levels.1.rounds_today', 2)
            ->assertJsonPath('levels.1.best_score', 80)
            ->assertJsonPath('levels.1.latest_score', 80)
            ->assertJsonPath('levels.1.attempts', 2);

        $this->getJson(route('jeda.api.quiz-progress', ['date' => '2026-11-06']))
            ->assertJsonPath('levels.1.rounds_today', 0);
    }

    public function test_quiz_page_renders_with_new_assets(): void
    {
        $this->get(route('simulasi'))
            ->assertOk()
            ->assertSee('css/latihan.css', false)
            ->assertSee('js/latihan.js', false)
            ->assertSee('js/latihan-data.js', false)
            ->assertSee('Puncak Kedamaian');
    }
}
