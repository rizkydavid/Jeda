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

    public function rules(): array
    {
        $rules = [
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:0'],
            'sha256' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'detected_type' => ['nullable', 'string', Rule::in(config('jeda_cek.file_types', []))],
            'content_scanned' => ['nullable', 'boolean'],
            'flags' => ['nullable', 'array', 'max:'.count(FileAnalysisService::FLAGS)],
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
