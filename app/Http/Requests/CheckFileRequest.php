<?php

namespace App\Http\Requests;

use App\Services\Cek\FileAnalysisService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi input Cek File. Yang diterima hanya ringkasan hasil pembacaan di browser:
 * isi file tidak pernah dikirim, dan ukuran permintaan sengaja kecil.
 */
class CheckFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guest-first: tanpa akun
    }

    /** Batas jumlah izin APK yang dinilai. Browser dan server memakai angka yang sama. */
    public const MAX_APK_PERMISSIONS = 500;

    /**
     * Data APK berasal dari browser dan dibaca dari file yang bisa sengaja dirusak. Nilai yang tidak valid
     * dibuang di sini, bukan membuat seluruh pemeriksaan ditolak (422): APK dengan nama izin atau paket
     * yang janggal justru harus tetap diperiksa. Izin yang dikenali mesin penilaian didahulukan, jadi
     * ratusan izin pengisi tidak bisa mendorong izin berbahaya keluar dari batas.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('apk_package')) {
            $package = $this->input('apk_package');
            $merge['apk_package'] = is_string($package) && preg_match('/^[A-Za-z0-9_.]{1,150}$/', $package) === 1 ? $package : null;
        }

        if ($this->has('apk_permissions')) {
            $merge['apk_permissions'] = $this->cleanPermissions($this->input('apk_permissions'));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /** @return array<int,string>|null */
    private function cleanPermissions(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $dikenal = config('jeda_cek.apk_permissions', []);
        $known = [];
        $other = [];
        // Pembacaan dibatasi supaya permintaan yang sangat besar tidak membebani server.
        foreach (array_slice(array_values($raw), 0, self::MAX_APK_PERMISSIONS * 10) as $code) {
            if (! is_string($code) || strlen($code) > 120 || preg_match('/^[A-Za-z0-9_.]+$/', $code) !== 1) {
                continue;
            }
            if (isset($dikenal[$code])) {
                $known[$code] = $code;
            } else {
                $other[$code] = $code;
            }
        }

        return array_slice(array_values($known + $other), 0, self::MAX_APK_PERMISSIONS);
    }

    public function rules(): array
    {
        $rules = [
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:0', 'max:1099511627776'],
            'sha256' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'detected_type' => ['nullable', 'string', Rule::in(config('jeda_cek.file_types', []))],
            'content_scanned' => ['nullable', 'boolean'],
            'content_partial' => ['nullable', 'boolean'],
            'flags' => ['nullable', 'array', 'max:'.count(FileAnalysisService::FLAGS)],
            // APK: nama paket dan daftar izin yang dibaca browser dari AndroidManifest.xml.
            'apk_package' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_.]{1,150}$/'],
            'apk_permissions' => ['nullable', 'array', 'max:'.self::MAX_APK_PERMISSIONS],
            'apk_permissions.*' => ['string', 'max:120', 'regex:/^[A-Za-z0-9_.]+$/'],
        ];
        foreach (FileAnalysisService::FLAGS as $flag) {
            $rules["flags.$flag"] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'filename.required' => 'Pilih file atau tulis nama file yang ingin diperiksa.',
            'filename.max' => 'Nama file terlalu panjang (maksimal 255 karakter).',
            'sha256.regex' => 'Sidik jari file tidak valid.',
        ];
    }
}
