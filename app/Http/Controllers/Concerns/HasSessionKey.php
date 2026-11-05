<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait HasSessionKey
{
    protected function sessionKey(Request $request): string
    {
        // Guest-first: data dipisahkan berdasarkan sesi browser, tanpa akun pengguna.
        return substr(hash('sha256', (string) $request->session()->getId()), 0, 64);
    }
}
