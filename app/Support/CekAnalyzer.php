<?php

namespace App\Support;

/**
 * Pemeriksa pola tautan dan nama file. Murni PHP (tanpa Laravel) supaya mudah diuji.
 * Tautan tidak pernah dibuka dan file tidak pernah dibaca; hanya teksnya yang dianalisis.
 */
class CekAnalyzer
{
    private const SHORTENERS = ['bit.ly', 's.id', 'tinyurl.com', 'cutt.ly', 'shorturl.at', 'is.gd', 't.co', 'rebrand.ly', 'linktr.ee', 'wa.me'];
    private const RISKY_TLD = ['xyz', 'top', 'click', 'icu', 'buzz', 'cfd', 'sbs', 'rest', 'work', 'cam', 'monster', 'support', 'zip', 'mov', 'site', 'online', 'link', 'store', 'website', 'space', 'fun', 'vip', 'pw', 'tk', 'ml', 'ga', 'cf', 'gq', 'live'];
    private const SLD = ['co', 'com', 'net', 'org', 'go', 'or', 'ac', 'sch', 'web', 'my', 'biz'];
    private const BRANDS = ['bca', 'bri', 'bni', 'mandiri', 'cimb', 'btn', 'dana', 'ovo', 'gopay', 'shopee', 'tokopedia',
        'lazada', 'bpjs', 'pajak', 'kominfo', 'polri', 'ojk', 'jne', 'sicepat', 'pln', 'telkomsel', 'indosat', 'tiktok',
        'instagram', 'facebook', 'whatsapp', 'google', 'paypal', 'netflix', 'bank'];
    // Domain utama resmi yang umum dikenal. Daftar kecil, bukan jaminan keamanan.
    private const OFFICIAL = ['bca.co.id', 'klikbca.com', 'bri.co.id', 'bni.co.id', 'bankmandiri.co.id', 'cimbniaga.co.id',
        'dana.id', 'ovo.id', 'gopay.co.id', 'shopee.co.id', 'tokopedia.com', 'lazada.co.id', 'bpjs-kesehatan.go.id',
        'pajak.go.id', 'komdigi.go.id', 'polri.go.id', 'ojk.go.id', 'jne.co.id', 'sicepat.com', 'pln.co.id',
        'telkomsel.com', 'indosat.com', 'tiktok.com', 'instagram.com', 'facebook.com', 'whatsapp.com', 'google.com',
        'paypal.com', 'netflix.com', 'youtube.com', 'youtu.be', 'gmail.com', 'microsoft.com', 'apple.com', 'x.com', 'twitter.com', 'linkedin.com', 'github.com', 'wikipedia.org'];
    private const LURE = '/(login|log-in|signin|verif|verifikasi|secure|security|klaim|claim|hadiah|gift|bonus|promo|undian|reward|otp|update-akun|akun-anda)/i';
    private const SAFE_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'mp3', 'mp4'];
    private const EXEC_EXT = ['apk', 'exe', 'bat', 'cmd', 'msi', 'scr', 'vbs', 'js', 'ps1', 'jar', 'com', 'lnk', 'hta', 'dmg', 'pkg', 'sh', 'xapk', 'apks'];
    private const MACRO_EXT = ['docm', 'xlsm', 'pptm', 'dotm'];
    private const ARCHIVE_EXT = ['zip', 'rar', '7z', 'iso', 'img', 'tar', 'gz'];

    /** @return array{ok:bool,message?:string,hostname?:string,score?:int,level?:string,indicators?:array,notes?:array,advice?:array} */
    public static function link(string $input): array
    {
        $raw = trim($input);
        if ($raw === '' || preg_match('/\s/', $raw)) {
            return ['ok' => false, 'message' => 'Tempel satu tautan saja, tanpa spasi.'];
        }
        $explicitScheme = preg_match('~^([a-z][a-z0-9+.-]*)://~i', $raw, $m) ? strtolower($m[1]) : null;
        if ($explicitScheme !== null && !in_array($explicitScheme, ['http', 'https'], true)) {
            return ['ok' => false, 'message' => 'Hanya tautan http atau https yang bisa diperiksa.'];
        }
        $candidate = $explicitScheme ? $raw : 'https://' . ltrim($raw, '/');
        $parts = parse_url($candidate);
        if (!$parts || empty($parts['host'])) {
            return ['ok' => false, 'message' => 'Alamat tautan belum valid.'];
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $isIp = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false;
        if (!$isIp && (!preg_match('/^[a-z0-9.-]+$/', $host) || !str_contains($host, '.') || str_contains($host, '..') || strlen($host) > 253)) {
            return ['ok' => false, 'message' => 'Nama domain belum valid. Contoh: contoh.com/halaman'];
        }

        $path = ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $labels = explode('.', $host);
        $indicators = [];
        $notes = [];
        $score = 0;
        $add = function (string $text, int $points) use (&$indicators, &$score) {
            $indicators[] = $text;
            $score += $points;
        };

        if (isset($parts['user']) || isset($parts['pass'])) {
            $add('Ada tanda @ di alamat; tulisan di depannya bisa menyamarkan tujuan sebenarnya', 3);
        }
        if ($isIp) {
            $add('Alamat memakai IP langsung, bukan nama domain', 2);
        }
        if (!$isIp && str_contains($host, 'xn--')) {
            $add('Domain memakai karakter yang bisa menyerupai huruf lain (punycode)', 2);
        }

        $registrable = $isIp ? $host : self::registrable($labels);
        if (!$isIp && in_array($registrable, self::SHORTENERS, true)) {
            $add('Tautan pendek menyembunyikan tujuan akhir', 1);
        }

        if (!$isIp) {
            $official = in_array($registrable, self::OFFICIAL, true);
            $mainLabel = explode('.', $registrable)[0];
            $brandHit = null;
            foreach (self::BRANDS as $brand) {
                if (preg_match('/(^|[.-])' . $brand . '([.-]|$)/', $host) || str_contains($mainLabel, $brand) && $mainLabel !== $brand) {
                    $brandHit = $brand;
                    break;
                }
            }
            if ($official) {
                $notes[] = 'Domain utama (' . $registrable . ') cocok dengan situs resmi yang dikenal. Tetap pastikan ejaan dan sumber pengirim.';
            } elseif ($brandHit !== null && $mainLabel !== $brandHit) {
                $add('Memuat nama "' . $brandHit . '" tetapi domain utamanya ' . $registrable . ', bukan situs resmi yang dikenal', 2);
            }
            if (preg_match(self::LURE, $host)) {
                $add('Kata pancingan pada domain (login, verifikasi, hadiah, dan sejenisnya)', 1);
            }
            $tld = end($labels);
            if (in_array($tld, self::RISKY_TLD, true)) {
                $add('Akhiran domain .' . $tld . ' sering dipakai untuk situs sekali pakai', 1);
            }
            $extra = count($labels) - count(explode('.', $registrable));
            if ($extra >= 2) {
                $add('Subdomain bertingkat; yang menentukan pemilik adalah ' . $registrable, 1);
            }
            if (substr_count(str_replace('xn--', '', $host), '-') >= 3) {
                $add('Domain banyak tanda hubung', 1);
            }
            if (strlen($host) > 45) {
                $add('Nama domain sangat panjang', 1);
            }
        }

        // Pola khas tautan pengalih: subdomain umum (links, go, l, track...) + kode acak pendek di jalur.
        if (!$isIp && !in_array($registrable, self::SHORTENERS, true) && !in_array($registrable, self::OFFICIAL, true)) {
            $sub = count($labels) > count(explode('.', $registrable)) ? $labels[0] : '';
            $segments = array_values(array_filter(explode('/', $parts['path'] ?? '')));
            $oneToken = count($segments) === 1 && preg_match('/^(?=.*\d)(?=.*[a-z])[a-z0-9_-]{5,12}$/i', $segments[0]) === 1
                && !preg_match('/\.[a-z0-9]{2,5}$/i', $segments[0]);
            $redirSub = preg_match('/^(links?|go|l|r|s|u|t|click|track|trk|redirect|redir|short|url|lnk|m|app)$/i', $sub) === 1;
            if ($oneToken && ($redirSub || $sub === '')) {
                $add('Bentuk tautan pengalih: ' . ($redirSub ? 'subdomain "' . $sub . '" + ' : '') . 'kode acak di jalur. Tujuan akhirnya tidak terlihat', 1);
            }
        }

        if ($explicitScheme === 'http') {
            $add('Memakai http, bukan https (tidak terenkripsi)', 1);
        }
        if (preg_match('/\.(apk|exe|msi|scr|bat|cmd|vbs|ps1|jar|xapk)(\?|$)/i', $path)) {
            $add('Tautan langsung mengunduh file yang bisa menjalankan program', 2);
        }
        if (preg_match('/[?&](?:url|redirect|redir|next|goto|link|return)=https?(?::|%3a)/i', $path)) {
            $add('Tautan mengalihkan ke alamat lain', 1);
        }

        $level = $score >= 4 ? 'Tinggi' : ($score >= 1 ? 'Perlu diperiksa' : 'Belum terdeteksi');
        $advice = match ($level) {
            'Tinggi' => ['Jangan klik dan jangan isi data apa pun.', 'Tanyakan ke pengirim lewat jalur lain (telepon atau aplikasi resmi).', 'Jika sudah terlanjur klik, buka halaman Jam Emas.'],
            'Perlu diperiksa' => ['Jangan masukkan kata sandi, OTP, atau data kartu dari tautan ini.', 'Buka situs resminya dengan mengetik alamat sendiri atau lewat aplikasi resmi.'],
            default => ['Ini BUKAN berarti aman: pemeriksaan ini hanya membaca pola tulisan alamat, bukan reputasi situsnya.', 'Jangan isi data pribadi, OTP, atau kata sandi kecuali kamu yakin dengan pengirimnya.', 'Bandingkan juga lewat layanan pemeriksa lain, misalnya Google Safe Browsing.'],
        };

        return [
            'ok' => true,
            'candidate' => $candidate,
            'hostname' => $host,
            'score' => min(10, $score),
            'level' => $level,
            'severity' => self::severity($level),
            'indicators' => $indicators,
            'notes' => $notes,
            'advice' => $advice,
            'message' => 'Alamat tidak dibuka. Hasil ini bukan jaminan bahwa situs aman.',
        ];
    }

    /** @return array{ok:bool,message?:string,extension?:string,double_extension?:bool,executable_type?:bool,score?:int,level?:string,indicators?:array,advice?:array} */
    public static function file(string $input, ?string $detected = null): array
    {
        $name = trim(str_replace('\\', '/', $input));
        $name = trim(basename($name));
        if ($name === '' || mb_strlen($name) > 255) {
            return ['ok' => false, 'message' => 'Tulis nama file, misalnya Undangan.pdf.apk'];
        }

        $indicators = [];
        $score = 0;
        $add = function (string $text, int $points) use (&$indicators, &$score) {
            $indicators[] = $text;
            $score += $points;
        };

        // Karakter pembalik arah (RTL override) dipakai untuk menyamarkan ekstensi asli.
        if (preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $name)) {
            $add('Mengandung karakter pembalik arah teks yang dapat menyamarkan ekstensi asli', 3);
            $name = preg_replace('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $name);
        }

        $lower = mb_strtolower($name);
        $parts = explode('.', $lower);
        $extension = count($parts) > 1 ? trim((string) end($parts)) : '';
        $previous = count($parts) > 2 ? trim($parts[count($parts) - 2]) : '';

        $executable = in_array($extension, self::EXEC_EXT, true);
        $double = $previous !== '' && in_array($previous, self::SAFE_EXT, true) && ($executable || in_array($extension, self::MACRO_EXT, true));

        if ($double) {
            $add('Ekstensi ganda: tampak seperti .' . $previous . ' tetapi berakhir .' . $extension, 3);
        }
        if ($executable) {
            $add('.' . $extension . ' adalah jenis file yang dapat menjalankan program', $double ? 1 : 3);
        }
        if (in_array($extension, self::MACRO_EXT, true)) {
            $add('Dokumen berformat makro (.' . $extension . ') dapat menjalankan perintah tersembunyi', 2);
        }
        if (in_array($extension, self::ARCHIVE_EXT, true)) {
            $add('Arsip (.' . $extension . ') dapat menyembunyikan file berbahaya di dalamnya', 1);
        }
        if (preg_match('/\s{3,}\.[a-z0-9]+$/i', $name) || preg_match('/\s{5,}/', $name)) {
            $add('Banyak spasi kosong yang dapat menyembunyikan ekstensi di tampilan layar', 2);
        }
        if ($extension === '') {
            $add('Tidak ada ekstensi, jenis file tidak jelas', 1);
        }
        if (preg_match('/(invoice|tagihan|resi|undangan|surat|bukti|transfer|foto|paket|tilang|bpjs|pajak|lamaran)/i', $lower) && $executable) {
            $add('Nama terdengar seperti dokumen biasa, padahal jenis file dapat dijalankan', 1);
        }

        // Jenis asli dari byte awal file (dibaca di browser). Dibandingkan dengan ekstensi pada nama.
        $family = ['pdf' => ['pdf'], 'png' => ['png'], 'jpg' => ['jpg', 'jpeg'], 'gif' => ['gif'],
            'zip' => ['zip', 'docx', 'xlsx', 'pptx', 'apk', 'xapk', 'jar', 'odt', 'ods', 'epub'],
            'rar' => ['rar'], '7z' => ['7z'], 'gz' => ['gz', 'tgz']];
        if ($detected !== null && $detected !== 'unknown') {
            if (in_array($detected, ['exe', 'elf'], true)) {
                if (!in_array($extension, self::EXEC_EXT, true)) {
                    $add('Isi file ternyata program yang dapat dijalankan, tidak sesuai ekstensi' . ($extension ? ' .' . $extension : ''), 4);
                }
            } elseif ($detected === 'script') {
                if (!in_array($extension, self::EXEC_EXT, true) && !in_array($extension, ['txt', 'md', ''], true)) {
                    $add('Isi file berupa skrip yang dapat dijalankan, tidak sesuai ekstensi' . ($extension ? ' .' . $extension : ''), 2);
                }
            } elseif (isset($family[$detected]) && in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'docx', 'xlsx', 'pptx', 'zip', 'rar', '7z'], true)
                && !in_array($extension, $family[$detected], true)) {
                $add('Isi file bertipe ' . $detected . ', tidak sesuai ekstensi .' . $extension, 3);
            }
        }

        $level = $score >= 3 ? 'Tinggi' : ($score >= 1 ? 'Perlu diperiksa' : 'Belum terdeteksi');
        $advice = match ($level) {
            'Tinggi' => ['Jangan buka atau pasang file ini.', 'Konfirmasi ke pengirim lewat jalur lain sebelum melakukan apa pun.', 'Jika sudah terlanjur dibuka, putuskan internet dan buka halaman Jam Emas.'],
            'Perlu diperiksa' => ['Buka hanya jika kamu mengenal pengirim dan memang menunggu file ini.', 'Pindai dengan antivirus sebelum dibuka.'],
            default => ['Nama saja tidak cukup untuk memastikan file aman.', 'Tetap periksa pengirim dan konteks pesannya.'],
        };

        return [
            'ok' => true,
            'detected' => $detected,
            'filename' => $name,
            'extension' => $extension,
            'double_extension' => $double,
            'executable_type' => $executable,
            'score' => min(10, $score),
            'level' => $level,
            'severity' => self::severity($level),
            'indicators' => $indicators,
            'advice' => $advice,
            'message' => $double
                ? 'Ekstensi ganda terdeteksi. Jangan buka file sebelum memverifikasi pengirim.'
                : ($executable ? 'Jenis file perlu diwaspadai.' : 'Nama saja tidak cukup untuk memastikan file aman.'),
        ];
    }

    private static function severity(string $level): string
    {
        return $level === 'Tinggi' ? 'high' : ($level === 'Perlu diperiksa' ? 'medium' : 'none');
    }

    private static function registrable(array $labels): string
    {
        $n = count($labels);
        if ($n <= 2) {
            return implode('.', $labels);
        }
        $tld = $labels[$n - 1];
        $sld = $labels[$n - 2];
        if (strlen($tld) === 2 && in_array($sld, self::SLD, true)) {
            return implode('.', array_slice($labels, -3));
        }
        return implode('.', array_slice($labels, -2));
    }
}