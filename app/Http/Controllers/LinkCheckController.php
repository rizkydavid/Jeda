<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasSessionKey;
use App\Http\Requests\CheckLinkRequest;
use App\Services\Cek\GeminiExplanationService;
use App\Services\Cek\LinkAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cek Tautan. Controller hanya menerima request dan mengembalikan respons;
 * seluruh pemeriksaan ada di App\Services\Cek\LinkAnalysisService.
 */
class LinkCheckController extends Controller
{
    use HasSessionKey;

    public function check(CheckLinkRequest $request, LinkAnalysisService $analysis, GeminiExplanationService $gemini): JsonResponse
    {
        // Pelacakan redirect dan tiga layanan luar bisa memakan belasan detik pada koneksi lambat.
        @set_time_limit(60);

        $result = $analysis->analyze(
            $request->validated('url'),
            $request->boolean('submit_to_virustotal'),
        );

        if ($result === null) {
            return response()->json(['ok' => false, 'message' => 'Alamat tautan belum valid.'], 422);
        }

        $indicators = array_values(array_map(
            fn (array $s) => $s['label'],
            array_filter($result['signals'], fn (array $s) => $s['points'] > 0)
        ));

        $id = null;
        $token = Str::random(40);
        try {
            // Disimpan: hostname, skor, dan status pemeriksaan. URL lengkap TIDAK disimpan.
            $id = DB::table('jeda_link_checks')->insertGetId([
                'session_key' => $this->sessionKey($request),
                'token' => $token,
                'hostname' => $result['parsed']['host'],
                'final_hostname' => $result['final_hostname'],
                'risk_score' => $result['score'],
                'risk_level' => $result['level'],
                'indicators' => json_encode($indicators, JSON_UNESCAPED_UNICODE),
                // Ringkasan temuan untuk penjelasan AI. Tidak memuat alamat maupun isi halaman.
                'signals' => json_encode(array_map(
                    fn (array $x) => ['id' => $x['id'], 'group' => $x['group'], 'label' => $x['label'], 'points' => $x['points']],
                    $result['signals']
                ), JSON_UNESCAPED_UNICODE),
                'domain_age_days' => $result['domain_age_days'],
                'coverage' => $result['coverage']['status'],
                'checks' => json_encode(array_map(
                    fn (array $c) => array_filter(
                        ['label' => $c['label'], 'state' => $c['state'], 'result' => $c['result'] ?? null, 'count' => $c['count'] ?? null],
                        fn ($v) => $v !== null
                    ),
                    $result['checks']
                ), JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Hasil tetap ditampilkan walau penyimpanan gagal (mis. migration belum dijalankan).
            Log::warning('Hasil cek tautan belum tersimpan', ['error' => $e::class]);
        }

        return response()->json([
            'ok' => true,
            'id' => $id,
            'saved' => $id !== null,
            // Penjelasan AI hanya ditawarkan jika hasil tersimpan dan Gemini sudah dikonfigurasi.
            'explanation_available' => $id !== null && $gemini->isConfigured(),
            'explain_token' => $id !== null ? $token : null,
            'hostname' => $result['parsed']['host'],
            'final_hostname' => $result['final_hostname'],
            'redirect_hosts' => $result['redirect_hosts'],
            'official_brand' => $result['official_brand'],
            'official_shared' => $result['official_shared'],
            'score' => $result['score'],
            'level' => $result['level'],
            'confirmed' => $result['confirmed'],
            'indicators' => $indicators,
            'signals' => $result['signals'],
            'coverage' => $result['coverage'],
            'checks' => $result['checks'],
            'message' => $result['message'],
            'coverage_note' => $result['coverage_note'],
        ]);
    }
}
