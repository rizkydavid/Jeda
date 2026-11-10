<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasSessionKey;
use App\Http\Requests\CheckFileRequest;
use App\Services\Cek\FileAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cek File. Controller hanya menerima request dan mengembalikan respons;
 * penilaian ada di App\Services\Cek\FileAnalysisService. Isi file tidak pernah dikirim ke server.
 */
class FileCheckController extends Controller
{
    use HasSessionKey;

    public function check(CheckFileRequest $request, FileAnalysisService $analysis): JsonResponse
    {
        $result = $analysis->analyze([
            'filename' => $request->validated('filename'),
            'size' => (int) $request->validated('size'),
            'sha256' => $request->validated('sha256'),
            'detected_type' => $request->validated('detected_type'),
            'content_scanned' => $request->boolean('content_scanned'),
            'flags' => array_map('boolval', (array) $request->validated('flags', [])),
            'apk_package' => $request->validated('apk_package'),
            'apk_permissions' => $request->validated('apk_permissions'),
        ]);

        $indicators = array_values(array_map(
            fn (array $s) => $s['label'],
            array_filter($result['signals'], fn (array $s) => $s['points'] > 0)
        ));

        $saved = false;
        try {
            // Disimpan: ekstensi, jenis isi, ukuran, skor, dan status. Nama file dan SHA-256 TIDAK disimpan.
            DB::table('jeda_file_checks')->insert([
                'session_key' => $this->sessionKey($request),
                'extension' => $result['extension'] !== '' ? mb_substr($result['extension'], 0, 20) : null,
                'detected_type' => $result['detected_type'],
                'size_bytes' => $result['size'],
                'double_extension' => $result['double_extension'],
                'executable_type' => $result['executable_type'],
                'risk_score' => $result['score'],
                'risk_level' => $result['level'],
                'indicators' => json_encode($indicators, JSON_UNESCAPED_UNICODE),
                'coverage' => $result['coverage']['status'],
                'signals' => json_encode(array_map(
                    fn (array $x) => ['id' => $x['id'], 'group' => $x['group'], 'label' => $x['label'], 'points' => $x['points']],
                    $result['signals']
                ), JSON_UNESCAPED_UNICODE),
                'checks' => json_encode(array_map(
                    fn (array $c) => array_filter(
                        ['label' => $c['label'], 'state' => $c['state'], 'result' => $c['result'] ?? null],
                        fn ($v) => $v !== null
                    ),
                    $result['checks']
                ), JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $saved = true;
        } catch (Throwable $e) {
            // Hasil tetap ditampilkan walau penyimpanan gagal (mis. migration belum dijalankan).
            Log::warning('Hasil cek file belum tersimpan', ['error' => $e::class]);
        }

        return response()->json([
            'ok' => true,
            'saved' => $saved,
            'extension' => $result['extension'],
            'detected_type' => $result['detected_type'],
            'size' => $result['size'],
            'double_extension' => $result['double_extension'],
            'executable_type' => $result['executable_type'],
            'score' => $result['score'],
            'level' => $result['level'],
            'confirmed' => $result['confirmed'],
            'indicators' => $indicators,
            'signals' => $result['signals'],
            'coverage' => $result['coverage'],
            'checks' => $result['checks'],
            'message' => $result['message'],
            'coverage_note' => $result['coverage_note'],
            'summary' => $result['summary'],
            'apk' => $result['apk'],
        ]);
    }
}
