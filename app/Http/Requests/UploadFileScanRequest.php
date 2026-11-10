<?php

namespace App\Http\Requests;

use App\Services\Cek\UploadLimit;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pengiriman file untuk dipindai antivirus. Berbeda dari Cek File biasa: isi file memang dikirim,
 * karena itu persetujuan eksplisit (consent) wajib ada di setiap permintaan.
 */
class UploadFileScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guest-first: tanpa akun
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.max(1, intdiv(UploadLimit::bytes(), 1024))],
            'consent' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        $mb = number_format(UploadLimit::bytes() / 1048576, 1, ',', '.');

        return [
            'file.required' => "File tidak sampai ke server. Kemungkinan ukurannya melebihi batas unggah ($mb MB).",
            'file.uploaded' => "File gagal diunggah. Ukurannya mungkin melebihi batas unggah server ($mb MB).",
            'file.max' => "File terlalu besar untuk dipindai antivirus (maksimal $mb MB).",
            'consent.required' => 'Centang izin mengirim file ke VirusTotal dulu.',
            'consent.accepted' => 'Centang izin mengirim file ke VirusTotal dulu.',
        ];
    }
}
