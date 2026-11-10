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
        'apk_permission' => 7,
    ],

    // Akhiran dua tingkat agar domain utama terbaca benar (tanpa Public Suffix List).
    'second_level_suffixes' => [
        'co.id', 'go.id', 'or.id', 'ac.id', 'web.id', 'sch.id', 'my.id', 'biz.id',
        'net.id', 'mil.id', 'desa.id', 'ponpes.id',
        'co.uk', 'org.uk', 'com.au', 'co.jp', 'co.in', 'com.br', 'com.sg', 'com.my',
    ],

    // Layanan hosting/pembuat situs gratis: siapa saja bisa membuat halaman di subdomainnya, jadi domain utamanya
    // dihitung termasuk satu label di depannya (mis. bca-login.pages.dev, bukan pages.dev). Umur domain RDAP
    // tidak dipakai untuk alamat seperti ini karena yang tua adalah penyedianya, bukan halamannya.
    'free_hosting_suffixes' => [
        'pages.dev', 'workers.dev', 'github.io', 'gitlab.io', 'vercel.app', 'netlify.app', 'web.app', 'firebaseapp.com',
        'blogspot.com', 'weebly.com', 'wixsite.com', 'webflow.io', 'myshopify.com', 'herokuapp.com', 'onrender.com',
        'glitch.me', 'repl.co', 'replit.app', 'surge.sh', 'carrd.co', 'godaddysites.com', 'wordpress.com',
        'ngrok.io', 'ngrok-free.app', 'trycloudflare.com', 'duckdns.org', 'infinityfreeapp.com', '000webhostapp.com',
        'azurewebsites.net', 'framer.app', 'canva.site', 'notion.site', 'typedream.app',
    ],

    // Host milik merek resmi yang ISINYA bisa dibuat siapa saja (berbagi dokumen, formulir, penyimpanan) atau yang
    // hanya mengalihkan ke tujuan lain. Domainnya resmi, tetapi itu TIDAK membuktikan isinya aman, jadi tautan ke
    // host ini tetap dilacak dan halamannya tetap dibaca. Dicocokkan ke nama host persis atau subdomainnya.
    'shared_platform_hosts' => [
        'docs.google.com', 'drive.google.com', 'sites.google.com', 'script.google.com', 'sheets.google.com',
        'storage.googleapis.com', 'firebasestorage.googleapis.com', 'googleusercontent.com',
        'wa.me', 'fb.me', 'fb.com', 'youtu.be', 'l.facebook.com', 'lm.facebook.com', 'l.instagram.com',
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

    // Izin Android yang dikenali. Browser membaca SEMUA izin dari dalam APK, tetapi server hanya menilai yang tercantum di sini.
    // kind: sms | accessibility | notification | overlay | device_admin | install | sensitive | weak | normal
    'apk_permissions' => [
        'android.permission.READ_SMS' => ['Membaca SMS (termasuk kode OTP)', 'sms'],
        'android.permission.RECEIVE_SMS' => ['Menerima SMS (termasuk kode OTP)', 'sms'],
        'android.permission.SEND_SMS' => ['Mengirim SMS atas namamu (bisa memotong pulsa)', 'sms'],
        'android.permission.BIND_ACCESSIBILITY_SERVICE' => ['Membaca dan mengendalikan layar aplikasi lain', 'accessibility'],
        'android.permission.BIND_NOTIFICATION_LISTENER_SERVICE' => ['Membaca semua notifikasi (termasuk OTP)', 'notification'],
        'android.permission.SYSTEM_ALERT_WINDOW' => ['Tampil menimpa aplikasi lain (bisa menyamar jadi halaman bank)', 'overlay'],
        'android.permission.BIND_DEVICE_ADMIN' => ['Menjadi admin perangkat (sulit dicopot)', 'device_admin'],
        'android.permission.REQUEST_INSTALL_PACKAGES' => ['Memasang aplikasi lain', 'install'],
        'android.permission.READ_CONTACTS' => ['Membaca kontak', 'sensitive'],
        'android.permission.READ_CALL_LOG' => ['Membaca riwayat panggilan', 'sensitive'],
        'android.permission.RECORD_AUDIO' => ['Merekam suara', 'sensitive'],
        'android.permission.CAMERA' => ['Memakai kamera', 'sensitive'],
        'android.permission.ACCESS_FINE_LOCATION' => ['Mengetahui lokasi tepat', 'sensitive'],
        'android.permission.MANAGE_EXTERNAL_STORAGE' => ['Mengakses semua file di HP', 'sensitive'],
        'android.permission.PROCESS_OUTGOING_CALLS' => ['Memantau panggilan keluar', 'sensitive'],
        'android.permission.RECEIVE_BOOT_COMPLETED' => ['Jalan otomatis saat HP menyala', 'weak'],
        'android.permission.QUERY_ALL_PACKAGES' => ['Melihat daftar semua aplikasi terpasang', 'weak'],
        'android.permission.REQUEST_IGNORE_BATTERY_OPTIMIZATIONS' => ['Menolak dihemat baterai (tetap jalan di latar)', 'weak'],
        'android.permission.INTERNET' => ['Mengakses internet', 'normal'],
        'android.permission.ACCESS_NETWORK_STATE' => ['Melihat status jaringan', 'normal'],
        'android.permission.VIBRATE' => ['Getar', 'normal'],
        'android.permission.WAKE_LOCK' => ['Menjaga HP tetap menyala', 'normal'],
    ],

    // Poin per jenis izin berbahaya (dijumlahkan, dibatasi group_caps.apk_permission).
    'apk_permission_points' => [
        'sms' => 3, 'accessibility' => 4, 'notification' => 3, 'overlay' => 3, 'device_admin' => 3,
        'install' => 2, 'sensitive' => 2, 'weak' => 1,
    ],
];
