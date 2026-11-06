<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasSessionKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Modul Cek: pemeriksaan nama file (Tahap 4 akan memindahkannya ke FileCheckController).
 * Pemeriksaan tautan ada di LinkCheckController.
 */
class CekController extends Controller
{
    use HasSessionKey;

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
