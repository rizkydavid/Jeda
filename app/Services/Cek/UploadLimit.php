<?php

namespace App\Services\Cek;

/**
 * Batas ukuran file yang boleh dikirim untuk pemindaian antivirus.
 * Terkecil dari: pengaturan Jeda, batas mutlak VirusTotal (32 MB), upload_max_filesize dan post_max_size PHP.
 * PHP bawaan hanya mengizinkan 2 MB, jadi tanpa ini pengguna akan melihat unggahan gagal tanpa penjelasan.
 */
final class UploadLimit
{
    public static function bytes(): int
    {
        $limits = [
            (int) config('jeda_cek.file_upload.max_bytes', 10 * 1024 * 1024),
            (int) config('jeda_cek.file_upload.hard_cap_bytes', 32 * 1024 * 1024),
        ];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $ini = self::iniBytes((string) ini_get($key));
            if ($ini > 0) {
                $limits[] = $ini;
            }
        }

        return max(0, min($limits));
    }

    /** "2M", "8M", "512K", "1G" → byte. "0" atau kosong = tak terbatas (dikembalikan 0). */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0' || $value === '-1') {
            return 0;
        }
        $number = (float) $value;
        $unit = strtolower(substr($value, -1));

        return (int) match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
