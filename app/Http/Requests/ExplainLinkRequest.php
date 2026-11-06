<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validasi permintaan penjelasan AI. Hanya token yang diterima, tidak ada alamat atau teks bebas. */
class ExplainLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['token' => ['required', 'string', 'size:40', 'alpha_num']];
    }

    public function messages(): array
    {
        $pesan = 'Token hasil pemeriksaan tidak valid. Periksa ulang tautannya.';

        return ['token.required' => $pesan, 'token.string' => $pesan, 'token.size' => $pesan, 'token.alpha_num' => $pesan];
    }
}
