<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasSessionKey;
use App\Support\CekAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Modul Cek: pemeriksaan tautan dan nama file. Tautan tidak dibuka, file tidak dibaca. */
class CekController extends Controller
{
    use HasSessionKey;

    public function checkLink(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:2048']]);
        $result = CekAnalyzer::link($data['url']);
        if (!$result['ok']) {
            return response()->json($result, 422);
        }

        $this->applyReputation($result);
        unset($result['candidate']);

        // Pencatatan hanya untuk ringkasan sesi (hostname saja). Kalau gagal, hasil tetap ditampilkan.
        $result['saved'] = $this->store('jeda_link_checks', [
            'session_key' => $this->sessionKey($request),
            'hostname' => $result['hostname'],
            'risk_score' => $result['score'],
            'risk_level' => $result['level'],
            'indicators' => json_encode($result['indicators'], JSON_UNESCAPED_UNICODE),
        ]);

        return response()->json($result);
    }

    public function checkFile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'detected' => ['nullable', 'string', 'in:exe,elf,script,pdf,png,jpg,gif,zip,rar,7z,gz,unknown'],
        ]);
        $result = CekAnalyzer::file($data['filename'], $data['detected'] ?? null);
        if (!$result['ok']) {
            return response()->json($result, 422);
        }

        // Nama file asli tidak disimpan.
        $result['saved'] = $this->store('jeda_file_checks', [
            'session_key' => $this->sessionKey($request),
            'extension' => $result['extension'] ?: null,
            'double_extension' => $result['double_extension'],
            'executable_type' => $result['executable_type'],
            'indicators' => json_encode($result['indicators'], JSON_UNESCAPED_UNICODE),
        ]);
        unset($result['filename']);

        return response()->json($result);
    }

    /**
     * Cek reputasi lewat Google Safe Browsing (opsional, perlu GOOGLE_SAFE_BROWSING_KEY di .env).
     * Catatan privasi: pada langkah ini alamat tautan dikirim ke Google. Tanpa key, langkah ini dilewati.
     */
    private function applyReputation(array &$result): void
    {
        $key = config('services.safe_browsing.key');
        $result['reputation'] = 'tidak-aktif';
        if (!$key) {
            return;
        }
        try {
            $response = Http::timeout(5)->acceptJson()->post('https://safebrowsing.googleapis.com/v4/threatMatches:find?key=' . urlencode($key), [
                'client' => ['clientId' => 'jeda', 'clientVersion' => '1.0'],
                'threatInfo' => [
                    'threatTypes' => ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE', 'POTENTIALLY_HARMFUL_APPLICATION'],
                    'platformTypes' => ['ANY_PLATFORM'],
                    'threatEntryTypes' => ['URL'],
                    'threatEntries' => [['url' => $result['candidate']]],
                ],
            ]);
            if (!$response->successful()) {
                $result['reputation'] = 'gagal';
                return;
            }
            $matches = $response->json('matches') ?? [];
            if ($matches) {
                $labels = ['SOCIAL_ENGINEERING' => 'phishing atau penipuan', 'MALWARE' => 'malware', 'UNWANTED_SOFTWARE' => 'perangkat lunak berbahaya', 'POTENTIALLY_HARMFUL_APPLICATION' => 'aplikasi berpotensi berbahaya'];
                $type = $labels[$matches[0]['threatType'] ?? ''] ?? 'berbahaya';
                array_unshift($result['indicators'], 'Terdaftar di Google Safe Browsing sebagai situs ' . $type);
                $result['score'] = min(10, $result['score'] + 5);
                $result['level'] = 'Tinggi';
                $result['severity'] = 'high';
                $result['advice'] = ['Jangan klik dan jangan isi data apa pun.', 'Tanyakan ke pengirim lewat jalur lain (telepon atau aplikasi resmi).', 'Jika sudah terlanjur klik, buka halaman Jam Emas.'];
                $result['reputation'] = 'terdaftar';
            } else {
                $result['reputation'] = 'bersih';
                $result['notes'][] = 'Tidak ditemukan di daftar Google Safe Browsing. Situs phishing baru bisa belum terdaftar, jadi ini bukan jaminan aman.';
            }
        } catch (\Throwable $e) {
            Log::warning('Jeda cek: Safe Browsing gagal: ' . $e->getMessage());
            $result['reputation'] = 'gagal';
        }
    }

    private function store(string $table, array $row): bool
    {
        try {
            DB::table($table)->insert($row + ['created_at' => now(), 'updated_at' => now()]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Jeda cek: gagal menyimpan ke ' . $table . ': ' . $e->getMessage());
            return false;
        }
    }
}