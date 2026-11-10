<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuizRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $sessionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startSession();
        $this->sessionId = session()->getId();
        $this->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class);
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $cookies[session()->getName()] = $this->sessionId;

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /** Pindah ke "browser lain" (sesi baru tanpa progres). */
    private function newBrowser(): void
    {
        $this->sessionId = Str::random(40);
    }

    private function round(int $level, int $correct): array
    {
        $answers = [];
        for ($i = 1; $i <= 10; $i++) {
            $ok = $i <= $correct;
            $answers[] = ['question_id' => sprintf('%d-%02d', $level, $i), 'selected' => $ok ? 1 : 2, 'answer' => 1, 'correct' => $ok, 'topic' => 'Uji'];
        }

        return ['level' => $level, 'score' => $correct, 'total_questions' => 10, 'correct_count' => $correct, 'quiz_date' => '2026-11-09', 'answers' => $answers];
    }

    private function play(int $level, int $correct): void
    {
        $this->postJson(route('jeda.api.quiz-attempt'), $this->round($level, $correct))->assertOk();
    }

    private function createCode(): string
    {
        return $this->postJson(route('jeda.api.quiz-recovery-create'))->assertOk()->json('code');
    }

    private function exp(): int
    {
        return (int) $this->getJson(route('jeda.api.quiz-progress'))->json('xp.xp');
    }

    public function test_cannot_create_code_without_any_progress(): void
    {
        $this->postJson(route('jeda.api.quiz-recovery-create'))->assertStatus(422);
        $this->assertSame(0, DB::table('jeda_recovery_codes')->count());
    }

    public function test_creates_random_code_and_stores_only_its_hash(): void
    {
        $this->play(1, 10);

        $response = $this->postJson(route('jeda.api.quiz-recovery-create'))->assertOk();
        $code = $response->json('code');

        $this->assertMatchesRegularExpression('/^JEDA-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(1, DB::table('jeda_recovery_codes')->count());
        $stored = json_encode(DB::table('jeda_recovery_codes')->first());
        $this->assertStringNotContainsString($code, $stored);
        $this->assertStringNotContainsString(str_replace('-', '', $code), $stored);
    }

    public function test_progress_reports_whether_a_code_exists_without_revealing_it(): void
    {
        $this->play(1, 10);
        $this->getJson(route('jeda.api.quiz-progress'))->assertJsonPath('recovery.has_code', false);

        $code = $this->createCode();

        $response = $this->getJson(route('jeda.api.quiz-progress'))->assertJsonPath('recovery.has_code', true);
        $this->assertStringNotContainsString($code, $response->getContent());
    }

    public function test_redeeming_in_a_new_browser_restores_exp_and_level(): void
    {
        $this->play(1, 10);
        $this->play(1, 10); // 140 EXP -> Level 2
        $code = $this->createCode();

        $this->newBrowser();
        $this->assertSame(0, $this->exp());

        $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => $code])
            ->assertOk()
            ->assertJsonPath('xp.xp', 140)
            ->assertJsonPath('xp.level', 2)
            ->assertJsonPath('already', false)
            ->assertJsonPath('restored_attempts', 2)
            ->assertJsonPath('levels.1.attempts', 2);

        $this->assertSame(140, $this->exp());
    }

    public function test_redeem_accepts_lowercase_and_missing_dashes(): void
    {
        $this->play(1, 10);
        $code = $this->createCode();

        $this->newBrowser();
        $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => strtolower(str_replace('-', ' ', $code))])
            ->assertOk()
            ->assertJsonPath('xp.xp', 70);
    }

    public function test_redeem_merges_with_progress_already_in_the_new_browser(): void
    {
        $this->play(1, 10);                 // 70 EXP di browser lama
        $code = $this->createCode();

        $this->newBrowser();
        $this->play(1, 5);                  // 30 EXP di browser baru
        $this->assertSame(30, $this->exp());

        $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => $code])
            ->assertOk()
            ->assertJsonPath('xp.xp', 100)
            ->assertJsonPath('xp.level', 2);
    }

    public function test_unknown_and_malformed_codes_are_rejected_with_the_same_message(): void
    {
        $this->play(1, 10);
        $this->createCode();
        $this->newBrowser();

        $unknown = $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => 'JEDA-AAAA-BBBB-CCCC'])->assertStatus(422);
        $malformed = $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => 'bukan-kode'])->assertStatus(422);

        $this->assertSame($unknown->json('message'), $malformed->json('message'));
        $this->assertSame(0, $this->exp());
    }

    public function test_redeeming_your_own_code_changes_nothing(): void
    {
        $this->play(1, 10);
        $code = $this->createCode();

        $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => $code])
            ->assertOk()
            ->assertJsonPath('already', true)
            ->assertJsonPath('xp.xp', 70);
        $this->assertSame(70, $this->exp());
    }

    public function test_code_follows_the_latest_holder_and_stays_reusable(): void
    {
        $this->play(1, 10);
        $code = $this->createCode();

        $this->newBrowser();
        $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => $code])->assertOk();
        $this->play(1, 10);                 // lanjut main di browser B: 140 EXP

        $this->newBrowser();                // browser C memakai kode yang sama
        $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => $code])
            ->assertOk()
            ->assertJsonPath('xp.xp', 140);
    }

    public function test_creating_a_new_code_invalidates_the_old_one(): void
    {
        $this->play(1, 10);
        $old = $this->createCode();
        $new = $this->createCode();

        $this->assertNotSame($old, $new);
        $this->assertSame(1, DB::table('jeda_recovery_codes')->count());

        $this->newBrowser();
        $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => $old])->assertStatus(422);
        $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => $new])->assertOk();
    }

    public function test_redeem_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => 'JEDA-AAAA-BBBB-CCCC'])->assertStatus(422);
        }

        $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => 'JEDA-AAAA-BBBB-CCCC'])->assertStatus(429);
    }

    public function test_quiz_rounds_do_not_use_up_the_recovery_rate_limit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->play(1, 5);
        }
        $this->postJson(route('jeda.api.quiz-recovery-redeem'), ['code' => 'JEDA-AAAA-BBBB-CCCC'])->assertStatus(422);
    }
}
