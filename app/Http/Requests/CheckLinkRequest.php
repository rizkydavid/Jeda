<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validasi input Cek Tautan. */
class CheckLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guest-first: tanpa akun
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
            // Persetujuan eksplisit sebelum alamat dikirim ke VirusTotal untuk dianalisis.
            'submit_to_virustotal' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required' => 'Tempel tautan yang ingin diperiksa terlebih dahulu.',
            'url.max' => 'Tautan terlalu panjang (maksimal 2048 karakter).',
            'submit_to_virustotal.boolean' => 'Pilihan pengiriman ke VirusTotal tidak valid.',
        ];
    }
}
