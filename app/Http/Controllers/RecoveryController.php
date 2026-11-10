<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasSessionKey;
use App\Support\QuizProgressStore;
use App\Support\RecoveryCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kode pemulihan progres Latihan untuk pengguna tanpa akun.
 * Kode mengikat progres ke pemegangnya: saat dipakai, progres pindah ke sesi yang memakainya.
 */
class RecoveryController extends Controller
{
    use HasSessionKey;

    private const TABLE = 'jeda_recovery_codes';

    public function create(Request $request): JsonResponse
    {
        $key = $this->sessionKey($request);
        $store = QuizProgressStore::make();

        if (! $store->hasAttempts($key)) {
            return response()->json(['ok' => false, 'message' => 'Mainkan minimal satu ronde dulu supaya ada progres yang bisa dicadangkan.'], 422);
        }

        $code = DB::transaction(function () use ($key) {
            // Satu pemain, satu kode aktif: kode lama otomatis tidak berlaku.
            DB::table(self::TABLE)->where('session_key', $key)->delete();

            do {
                $code = RecoveryCode::generate();
                $hash = RecoveryCode::hash($code);
            } while (DB::table(self::TABLE)->where('code_hash', $hash)->exists());

            DB::table(self::TABLE)->insert([
                'code_hash' => $hash,
                'session_key' => $key,
                'redeemed_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $code;
        });

        return response()->json($store->payload($key, $this->today($request)) + [
            'code' => $code,
            'message' => 'Kode pemulihan dibuat. Simpan sekarang, kode ini hanya ditampilkan sekali.',
        ])->header('Cache-Control', 'no-store');
    }

    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $notFound = response()->json(['ok' => false, 'message' => 'Kode tidak ditemukan. Periksa kembali penulisannya.'], 422);

        $normalized = RecoveryCode::normalize($data['code']);
        if ($normalized === null) {
            return $notFound;
        }

        $key = $this->sessionKey($request);
        $store = QuizProgressStore::make();
        $hash = RecoveryCode::hash($normalized);

        $result = DB::transaction(function () use ($hash, $key) {
            $row = DB::table(self::TABLE)->where('code_hash', $hash)->lockForUpdate()->first();
            if (! $row) {
                return null;
            }
            if ($row->session_key === $key) {
                return ['already' => true, 'moved' => 0];
            }

            // Progres (termasuk yang sudah ada di sesi ini) digabung di bawah kunci sesi sekarang.
            $moved = DB::table(QuizProgressStore::TABLE)->where('session_key', $row->session_key)->update(['session_key' => $key, 'updated_at' => now()]);

            DB::table(self::TABLE)->where('id', $row->id)->update([
                'session_key' => $key,
                'redeemed_count' => DB::raw('redeemed_count + 1'),
                'last_redeemed_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table(self::TABLE)->where('session_key', $key)->where('id', '!=', $row->id)->delete();

            return ['already' => false, 'moved' => $moved];
        });

        if ($result === null) {
            return $notFound;
        }

        return response()->json($store->payload($key, $this->today($request)) + [
            'already' => $result['already'],
            'restored_attempts' => $result['moved'],
            'message' => $result['already']
                ? 'Progres ini sudah aktif di browser ini.'
                : 'Progres berhasil dipulihkan.',
        ])->header('Cache-Control', 'no-store');
    }

    private function today(Request $request): string
    {
        $date = $request->input('date');

        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : now()->toDateString();
    }
}
