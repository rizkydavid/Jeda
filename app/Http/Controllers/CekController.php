<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasSessionKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Modul Cek: pemeriksaan tautan dan nama file. */
class CekController extends Controller
{
    use HasSessionKey;

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
}
