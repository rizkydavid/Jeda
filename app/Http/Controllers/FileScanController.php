<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileScanRequest;
use App\Services\Cek\VirusTotalFileClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pemindaian antivirus OPSIONAL lewat VirusTotal. Ini SATU-SATUNYA jalur tempat isi file sampai ke server Jeda
 * dan diteruskan ke VirusTotal, dan hanya setelah pengguna mencentang izin.
 *
 * Yang dilakukan: hitung SHA-256, teruskan file ke VirusTotal, lalu biarkan temp file terhapus otomatis.
 * Tidak ada yang disimpan di database. Nama file tidak diteruskan ke VirusTotal dan tidak dicatat di log.
 */
class FileScanController extends Controller
{
    private const SESSION_KEY = 'jeda_scans';

    public function upload(UploadFileScanRequest $request, VirusTotalFileClient $vt): JsonResponse
    {
        $file = $request->file('file');
        $path = (string) $file->getRealPath();
        $sha256 = (string) hash_file('sha256', $path);

        $result = $vt->upload($path);
        if ($result['state'] !== 'ok') {
            return $this->failure($result['state'], $result['reason']);
        }

        // Hanya id yang kita buat sendiri yang boleh ditanyakan lewat status(), supaya endpoint ini
        // tidak bisa dipakai menghabiskan kuota VirusTotal untuk analisis orang lain.
        $scans = (array) $request->session()->get(self::SESSION_KEY, []);
        $scans[$result['analysis_id']] = $sha256;
        $request->session()->put(self::SESSION_KEY, array_slice($scans, -10, null, true));

        return response()->json(['ok' => true, 'status' => 'queued', 'analysis_id' => $result['analysis_id']]);
    }

    public function status(Request $request, VirusTotalFileClient $vt): JsonResponse
    {
        $id = (string) $request->query('id', '');
        $scans = (array) $request->session()->get(self::SESSION_KEY, []);
        if (preg_match('/^[A-Za-z0-9+\/=_\-]{8,200}$/', $id) !== 1 || ! isset($scans[$id])) {
            return response()->json(['ok' => false, 'status' => 'error', 'message' => 'Pemindaian tidak ditemukan. Kirim ulang filenya.'], 404);
        }

        $r = $vt->analysis($id);

        if ($r['state'] === 'pending') {
            return response()->json(['ok' => true, 'status' => 'pending']);
        }
        if ($r['state'] !== 'ok') {
            // Kuota VirusTotal habis sementara: pemindaiannya sendiri jalan terus, jadi pengguna cukup menunggu.
            if ($r['reason'] === 'rate_limited') {
                return response()->json(['ok' => true, 'status' => 'busy']);
            }

            return $this->failure($r['state'], $r['reason']);
        }

        $vt->remember((string) $scans[$id], $r);

        return response()->json(array_merge(['ok' => true, 'status' => 'done'], self::verdict($r)));
    }

    /**
     * Vonis dari hasil pemindaian. Satu atau dua mesin saja sering keliru (false positive),
     * jadi baru "terkonfirmasi" bila banyak mesin sepakat, sama seperti pencocokan hash.
     *
     * @return array<string,mixed>
     */
    public static function verdict(array $r): array
    {
        $hits = $r['malicious'] + $r['suspicious'];
        $base = [
            'verdict' => $r['result'],
            'malicious' => $r['malicious'],
            'suspicious' => $r['suspicious'],
            'total' => $r['total'],
            'engines' => $r['engines'] ?? [],
            'confirmed' => false,
            'level' => null,
        ];

        if ($r['result'] === 'flagged' && $r['malicious'] >= 3) {
            return array_merge($base, [
                'confirmed' => true, 'level' => 'Tinggi',
                'headline' => 'Banyak antivirus menandai file ini berbahaya',
                'message' => "$hits dari {$r['total']} mesin antivirus menandai file ini. Jangan buka atau pasang file ini.",
            ]);
        }
        if ($r['result'] === 'flagged') {
            return array_merge($base, [
                'level' => 'Sedang',
                'headline' => 'Sebagian kecil antivirus menandai file ini',
                'message' => "$hits dari {$r['total']} mesin menandai file ini. Satu atau dua mesin saja sering keliru, jadi ini belum bukti kuat, tetapi tetap waspada.",
            ]);
        }
        if ($r['result'] === 'clean') {
            return array_merge($base, [
                'headline' => "Tidak ada dari {$r['total']} mesin antivirus yang menandai file ini",
                'message' => 'Itu kabar baik, tetapi bukan jaminan. Malware yang sangat baru bisa belum dikenali antivirus.',
            ]);
        }

        return array_merge($base, [
            'headline' => 'Antivirus tidak bisa memindai file ini',
            'message' => 'Jenis file ini tidak didukung pemindai, jadi hasilnya tidak bisa dipakai untuk menilai keamanan file.',
        ]);
    }

    private function failure(string $state, ?string $reason): JsonResponse
    {
        if ($state === 'not_configured') {
            return response()->json(['ok' => false, 'status' => 'error', 'message' => 'Pemindaian antivirus belum diaktifkan di server ini (API key VirusTotal belum diisi).'], 503);
        }
        $message = match ($reason) {
            'rate_limited' => 'Kuota VirusTotal sedang penuh. Tunggu satu atau dua menit lalu coba lagi.',
            'access_denied' => 'VirusTotal menolak permintaan dari server Jeda.',
            'not_found' => 'Pemindaian tidak ditemukan di VirusTotal. Kirim ulang filenya.',
            'invalid_request' => 'File tidak dapat dibaca untuk dikirim.',
            default => 'Layanan VirusTotal sedang bermasalah atau tidak dapat dihubungi. Coba lagi nanti.',
        };

        return response()->json(['ok' => false, 'status' => 'error', 'message' => $message], $reason === 'rate_limited' ? 429 : 503);
    }
}
