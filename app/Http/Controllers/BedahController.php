<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasSessionKey;
use App\Services\Cek\BedahAiService;
use App\Services\Cek\BedahScorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BedahController extends Controller
{
    use HasSessionKey;

    private const NEUTRAL_STEPS = [
        'Kalau ragu, tanyakan langsung ke pengirimnya lewat jalur lain (telepon atau bertemu).',
        'Jangan klik tautan, unduh aplikasi, atau kirim kode dan uang kalau diminta.',
    ];

    public function analyzeMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'indicators' => ['present', 'array', 'max:'.count(BedahScorer::allIds())],
            'indicators.*' => ['string', 'distinct', Rule::in(BedahScorer::allIds())],
            'text_length' => ['required', 'integer', 'between:1,12000'],
        ]);

        $eval = BedahScorer::evaluate($data['indicators']);
        $id = $this->record($request, $eval, (int) $data['text_length']);

        return response()->json([
            'ok' => true, 'id' => $id, 'score' => $eval['score'], 'level' => $eval['level'],
            'indicators' => array_merge($eval['ids'], array_column($eval['derived'], 'id')),
        ]);
    }

    public function analyzeMessageWithAi(Request $request, BedahAiService $ai): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'min:10', 'max:4000']]);

        $out = $ai->analyze($data['text']);
        if (! $out['ok']) {
            $message = match ($out['reason']) {
                'not_configured' => 'Analisis AI belum diaktifkan di server ini (isi GEMINI_API_KEY atau ANTHROPIC_API_KEY di .env).',
                'rate_limited' => 'Layanan AI sedang penuh. Tunggu sebentar lalu coba lagi.',
                'unreachable' => 'Layanan AI tidak bisa dihubungi. Coba lagi nanti.',
                'bad_response' => 'Jawaban AI tidak bisa dibaca. Coba lagi.',
                default => 'Layanan AI sedang bermasalah. Coba lagi nanti.',
            };

            return response()->json(['ok' => false, 'message' => $message], $out['reason'] === 'not_configured' ? 503 : 502);
        }

        $eval = BedahScorer::evaluate(array_column($out['indikator'], 'id'));
        $quiet = $eval['gated'] || $eval['level'] === 'Belum terdeteksi';

        $indikator = [];
        if (! $eval['gated']) {
            $indikator = array_map(fn ($i) => $i + ['judul' => BedahScorer::TITLES[$i['id']]], $out['indikator']);
            foreach ($eval['derived'] as $d) {
                $indikator[] = ['id' => $d['id'], 'judul' => $d['judul'], 'bukti' => '', 'penjelasan' => $d['penjelasan']];
            }
        }

        $ringkasan = $out['ringkasan'];
        if ($eval['gated'] || $ringkasan === '' || $ai->claimsSafe($ringkasan)) {
            $ringkasan = $this->defaultSummary($eval, $out['konteks']);
        }

        $this->record($request, $eval, mb_strlen($data['text']));

        return response()->json([
            'ok' => true,
            'score' => $eval['score'],
            'level' => $eval['level'],
            'jenis' => $quiet ? '' : $out['jenis'],
            'ringkasan' => $ringkasan,
            'indikator' => $indikator,
            'langkah_aman' => $quiet ? self::NEUTRAL_STEPS : $out['langkah_aman'],
            'masked' => $out['masked'],
        ]);
    }

    private function defaultSummary(array $eval, string $konteks): string
    {
        if ($eval['gated']) {
            return 'Belum ada permintaan berisiko (kirim uang, kode OTP, unduh aplikasi) di pesan ini. '
                .'Pesannya belum cukup lengkap untuk disimpulkan sebagai penipuan.';
        }
        if ($eval['level'] === 'Belum terdeteksi') {
            return $konteks === 'kurang'
                ? 'Pesannya terlalu singkat untuk dinilai, dan belum ada permintaan berisiko di dalamnya. Ini bukan jaminan aman.'
                : 'Belum ada permintaan atau tekanan yang mencurigakan di pesan ini. Ini bukan jaminan aman, tetap cek lewat jalur resmi kalau ragu.';
        }

        return 'Ditemukan '.count($eval['ids']).' tanda penipuan di pesan ini. Ini penilaian risiko, bukan vonis.';
    }

    private function record(Request $request, array $eval, int $textLength): ?int
    {
        try {
            return DB::table('jeda_message_analyses')->insertGetId([
                'session_key' => $this->sessionKey($request),
                'risk_score' => $eval['score'],
                'risk_level' => $eval['level'],
                'indicators' => json_encode(array_merge($eval['ids'], array_column($eval['derived'], 'id')), JSON_UNESCAPED_UNICODE),
                'text_length' => max(1, $textLength),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Bedah: gagal mencatat progres', ['error' => Str::limit($e->getMessage(), 200, '…')]);

            return null;
        }
    }
}