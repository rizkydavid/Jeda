<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JedaFeatureController extends Controller
{
    private function sessionKey(Request $request): string
    {
        // Guest-first: data dipisahkan berdasarkan sesi browser, tanpa akun pengguna.
        return substr(hash('sha256', (string) $request->session()->getId()), 0, 64);
    }

    public function analyzeMessage(Request $request): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'min:1', 'max:12000']]);
        $text = $data['text'];
        $rules = [
            'Urgensi / batas waktu' => '/\b(sekarang|segera|dalam\s+\d+\s+menit|hari ini|terakhir|cepat|deadline)\b/i',
            'Kerahasiaan' => '/\b(jangan beri tahu|jangan bilang|rahasia|jangan ceritakan|diam saja)\b/i',
            'Permintaan uang' => '/\b(transfer|biaya admin|bayar|uang muka|top up|rekening)\b/i',
            'Permintaan kode / kredensial' => '/\b(otp|kode verifikasi|kata sandi|password|pin)\b/i',
            'Tautan' => '~https?://|www\.~i',
            'Iming-iming hadiah / pekerjaan' => '/\b(lolos|hadiah|bonus|menang|gaji besar|lowongan)\b/i',
        ];
        $indicators = [];
        foreach ($rules as $label => $pattern) {
            if (preg_match($pattern, $text)) $indicators[] = $label;
        }
        $score = min(5, count($indicators));
        $level = $score >= 4 ? 'Tinggi' : ($score >= 2 ? 'Sedang' : 'Perlu diperiksa');

        $id = DB::table('jeda_message_analyses')->insertGetId([
            'session_key' => $this->sessionKey($request),
            'risk_score' => $score,
            'risk_level' => $level,
            'indicators' => json_encode($indicators, JSON_UNESCAPED_UNICODE),
            'text_length' => mb_strlen($text),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true, 'id' => $id, 'score' => $score, 'level' => $level,
            'indicators' => $indicators,
            'message' => 'Pemeriksaan berbasis pola selesai. Hasil bukan vonis keamanan atau penipuan.',
        ]);
    }

    public function checkLink(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:2048']]);
        $raw = trim($data['url']);
        $candidate = preg_match('~^https?://~i', $raw) ? $raw : 'https://' . $raw;
        $parts = parse_url($candidate);
        if (!$parts || empty($parts['host']) || !preg_match('/^[a-z0-9.-]+$/i', $parts['host'])) {
            return response()->json(['ok' => false, 'message' => 'Alamat tautan belum valid.'], 422);
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        $indicators = [];
        if (preg_match('/(login|verify|verifikasi|secure|hadiah|claim|gift|bonus|bank|otp)/i', $host)) $indicators[] = 'Kata pancingan pada domain';
        if (filter_var($host, FILTER_VALIDATE_IP)) $indicators[] = 'Alamat menggunakan IP langsung';
        if (substr_count($host, '.') >= 3) $indicators[] = 'Subdomain bertingkat; periksa domain utamanya';
        if (strlen($host) > 45) $indicators[] = 'Nama domain panjang';
        $score = min(5, count($indicators));
        $level = $score >= 3 ? 'Tinggi' : ($score >= 1 ? 'Perlu diperiksa' : 'Belum ada pola umum');

        $id = DB::table('jeda_link_checks')->insertGetId([
            'session_key' => $this->sessionKey($request), 'hostname' => $host,
            'risk_score' => $score, 'risk_level' => $level,
            'indicators' => json_encode($indicators, JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id, 'hostname' => $host, 'score' => $score, 'level' => $level, 'indicators' => $indicators,
            'message' => 'Alamat tidak dibuka. Hasil ini bukan jaminan bahwa situs aman.']);
    }

    public function checkFile(Request $request): JsonResponse
    {
        $data = $request->validate(['filename' => ['required', 'string', 'max:255']]);
        $name = basename(trim($data['filename']));
        $parts = explode('.', strtolower($name));
        $extension = count($parts) > 1 ? end($parts) : '';
        $safeExt = ['pdf','jpg','jpeg','png','doc','docx','xlsx','txt'];
        $riskyExt = ['apk','exe','bat','cmd','msi','scr','vbs','js','ps1'];
        $double = count($parts) > 2 && in_array($parts[count($parts)-2], $safeExt, true) && in_array($extension, $riskyExt, true);
        $executable = in_array($extension, $riskyExt, true);
        $indicators = [];
        if ($double) $indicators[] = 'Ekstensi ganda';
        if ($executable) $indicators[] = 'Jenis file dapat menjalankan program';
        DB::table('jeda_file_checks')->insert([
            'session_key' => $this->sessionKey($request), 'extension' => $extension ?: null,
            'double_extension' => $double, 'executable_type' => $executable,
            'indicators' => json_encode($indicators, JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'extension' => $extension, 'double_extension' => $double, 'executable_type' => $executable, 'indicators' => $indicators,
            'message' => $double ? 'Ekstensi ganda terdeteksi. Jangan buka file sebelum memverifikasi pengirim.' : ($executable ? 'Jenis file perlu diwaspadai.' : 'Nama saja tidak cukup untuk memastikan file aman.')]);
    }

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

    public function saveChecklist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature' => ['required', Rule::in(['jam-emas', 'keluarga', 'kartu'])],
            'situation' => ['nullable', 'string', 'max:80'],
            'item' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'done' => ['required', 'boolean'],
        ]);
        $key = ['session_key' => $this->sessionKey($request), 'feature' => $data['feature'], 'situation' => $data['situation'] ?? 'umum', 'item_key' => $data['item']];
        DB::table('jeda_checklist_items')->updateOrInsert($key, ['is_done' => $data['done'], 'updated_at' => now(), 'created_at' => now()]);
        return response()->json(['ok' => true, 'saved' => true]);
    }

    public function supportAction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature' => ['required', 'string', 'max:50'],
            'action' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9 ._-]+$/'],
        ]);
        DB::table('jeda_support_actions')->insert([
            'session_key' => $this->sessionKey($request), 'feature' => $data['feature'], 'action' => $data['action'],
            'metadata' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function progress(Request $request): JsonResponse
    {
        $key = $this->sessionKey($request);
        return response()->json([
            'ok' => true,
            'message_analyses' => DB::table('jeda_message_analyses')->where('session_key', $key)->count(),
            'link_checks' => DB::table('jeda_link_checks')->where('session_key', $key)->count(),
            'file_checks' => DB::table('jeda_file_checks')->where('session_key', $key)->count(),
            'simulation_attempts' => DB::table('jeda_simulation_attempts')->where('session_key', $key)->count(),
            'strong_simulation_choices' => DB::table('jeda_simulation_attempts')->where('session_key', $key)->where('strong_choice', true)->count(),
            'checklist_done' => DB::table('jeda_checklist_items')->where('session_key', $key)->where('is_done', true)->count(),
        ]);
    }
}