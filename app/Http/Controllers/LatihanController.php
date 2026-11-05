<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasSessionKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Modul Latihan: simulasi percakapan dan kuis berlevel. */
class LatihanController extends Controller
{
    use HasSessionKey;

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

    public function quizProgress(Request $request): JsonResponse
    {
        $key = $this->sessionKey($request);
        $levels = [];
        for ($level = 1; $level <= 3; $level++) {
            $best = DB::table('jeda_quiz_attempts')->where('session_key', $key)->where('level', $level)->max('score');
            $latest = DB::table('jeda_quiz_attempts')->where('session_key', $key)->where('level', $level)->orderByDesc('created_at')->value('score');
            $levels[$level] = $best === null ? null : [
                'best_score' => (int) round(((int) $best) * 10),
                'latest_score' => (int) round(((int) ($latest ?? 0)) * 10),
                'attempts' => DB::table('jeda_quiz_attempts')->where('session_key', $key)->where('level', $level)->count(),
            ];
        }
        return response()->json(['ok' => true, 'levels' => $levels]);
    }

    public function saveQuizAttempt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'integer', 'between:1,3'],
            'score' => ['required', 'integer', 'between:0,10'],
            'total_questions' => ['required', 'integer', 'in:10'],
            'correct_count' => ['required', 'integer', 'between:0,10'],
            'quiz_date' => ['required', 'date_format:Y-m-d'],
            'answers' => ['required', 'array', 'size:10'],
            'answers.*.question_id' => ['required', 'string', 'max:20'],
            'answers.*.selected' => ['required', 'integer', 'between:0,3'],
            'answers.*.correct' => ['required', 'boolean'],
            'answers.*.answer' => ['required', 'integer', 'between:0,3'],
            'answers.*.topic' => ['required', 'string', 'max:100'],
        ]);

        // Level berikutnya hanya bisa dibuka setelah level sebelumnya mencapai 70%.
        if ((int) $data['level'] > 1) {
            $previousBest = DB::table('jeda_quiz_attempts')
                ->where('session_key', $this->sessionKey($request))
                ->where('level', (int) $data['level'] - 1)
                ->max('score');
            if ($previousBest === null || (int) $previousBest < 7) {
                return response()->json(['ok' => false, 'message' => 'Selesaikan level sebelumnya dengan nilai minimal 70% terlebih dahulu.'], 403);
            }
        }

        $correct = collect($data['answers'])->filter(fn ($answer) => (bool) $answer['correct'])->count();
        $score = (int) $data['score'];
        if ($correct !== (int) $data['correct_count'] || $score !== $correct) {
            return response()->json(['ok' => false, 'message' => 'Data nilai tidak konsisten. Silakan ulangi latihan.'], 422);
        }

        DB::table('jeda_quiz_attempts')->insert([
            'session_key' => $this->sessionKey($request),
            'level' => (int) $data['level'],
            'quiz_date' => $data['quiz_date'],
            'score' => $score,
            'total_questions' => (int) $data['total_questions'],
            'correct_count' => $correct,
            'answers' => json_encode($data['answers'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levels = [];
        for ($level = 1; $level <= 3; $level++) {
            $best = DB::table('jeda_quiz_attempts')->where('session_key', $this->sessionKey($request))->where('level', $level)->max('score');
            $levels[$level] = $best === null ? null : ['best_score' => (int) $best * 10];
        }
        return response()->json(['ok' => true, 'saved' => true, 'levels' => $levels]);
    }
}
