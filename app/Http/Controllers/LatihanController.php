<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasSessionKey;
use App\Support\QuizProgression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Modul Latihan: simulasi percakapan dan kuis berlevel dengan sistem EXP. */
class LatihanController extends Controller
{
    use HasSessionKey;

    private const TABLE = 'jeda_quiz_attempts';

    public function saveSimulation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scenario' => ['nullable', 'string', 'max:80'],
            'option' => ['required', 'integer', 'between:0,2'],
        ]);
        $option = (int) $data['option'];
        $strong = $option === 1;
        DB::table('jeda_simulation_attempts')->insert([
            'session_key' => $this->sessionKey($request), 'scenario' => $data['scenario'] ?? 'rekruter-1',
            'selected_option' => $option, 'strong_choice' => $strong, 'score' => $strong ? 1 : 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'strong_choice' => $strong, 'score' => $strong ? 1 : 0]);
    }

    /** Posisi EXP/level pemain dan statistik tiap level. */
    public function quizProgress(Request $request): JsonResponse
    {
        $date = $this->quizDate($request->query('date'));

        return response()->json($this->progressPayload($this->sessionKey($request), $date));
    }

    public function saveQuizAttempt(Request $request): JsonResponse
    {
        $progression = QuizProgression::fromConfig();
        $perRound = $progression->questionsPerRound();
        $max = $progression->maxLevel();

        $data = $request->validate([
            'level' => ['required', 'integer', "between:1,{$max}"],
            'score' => ['required', 'integer', "between:0,{$perRound}"],
            'total_questions' => ['required', 'integer', "in:{$perRound}"],
            'correct_count' => ['required', 'integer', "between:0,{$perRound}"],
            'quiz_date' => ['required', 'date_format:Y-m-d'],
            'answers' => ['required', 'array', "size:{$perRound}"],
            'answers.*.question_id' => ['required', 'string', 'max:20', 'regex:/^\d{1,2}-\d{2}$/', 'distinct'],
            'answers.*.selected' => ['required', 'integer', 'between:0,3'],
            'answers.*.correct' => ['required', 'boolean'],
            'answers.*.answer' => ['required', 'integer', 'between:0,3'],
            'answers.*.topic' => ['required', 'string', 'max:100'],
        ]);

        $key = $this->sessionKey($request);
        $level = (int) $data['level'];

        // Level dibuka oleh total EXP, bukan oleh nilai ronde sebelumnya.
        $before = $progression->summary($this->totalXp($key));
        if ($level > $before['level']) {
            return response()->json(['ok' => false, 'message' => 'Level ini belum terbuka. Kumpulkan EXP dari level yang sudah terbuka dulu.'], 403);
        }

        $answers = collect($data['answers']);
        $correct = $answers->filter(fn ($a) => (bool) $a['correct'])->count();
        $consistent = $answers->every(fn ($a) => (bool) $a['correct'] === ((int) $a['selected'] === (int) $a['answer']))
            && $answers->every(fn ($a) => str_starts_with((string) $a['question_id'], $level.'-'));
        if (! $consistent || $correct !== (int) $data['correct_count'] || (int) $data['score'] !== $correct) {
            return response()->json(['ok' => false, 'message' => 'Data nilai tidak konsisten. Silakan ulangi latihan.'], 422);
        }

        // EXP dihitung di server agar tidak bisa diubah dari browser.
        $breakdown = $progression->roundBreakdown($level, $correct, $perRound);

        DB::table(self::TABLE)->insert([
            'session_key' => $key,
            'level' => $level,
            'quiz_date' => $data['quiz_date'],
            'score' => $correct,
            'total_questions' => $perRound,
            'correct_count' => $correct,
            'exp_earned' => $breakdown['total'],
            'answers' => json_encode($data['answers'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = $this->progressPayload($key, $data['quiz_date']);

        return response()->json($payload + [
            'saved' => true,
            'xp_earned' => $breakdown['total'],
            'xp_breakdown' => $breakdown,
            'xp_before' => $before,
            'leveled_up' => $payload['xp']['level'] > $before['level'],
        ]);
    }

    /** @return array<string, mixed> */
    private function progressPayload(string $key, string $date): array
    {
        $progression = QuizProgression::fromConfig();
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
            'xp' => $progression->summary((int) $rows->sum('exp_earned')),
            'levels' => $levels,
        ];
    }

    private function totalXp(string $key): int
    {
        return (int) DB::table(self::TABLE)->where('session_key', $key)->sum('exp_earned');
    }

    private function quizDate(mixed $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : now()->toDateString();
    }
}
