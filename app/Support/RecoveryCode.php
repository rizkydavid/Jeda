<?php

namespace App\Support;

/**
 * Kode pemulihan progres Latihan (pengganti akun).
 * Format: JEDA-XXXX-XXXX-XXXX (12 karakter acak, tanpa huruf/angka yang mudah tertukar:
 * I, O, 0, 1). Server hanya menyimpan hash SHA-256 dari kode, bukan kodenya.
 */
final class RecoveryCode
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LENGTH = 12;

    public static function generate(): string
    {
        $chars = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return 'JEDA-'.implode('-', str_split($chars, 4));
    }

    /** Rapikan masukan pengguna (huruf kecil, spasi, tanpa tanda hubung). Null jika bentuknya tidak valid. */
    public static function normalize(string $input): ?string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));
        if (str_starts_with($clean, 'JEDA')) {
            $clean = substr($clean, 4);
        }
        if (! preg_match('/^[A-HJ-NP-Z2-9]{'.self::LENGTH.'}$/', $clean)) {
            return null;
        }

        return 'JEDA-'.implode('-', str_split($clean, 4));
    }

    /** Kode berentropi tinggi (60 bit), jadi SHA-256 biasa sudah cukup dan tidak bergantung pada APP_KEY. */
    public static function hash(string $normalizedCode): string
    {
        return hash('sha256', $normalizedCode);
    }
}
