<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi fitur Cek (tautan dan file)
|--------------------------------------------------------------------------
| Dipakai oleh App\Services\Cek\*. Ubah angka dan daftar di sini, bukan di kode.
| PENTING: daftar domain resmi adalah contoh awal. Verifikasi ulang sebelum dipakai di demo.
*/

return [
    // Batas ukuran file yang diperiksa di browser (byte). Dipakai di tahap pemeriksaan file.
    'max_file_bytes' => 50 * 1024 * 1024,

    // Pembukaan tautan oleh server (redirect dan isi halaman). Lihat RedirectTracer.
    'redirect' => [
        'max_hops' => 5,            // maksimal pengalihan yang diikuti
        'total_seconds' => 10,      // batas waktu seluruh rantai
        'hop_timeout' => 5,         // batas waktu per permintaan
        'max_body_bytes' => 262144, // isi halaman yang dibaca (256 KB)
        'abort_after_bytes' => 1572864, // unduhan dihentikan jika melewati 1,5 MB
        'allowed_ports' => [80, 443],
    ],

    // Batas pemakaian API gratis. VirusTotal gratis: 4 permintaan/menit dan 500/hari.
    'virustotal_limits' => ['per_minute' => 4, 'per_day' => 450],

    // Batas bobot per kelompok sinyal (agar satu jenis tanda tidak mendominasi skor).
    'group_caps' => [
        'url_pattern' => 6,
        'keyword' => 1,
        'page' => 6,
        'redirect' => 3,
        'domain' => 2,
        'reputation' => 10,
        // Pemeriksaan file (Tahap 4).
        'file_name' => 7,
        'file_type' => 7,
        'file_content' => 6,
    ],

    // Akhiran dua tingkat agar domain utama terbaca benar (tanpa Public Suffix List).
    'second_level_suffixes' => [
        'co.id', 'go.id', 'or.id', 'ac.id', 'web.id', 'sch.id', 'my.id', 'biz.id',
        'net.id', 'mil.id', 'desa.id', 'ponpes.id',
        'co.uk', 'org.uk', 'com.au', 'co.jp', 'co.in', 'com.br', 'com.sg', 'com.my',
    ],

    // Akhiran yang sering dipakai situs sekali pakai. Bobot kecil karena banyak yang sah.
    'suspicious_tlds' => [
        'xyz', 'top', 'click', 'icu', 'buzz', 'cfd', 'sbs', 'monster', 'rest',
        'gq', 'tk', 'ml', 'cf', 'ga', 'cyou', 'lol',
    ],

    // Pemendek tautan: tujuan asli tersembunyi.
    'shorteners' => [
        'bit.ly', 'tinyurl.com', 's.id', 'cutt.ly', 't.ly', 'rebrand.ly', 'is.gd',
        'shorturl.at', 'bit.do', 'ow.ly', 'goo.gl', 'rb.gy', 'tiny.cc', 'wa.link',
    ],

    // Kata pertama subdomain yang lazim dipakai tautan pengalih.
    'redirector_labels' => ['links', 'link', 'go', 'out', 'click', 'track', 'redir', 'redirect', 'r', 'l'],

    // Merek yang sering ditiru. keywords: dicocokkan ke potongan nama domain.
    // domains: domain utama resmi (tanpa subdomain).
    'brands' => [
        'BCA' => ['keywords' => ['bca', 'klikbca', 'mybca'], 'domains' => ['bca.co.id', 'klikbca.com']],
        'BRI' => ['keywords' => ['bri', 'brimo'], 'domains' => ['bri.co.id']],
        'BNI' => ['keywords' => ['bni'], 'domains' => ['bni.co.id']],
        'Mandiri' => ['keywords' => ['mandiri'], 'domains' => ['bankmandiri.co.id']],
        'Shopee' => ['keywords' => ['shopee'], 'domains' => ['shopee.co.id', 'shopee.com']],
        'Tokopedia' => ['keywords' => ['tokopedia'], 'domains' => ['tokopedia.com']],
        'Gojek / GoPay' => ['keywords' => ['gojek', 'gopay'], 'domains' => ['gojek.com', 'gopay.co.id']],
        'DANA' => ['keywords' => ['dana'], 'domains' => ['dana.id']],
        'OVO' => ['keywords' => ['ovo'], 'domains' => ['ovo.id']],
        'BPJS' => ['keywords' => ['bpjs'], 'domains' => ['bpjs-kesehatan.go.id', 'bpjsketenagakerjaan.go.id']],
        'Telkomsel' => ['keywords' => ['telkomsel'], 'domains' => ['telkomsel.com']],
        'Komdigi' => ['keywords' => ['komdigi', 'kominfo'], 'domains' => ['komdigi.go.id', 'kominfo.go.id']],
        'WhatsApp' => ['keywords' => ['whatsapp'], 'domains' => ['whatsapp.com', 'whatsapp.net', 'wa.me']],
        'Instagram' => ['keywords' => ['instagram'], 'domains' => ['instagram.com', 'cdninstagram.com']],
        'Facebook' => ['keywords' => ['facebook'], 'domains' => ['facebook.com', 'facebookmail.com', 'fb.com', 'fb.me']],
        'Google' => ['keywords' => ['google'], 'domains' => ['google.com', 'google.co.id', 'googleapis.com', 'googleusercontent.com']],
        'YouTube' => ['keywords' => ['youtube'], 'domains' => ['youtube.com', 'youtu.be']],
        'TikTok' => ['keywords' => ['tiktok'], 'domains' => ['tiktok.com']],
        'PayPal' => ['keywords' => ['paypal'], 'domains' => ['paypal.com']],
    ],

    // Penjelasan AI (Tahap 3). Kunci dan model dibaca dari config/services.php (GEMINI_API_KEY, GEMINI_MODEL).
    'gemini' => [
        'timeout' => 20,
        'tries' => 2,              // 1 percobaan awal + 1 ulang untuk 429/500/503
        'retry_sleep_ms' => 800,
        'max_output_tokens' => 2048,
    ],

    // ---------------------------------------------------------------------------------------------
    // Pemeriksaan file (Tahap 4). File TIDAK diunggah: browser membaca isinya sendiri, lalu hanya
    // mengirim ringkasan + SHA-256 ke server. Daftar di bawah dipakai server DAN browser (lewat halaman /cek).
    // ---------------------------------------------------------------------------------------------

    // Jenis file yang menjalankan program/skrip bila dibuka. (apk dipisah karena punya aturan sendiri.)
    'executable_extensions' => [
        'exe', 'bat', 'cmd', 'msi', 'scr', 'vbs', 'vbe', 'js', 'jse', 'ps1',
        'jar', 'lnk', 'hta', 'com', 'dll', 'wsf', 'cpl', 'reg', 'msix', 'appx', 'iso',
    ],

    // Ekstensi yang tampak "aman" di mata korban. Dipakai untuk mendeteksi ekstensi ganda (laporan.pdf.exe).
    'decoy_extensions' => [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'mp3', 'mp4', 'mov', 'zip', 'rar',
    ],

    // Format dokumen yang bisa membawa makro.
    'macro_extensions' => ['docm', 'xlsm', 'pptm', 'dotm', 'xltm', 'xlam', 'ppam', 'xlsb'],

    // Kata-kata alasan (pretext) yang lazim dipakai penipuan file di Indonesia: undangan, resi, tilang, dll.
    // Hanya dihitung jika file-nya berupa APK atau program.
    'file_pretext_words' => [
        'undangan', 'pernikahan', 'walimah', 'resi', 'paket', 'kurir', 'jne', 'jnt', 'sicepat', 'anteraja',
        'tilang', 'etilang', 'pajak', 'bpjs', 'tagihan', 'faktur', 'surat', 'panggilan', 'sidang', 'polisi',
        'kejaksaan', 'lamaran', 'hadiah', 'undian', 'pemenang', 'bansos', 'bantuan', 'kemensos',
        'pengumuman', 'mutasi', 'rekening', 'bukti', 'transfer', 'mbanking', 'update', 'foto', 'video',
    ],

    // Jenis isi file yang dikenali browser (dari byte awal). Server hanya menerima nilai di daftar ini.
    'file_types' => [
        'pe', 'elf', 'macho', 'java', 'lnk', 'script', 'apk', 'jar', 'pdf', 'ole', 'ooxml', 'zip', 'rar', '7z',
        'gzip', 'rtf', 'png', 'jpeg', 'gif', 'webp', 'bmp', 'html', 'svg', 'text', 'unknown',
    ],
];
