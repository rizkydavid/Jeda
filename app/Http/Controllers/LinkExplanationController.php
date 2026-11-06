<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExplainLinkRequest;
use App\Services\Cek\GeminiExplanationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penjelasan AI untuk hasil Cek Tautan. Terpisah dari LinkCheckController agar kegagalan Gemini
 * tidak pernah mengganggu hasil pemeriksaan utama.
 */
class LinkExplanationController extends Controller
{
    private const UNAVAILABLE = 'Penjelasan AI belum tersedia saat ini. Hasil pemeriksaan di atas tetap berlaku.';

    public function explain(ExplainLinkRequest $request, GeminiExplanationService $gemini): JsonResponse
    {
        $row = DB::table('jeda_link_checks')->where('token', $request->validated('token'))->first();
        if ($row === null) {
            return response()->json(['ok' => false, 'message' => 'Hasil pemeriksaan tidak ditemukan. Periksa ulang tautannya.'], 404);
        }

        // Sudah pernah dibuat: pakai lagi, tidak memanggil Gemini.
        if (! empty($row->explanation)) {
            return response()->json(['ok' => true, 'cached' => true, 'explanation' => json_decode((string) $row->explanation, true)]);
        }

        if (! $gemini->isConfigured()) {
            return response()->json(['ok' => false, 'reason' => 'not_configured', 'message' => 'Penjelasan AI belum diaktifkan di server ini.'], 503);
        }

        $result = $gemini->explain($gemini->buildPayload($row));
        if (! $result['ok']) {
            return response()->json(['ok' => false, 'reason' => $result['reason'], 'message' => self::UNAVAILABLE], 503);
        }

        try {
            DB::table('jeda_link_checks')->where('id', $row->id)->update([
                'explanation' => json_encode($result['explanation'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Penjelasan AI belum tersimpan', ['error' => $e::class]);
        }

        return response()->json(['ok' => true, 'cached' => false, 'explanation' => $result['explanation']]);
    }
}
