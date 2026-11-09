<?php

namespace App\Services\Cek;

/**
 * Orkestrator pemeriksaan file (Tahap 4). Prinsip yang sama dengan Cek Tautan:
 *  - hasil berupa INDIKATOR RISIKO, bukan vonis aman/tidak;
 *  - pemeriksaan yang tidak berhasil dilaporkan sebagai "belum lengkap", tidak pernah dianggap aman;
 *  - skor dihitung oleh RiskScorer yang sama (tanda yang sama tidak dihitung dua kali, ada batas per kelompok).
 *
 * PRIVASI: isi file TIDAK PERNAH sampai ke server. Browser membaca file di perangkat pengguna (lihat
 * public/js/file-inspect.js) dan hanya mengirim: nama file, ukuran, jenis isi hasil deteksi, beberapa penanda
 * (mis. "PDF memuat JavaScript"), dan sidik jari SHA-256. Server menilai nama file sendiri (tidak mempercayai
 * browser untuk hal yang bisa dihitung dari nama) lalu mencocokkan SHA-256 ke VirusTotal.
 *
 * Penanda dari browser tidak bisa dipalsukan untuk merugikan siapa pun selain pengirimnya sendiri: hasilnya
 * hanya ditampilkan kepada pengguna yang sama.
 */
final class FileAnalysisService
{
    /** Penanda isi file yang boleh dikirim browser. Selain ini diabaikan. */
    public const FLAGS = [
        'archive_executable', 'archive_double_extension', 'archive_encrypted', 'archive_unreadable',
        'has_macro', 'pdf_javascript', 'pdf_launch', 'pdf_openaction', 'pdf_embedded',
        'html_password_form', 'html_external_form', 'svg_script', 'rtf_object', 'apk_unreadable',
    ];

    /** Jenis isi yang berarti "program atau pemasang". */
    private const EXECUTABLE_TYPES = ['pe', 'elf', 'macho', 'java', 'lnk', 'apk', 'jar'];

    /** Ekstensi → jenis isi yang wajar. Ekstensi yang tidak tercantum tidak diperiksa kecocokannya. */
    private const EXPECTED = [
        'pdf' => ['pdf'], 'jpg' => ['jpeg'], 'jpeg' => ['jpeg'], 'png' => ['png'], 'gif' => ['gif'],
        'webp' => ['webp'], 'bmp' => ['bmp'],
        'docx' => ['ooxml'], 'xlsx' => ['ooxml'], 'pptx' => ['ooxml'],
        'doc' => ['ole', 'ooxml', 'rtf', 'html'], 'xls' => ['ole', 'ooxml', 'html', 'text'], 'ppt' => ['ole', 'ooxml'],
        'zip' => ['zip', 'ooxml'], 'rar' => ['rar'], '7z' => ['7z'],
        'txt' => ['text'], 'csv' => ['text'],
    ];

    private const HTML_EXTENSIONS = ['html', 'htm', 'xhtml', 'shtml', 'mht', 'mhtml'];

    public function __construct(
        private readonly VirusTotalFileClient $virusTotal,
        private readonly RiskScorer $scorer,
    ) {}

    /**
     * @param  array{filename:string,size:int,sha256:?string,detected_type:?string,content_scanned:bool,flags:array<string,bool>}  $in
     * @return array<string,mixed>
     */
    public function analyze(array $in): array
    {
        $name = $this->normalizeName((string) $in['filename']);
        $type = in_array($in['detected_type'] ?? null, config('jeda_cek.file_types', []), true) ? $in['detected_type'] : 'unknown';
        $scanned = (bool) ($in['content_scanned'] ?? false);
        $flags = array_filter(array_intersect_key((array) ($in['flags'] ?? []), array_flip(self::FLAGS)));
        $size = max(0, (int) ($in['size'] ?? 0));
        $tooLarge = $size > (int) config('jeda_cek.max_file_bytes', 50 * 1024 * 1024);

        $signals = $this->nameSignals($name);
        if ($scanned) {
            $signals = array_merge($signals, $this->contentSignals($name, $type, $flags));
        }

        // --- APK: izin yang diminta (dibaca browser dari AndroidManifest.xml) ---------------------------
        $apk = null;
        if ($scanned && $type === 'apk') {
            $apk = $this->apkInfo($in['apk_permissions'] ?? null, $in['apk_package'] ?? null, ! empty($flags['apk_unreadable']));
            $signals = array_merge($signals, $this->apkSignals($apk, $this->matchesPretext($this->cleanName($name))));
        }

        // --- Pemeriksaan: pembacaan isi (dilakukan browser) ------------------------------------------
        $checks = [];
        if ($scanned) {
            $checks['content'] = $this->check('Pembacaan isi file (di browser)', 'ok', 'Header, struktur, dan sidik jari file dibaca di perangkatmu. File tidak diunggah.');
        } else {
            $checks['content'] = $this->check('Pembacaan isi file (di browser)', 'skipped', $tooLarge
                ? 'File lebih besar dari batas pemeriksaan, jadi hanya nama file yang diperiksa.'
                : 'Hanya nama file yang diperiksa. Pilih filenya agar isinya ikut dibaca di perangkatmu.');
        }

        if ($apk !== null) {
            $checks['apk_permissions'] = $apk['readable']
                ? $this->check('Izin aplikasi (APK)', 'ok', 'Daftar izin dibaca dari dalam APK di perangkatmu.')
                : $this->check('Izin aplikasi (APK)', 'skipped', 'Daftar izin tidak bisa dibaca dari APK ini.');
        }

        // --- Pemeriksaan: reputasi hash (VirusTotal) ---------------------------------------------------
        [$checks['virustotal'], $vtSignals] = $this->virusTotalStep($in['sha256'] ?? null);
        $signals = array_merge($signals, $vtSignals);

        $evaluation = $this->scorer->evaluate($signals, []);
        $coverage = $this->coverage($checks);
        $level = $evaluation['level'];
        $ext = $this->extensionOf($name);

        return [
            'score' => $evaluation['score'],
            'level' => $level,
            'signals' => $evaluation['signals'],
            'confirmed' => $evaluation['confirmed'],
            'coverage' => $coverage,
            'checks' => $checks,
            'message' => self::adviceFor($level),
            'coverage_note' => $coverage['status'] === 'lengkap' ? null : $this->coverageNote($coverage),
            'summary' => $this->summary($level, (bool) $evaluation['confirmed'], $evaluation['signals'], $checks, $type, $ext, $apk, $flags, $scanned),
            'apk' => $apk === null ? null : [
                'readable' => $apk['readable'], 'package' => $apk['package'],
                'permissions' => $apk['permissions'], 'other_count' => $apk['other_count'],
            ],
            'extension' => $ext,
            'double_extension' => in_array('double_extension', array_column($evaluation['signals'], 'id'), true),
            'executable_type' => $this->isExecutableName($this->extensionOf($name)),
            'detected_type' => $scanned ? $type : null,
            'size' => $size,
        ];
    }

    public static function adviceFor(string $level): string
    {
        return match ($level) {
            'Tinggi' => 'Sebaiknya jangan buka atau pasang file ini. Kalau sudah terlanjur, putuskan internet, jangan isi data apa pun, dan lihat langkah di halaman Jam Emas.',
            'Sedang' => 'Ada tanda yang perlu diwaspadai. Pastikan kamu kenal pengirimnya dan minta konfirmasi lewat jalur lain (telepon atau tatap muka) sebelum membuka file ini.',
            'Rendah' => 'Ada tanda kecil yang layak dicek ulang sebelum kamu membuka file ini.',
            default => 'Belum ada indikator dari pemeriksaan yang berhasil. Itu bukan jaminan file ini aman.',
        };
    }

    // ------------------------------------------------------------------------------------------------
    // Nama file
    // ------------------------------------------------------------------------------------------------

    /** Ambil nama saja (tanpa folder). Karakter pengecoh tetap dipertahankan supaya bisa dideteksi. */
    private function normalizeName(string $filename): string
    {
        $filename = str_replace('\\', '/', trim($filename));

        return basename($filename);
    }

    private function hasBidiControl(string $name): bool
    {
        return preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200E}\x{200F}]/u', $name) === 1;
    }

    /** Nama yang dipakai untuk membaca ekstensi: tanpa karakter kontrol/tak terlihat, huruf kecil, tanpa titik/spasi di akhir (Windows ikut membuangnya). */
    private function cleanName(string $name): string
    {
        $name = preg_replace('/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200B}-\x{200F}\x{FEFF}\p{Cc}]/u', '', $name) ?? $name;

        return strtolower(rtrim($name, " .\t"));
    }

    /** @return array<int,string> */
    private function extensions(string $name): array
    {
        $parts = explode('.', $this->cleanName($name));

        return count($parts) > 1 ? array_map('trim', array_slice($parts, 1)) : [];
    }

    private function extensionOf(string $name): string
    {
        $exts = $this->extensions($name);

        return $exts === [] ? '' : (string) end($exts);
    }

    private function isExecutableName(string $ext): bool
    {
        return $ext !== '' && (in_array($ext, config('jeda_cek.executable_extensions', []), true) || $ext === 'apk');
    }

    /** @return array<int,Signal> */
    private function nameSignals(string $name): array
    {
        $signals = [];
        $clean = $this->cleanName($name);
        $exts = $this->extensions($name);
        $ext = $exts === [] ? '' : (string) end($exts);
        $prev = count($exts) >= 2 ? (string) $exts[count($exts) - 2] : '';
        $isApk = $ext === 'apk';
        $isExec = $this->isExecutableName($ext) && ! $isApk;

        if ($this->hasBidiControl($name)) {
            $signals[] = new Signal('bidi_control', 'file_name', 'Nama file memuat karakter pembalik arah teks',
                'Karakter tak terlihat (seperti RLO) bisa membuat nama file tampak berakhir dengan .jpg atau .pdf padahal sebenarnya program. Ini hampir selalu disengaja.', 5);
        }

        if ($prev !== '' && ($isExec || $isApk) && in_array($prev, config('jeda_cek.decoy_extensions', []), true)) {
            $signals[] = new Signal('double_extension', 'file_name', 'Ekstensi ganda',
                "Nama file tampak seperti .$prev, tetapi sebenarnya berakhir .$ext, yaitu jenis yang menjalankan program.", 4);
        }

        if ($isApk) {
            $signals[] = new Signal('apk_file', 'file_name', 'Aplikasi Android (.apk)',
                'File .apk yang dikirim lewat chat atau tautan adalah cara umum menyebarkan aplikasi penipu atau pencuri data. Aplikasi resmi dipasang dari Play Store.', 4);
        } elseif ($isExec) {
            $signals[] = new Signal('executable_ext', 'file_name', "Jenis file .$ext menjalankan program",
                'File jenis ini bisa menjalankan program atau skrip saat dibuka. Ini bukan berarti berbahaya, tetapi risikonya jauh lebih besar daripada dokumen atau gambar.', 3);
        }

        if (in_array($ext, config('jeda_cek.macro_extensions', []), true)) {
            $signals[] = new Signal('macro_ext', 'file_name', 'Dokumen berformat makro',
                "Ekstensi .$ext menandakan dokumen yang boleh membawa makro (program kecil di dalam dokumen), cara lama yang masih sering dipakai menyebarkan malware.", 2);
        }

        if (in_array($ext, self::HTML_EXTENSIONS, true)) {
            $signals[] = new Signal('html_attachment', 'file_name', 'File halaman web (.'.$ext.')',
                'Halaman web yang dikirim sebagai file bisa meniru halaman login dan mengirim isiannya ke penipu. Situs sah jarang mengirim halamannya sebagai lampiran.', 1);
        }

        if (($isApk || $isExec) && $this->matchesPretext($clean)) {
            $signals[] = new Signal('scam_pretext_name', 'file_name', 'Nama file memakai alasan yang lazim dipakai penipu',
                'Nama seperti undangan, resi paket, tilang, atau tagihan sering dipakai untuk membujuk korban memasang aplikasi atau program berbahaya.', 3);
        }

        // Banyak spasi/titik pemanis sebelum ekstensi asli, atau titik/spasi di ujung nama.
        $raw = trim($name);
        if (preg_match('/ {5,}\.?[a-z0-9]{1,5}$/i', $raw) === 1 || preg_match('/[ .]+$/', $raw) === 1) {
            $signals[] = new Signal('name_padding', 'file_name', 'Nama file diberi spasi atau titik pengecoh',
                'Spasi panjang atau titik di ujung nama dipakai untuk menyembunyikan ekstensi asli pada layar yang sempit.', 2);
        }

        return $signals;
    }

    private function matchesPretext(string $cleanName): bool
    {
        $words = array_map(fn ($w) => preg_quote($w, '/'), config('jeda_cek.file_pretext_words', []));
        if ($words === []) {
            return false;
        }

        return preg_match('/(?<![a-z])('.implode('|', $words).')(?![a-z])/', $cleanName) === 1;
    }

    // ------------------------------------------------------------------------------------------------
    // Isi file (penanda dari browser)
    // ------------------------------------------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $flags
     * @return array<int,Signal>
     */
    private function contentSignals(string $name, string $type, array $flags): array
    {
        $signals = [];
        $ext = $this->extensionOf($name);
        $isProgram = in_array($type, self::EXECUTABLE_TYPES, true);
        $isScript = $type === 'script';
        $decoy = in_array($ext, config('jeda_cek.decoy_extensions', []), true);

        if ($decoy && ($isProgram || ($isScript && $this->scriptCanDisguise($ext)))) {
            $signals[] = new Signal('type_mismatch_executable', 'file_type', "Isinya program, bukan .$ext",
                "Nama file mengaku .$ext, tetapi isi sebenarnya adalah ".$this->typeLabel($type).'. File seperti ini sengaja disamarkan.', 7, floor: 7);
        } elseif ($isProgram && ! $this->isExecutableName($ext)) {
            $signals[] = new Signal('executable_content', 'file_type', 'Isinya program padahal ekstensinya tidak menunjukkan itu',
                'Isi file adalah '.$this->typeLabel($type).($ext !== '' ? ", tetapi ekstensinya .$ext" : ' dan tidak punya ekstensi').'.', 4);
        } elseif (isset(self::EXPECTED[$ext]) && ! in_array($type, self::EXPECTED[$ext], true)) {
            $signals[] = new Signal('type_mismatch_minor', 'file_type', 'Isi file tidak sesuai ekstensinya',
                "Ekstensinya .$ext, tetapi isi yang terbaca adalah ".$this->typeLabel($type).'. Bisa juga file yang rusak atau salah nama.', 1, needsOther: true);
        }

        if (! empty($flags['archive_executable'])) {
            $signals[] = new Signal('archive_executable', 'file_content', 'Arsip berisi program',
                'Di dalam arsip ada file yang menjalankan program (mis. .exe, .apk, .js, .bat).', 3);
        }
        if (! empty($flags['archive_double_extension'])) {
            $signals[] = new Signal('archive_double_extension', 'file_content', 'Arsip berisi file berekstensi ganda',
                'Di dalam arsip ada file yang tampak seperti dokumen atau gambar tetapi berakhir sebagai program (mis. tagihan.pdf.exe).', 3);
        }
        if (! empty($flags['archive_encrypted'])) {
            $signals[] = new Signal('archive_encrypted', 'file_content', 'Arsip dilindungi kata sandi',
                'Isi arsip tidak bisa dibaca tanpa sandi, sehingga tidak bisa diperiksa. Penipu memakainya agar lolos dari pemindai antivirus.', 2);
        }
        if (! empty($flags['has_macro'])) {
            $signals[] = new Signal('macro_content', 'file_content', 'Dokumen berisi makro',
                'Ditemukan komponen makro (VBA) di dalam dokumen. Jangan klik "Enable content" kalau tidak yakin dengan pengirimnya.', 3);
        }
        if (! empty($flags['pdf_launch'])) {
            $signals[] = new Signal('pdf_launch', 'file_content', 'PDF mencoba menjalankan program',
                'PDF ini memuat perintah untuk membuka atau menjalankan sesuatu di luar PDF.', 3);
        }
        if (! empty($flags['pdf_javascript'])) {
            $signals[] = new Signal('pdf_javascript', 'file_content', 'PDF memuat JavaScript',
                'PDF biasa jarang memakai JavaScript. Skrip di PDF kadang dipakai untuk menyerang pembaca PDF yang belum diperbarui.', 2);
        }
        if (! empty($flags['pdf_embedded'])) {
            $signals[] = new Signal('pdf_embedded', 'file_content', 'PDF membawa file lain di dalamnya',
                'Ada file yang disematkan di dalam PDF. Periksa baik-baik sebelum membukanya.', 2);
        }
        if (! empty($flags['pdf_openaction'])) {
            $signals[] = new Signal('pdf_openaction', 'file_content', 'PDF menjalankan aksi otomatis saat dibuka',
                'Banyak PDF sah memakainya untuk hal biasa, jadi ini hanya dihitung jika ada tanda lain.', 1, needsOther: true);
        }
        if (! empty($flags['html_password_form'])) {
            $signals[] = new Signal('html_password_form', 'file_content', 'Halaman meminta kata sandi',
                'File web ini memiliki kolom kata sandi. Halaman login yang dikirim sebagai file sering dipakai untuk mencuri akun.', 3);
        }
        if (! empty($flags['html_external_form'])) {
            $signals[] = new Signal('html_external_form', 'file_content', 'Form mengirim isian ke alamat lain',
                'File web ini punya form yang mengirim data ke alamat di internet saat disubmit.', 3);
        }
        if (! empty($flags['svg_script'])) {
            $signals[] = new Signal('svg_script', 'file_content', 'Gambar SVG berisi skrip',
                'Gambar biasa tidak membutuhkan skrip. SVG berskrip bisa dipakai untuk mengalihkan atau menjalankan kode saat dibuka di browser.', 3);
        }
        if (! empty($flags['rtf_object'])) {
            $signals[] = new Signal('rtf_object', 'file_content', 'Dokumen RTF menyematkan objek',
                'RTF yang menyematkan objek pernah sering dipakai menjalankan kode jahat lewat celah di aplikasi dokumen.', 3);
        }

        return $signals;
    }

    /** Skrip bershebang hanya dianggap penyamaran bila ekstensinya dokumen/gambar. */
    private function scriptCanDisguise(string $ext): bool
    {
        return in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'mp3', 'mp4', 'mov'], true);
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'pe' => 'program Windows',
            'elf' => 'program Linux',
            'macho' => 'program macOS',
            'java' => 'program Java',
            'lnk' => 'pintasan Windows (.lnk)',
            'script' => 'skrip',
            'apk' => 'aplikasi Android (APK)',
            'jar' => 'program Java (JAR)',
            'pdf' => 'PDF',
            'ole' => 'dokumen Office lama',
            'ooxml' => 'dokumen Office',
            'zip' => 'arsip ZIP',
            'rar' => 'arsip RAR',
            '7z' => 'arsip 7z',
            'gzip' => 'arsip gzip',
            'rtf' => 'dokumen RTF',
            'png' => 'gambar PNG',
            'jpeg' => 'gambar JPEG',
            'gif' => 'gambar GIF',
            'webp' => 'gambar WebP',
            'bmp' => 'gambar BMP',
            'html' => 'halaman web',
            'svg' => 'gambar SVG',
            'text' => 'teks biasa',
            default => 'jenis yang tidak dikenali',
        };
    }

    // ------------------------------------------------------------------------------------------------
    // APK: izin
    // ------------------------------------------------------------------------------------------------

    /**
     * @param  mixed  $permissions  semua izin yang terbaca dari manifest, atau null jika manifest tidak terbaca
     * @return array{readable:bool,package:?string,permissions:array<int,array{code:string,label:string,kind:string}>,other_count:int}
     */
    private function apkInfo(mixed $permissions, mixed $package, bool $unreadable): array
    {
        $known = config('jeda_cek.apk_permissions', []);
        $readable = ! $unreadable && is_array($permissions);
        $list = [];
        $other = 0;
        foreach ($readable ? array_unique($permissions) : [] as $code) {
            if (is_string($code) && isset($known[$code])) {
                $list[] = ['code' => $code, 'label' => $known[$code][0], 'kind' => $known[$code][1]];
            } else {
                $other++;
            }
        }
        // Yang paling berbahaya tampil lebih dulu.
        $rank = array_flip(['sms', 'accessibility', 'notification', 'overlay', 'device_admin', 'install', 'sensitive', 'weak', 'normal']);
        usort($list, fn ($a, $b) => ($rank[$a['kind']] ?? 99) <=> ($rank[$b['kind']] ?? 99));

        return [
            'readable' => $readable,
            'package' => is_string($package) && preg_match('/^[A-Za-z0-9_.]{1,150}$/', $package) === 1 ? $package : null,
            'permissions' => $list,
            'other_count' => $other,
        ];
    }

    /** @return array<int,Signal> */
    private function apkSignals(array $apk, bool $pretext): array
    {
        if (! $apk['readable']) {
            return [new Signal('apk_manifest_unreadable', 'apk_permission', 'Daftar izin aplikasi tidak bisa dibaca',
                'Aplikasi yang manifest-nya rusak atau disembunyikan sering sengaja dibuat agar sulit dianalisis.', 2)];
        }

        $points = config('jeda_cek.apk_permission_points', []);
        $byKind = [];
        foreach ($apk['permissions'] as $p) {
            $byKind[$p['kind']][] = $p['label'];
        }
        $why = $pretext ? ' Aplikasi dengan nama seperti ini tidak membutuhkan izin tersebut.' : '';
        $defs = [
            'sms' => ['apk_perm_sms', 'Bisa membaca atau mengirim SMS', 'Kode OTP bank dan akun dikirim lewat SMS. Aplikasi yang bisa membacanya dapat mengosongkan rekeningmu.'.$why],
            'accessibility' => ['apk_perm_accessibility', 'Meminta kendali atas layar (aksesibilitas)', 'Izin ini membuat aplikasi bisa membaca dan menekan layar aplikasi lain, termasuk m-banking. Ini izin yang paling sering disalahgunakan trojan perbankan.'.$why],
            'notification' => ['apk_perm_notification', 'Bisa membaca semua notifikasi', 'Notifikasi sering memuat kode OTP dan isi pesan pribadi.'.$why],
            'overlay' => ['apk_perm_overlay', 'Bisa tampil menimpa aplikasi lain', 'Dipakai untuk menempelkan halaman login palsu di atas aplikasi bank.'.$why],
            'device_admin' => ['apk_perm_device_admin', 'Meminta menjadi admin perangkat', 'Aplikasi yang jadi admin sulit dicopot oleh pemiliknya.'.$why],
            'install' => ['apk_perm_install', 'Bisa memasang aplikasi lain', 'Aplikasi penipu sering memasang aplikasi berbahaya tambahan secara diam-diam.'],
            'sensitive' => ['apk_perm_sensitive', 'Meminta akses data pribadi', 'Misalnya kontak, riwayat panggilan, mikrofon, kamera, atau lokasi. Pastikan memang dibutuhkan fungsi aplikasinya.'],
            'weak' => ['apk_perm_weak', 'Jalan terus di latar belakang', 'Banyak aplikasi sah memakainya, jadi ini hanya dihitung jika ada tanda lain.'],
        ];
        $signals = [];
        foreach ($defs as $kind => [$id, $label, $detail]) {
            if (empty($byKind[$kind])) {
                continue;
            }
            $signals[] = new Signal($id, 'apk_permission', $label, $detail.' Izin yang diminta: '.implode('; ', $byKind[$kind]).'.',
                (int) ($points[$kind] ?? 1), needsOther: $kind === 'weak');
        }

        return $signals;
    }

    // ------------------------------------------------------------------------------------------------
    // Ringkasan hasil untuk pengguna
    // ------------------------------------------------------------------------------------------------

    /**
     * Ringkasan yang dibaca manusia: apa kesimpulannya, kenapa, apa yang sudah dicek dan tidak ditemukan,
     * apa yang belum bisa dicek, dan langkah yang sebaiknya dilakukan. Semuanya turunan dari hasil pemeriksaan
     * (bukan karangan), sehingga selalu konsisten dengan skor.
     *
     * @param  array<int,array<string,mixed>>  $signals
     * @param  array<string,array<string,mixed>>  $checks
     * @return array{kind:string,headline:string,reasons:array<int,string>,passed:array<int,string>,unchecked:array<int,array{label:string,note:?string}>,steps:array<int,string>}
     */
    private function summary(string $level, bool $confirmed, array $signals, array $checks, string $type, string $ext, ?array $apk, array $flags, bool $scanned): array
    {
        $ids = array_column($signals, 'id');
        $has = fn (string ...$x) => array_intersect($x, $ids) !== [];
        $kind = match (true) {
            $type === 'apk' || $ext === 'apk' => 'apk',
            in_array($type, ['pe', 'elf', 'macho', 'java', 'lnk', 'jar'], true) || ($this->isExecutableName($ext) && $ext !== 'apk') => 'program',
            in_array($type, ['html', 'svg'], true) || in_array($ext, self::HTML_EXTENSIONS, true) => 'web',
            in_array($type, ['zip', 'rar', '7z', 'gzip'], true) => 'archive',
            default => 'document',
        };

        // Kesimpulan
        $incomplete = ($checks['content']['state'] ?? 'ok') !== 'ok' || ($checks['virustotal']['state'] ?? 'ok') !== 'ok';
        $headline = match (true) {
            $confirmed => 'Banyak mesin keamanan menandai file ini berbahaya',
            $level === 'Tinggi' && $kind === 'apk' => 'Jangan pasang aplikasi ini',
            $level === 'Tinggi' && $kind === 'program' => 'Jangan jalankan file ini',
            $level === 'Tinggi' => 'Jangan buka file ini',
            $level === 'Sedang' => 'Hati-hati sebelum membuka file ini',
            $level === 'Rendah' => 'Ada tanda kecil, cek ulang dulu',
            $incomplete => 'Belum ada tanda bahaya, tetapi pemeriksaan belum lengkap',
            default => 'Tidak ada tanda bahaya yang terdeteksi',
        };

        // Alasan: tanda dengan poin terbesar
        $scored = array_filter($signals, fn ($s) => $s['points'] > 0);
        usort($scored, fn ($a, $b) => $b['points'] <=> $a['points']);
        $reasons = array_map(fn ($s) => $s['label'], array_slice($scored, 0, 3));

        // Yang sudah diperiksa dan tidak ditemukan
        $passed = [];
        if (! $has('bidi_control', 'double_extension', 'name_padding')) {
            $passed[] = 'Nama file tidak memakai trik ekstensi ganda atau karakter tersembunyi.';
        }
        if ($scanned) {
            if (! $has('type_mismatch_executable', 'executable_content', 'type_mismatch_minor') && isset(self::EXPECTED[$ext])) {
                $passed[] = "Isi file sesuai dengan ekstensinya (.$ext).";
            }
            if ($type === 'pdf' && ! $has('pdf_javascript', 'pdf_launch', 'pdf_embedded', 'pdf_openaction')) {
                $passed[] = 'PDF tidak memuat JavaScript, peluncur program, atau file tersembunyi.';
            }
            if (in_array($type, ['ooxml', 'ole'], true) && ! $has('macro_content')) {
                $passed[] = 'Tidak ditemukan makro di dalam dokumen.';
            }
            if ($type === 'zip' && ! $has('archive_executable', 'archive_double_extension', 'archive_encrypted')) {
                $passed[] = 'Arsip tidak berisi program, file berekstensi ganda, atau sandi.';
            }
            if (in_array($type, ['html', 'svg'], true) && ! $has('html_password_form', 'html_external_form', 'svg_script')) {
                $passed[] = 'Tidak ada kolom kata sandi, form ke alamat lain, atau skrip mencurigakan.';
            }
            if ($apk !== null && $apk['readable'] && ! $has('apk_perm_sms', 'apk_perm_accessibility', 'apk_perm_notification', 'apk_perm_overlay', 'apk_perm_device_admin', 'apk_perm_install')) {
                $passed[] = 'Aplikasi tidak meminta izin yang biasa disalahgunakan penipu (SMS, aksesibilitas, notifikasi, menimpa layar, admin perangkat, memasang aplikasi).';
            }
        }
        if (($checks['virustotal']['result'] ?? null) === 'clean') {
            $passed[] = $checks['virustotal']['note'];
        }

        // Yang belum bisa dicek
        $unchecked = [];
        foreach ($checks as $c) {
            if ($c['state'] !== 'ok') {
                $unchecked[] = ['label' => $c['label'], 'note' => $c['note'] ?? null];
            }
        }
        if (($checks['virustotal']['result'] ?? null) === 'unknown') {
            $unchecked[] = ['label' => 'Reputasi di VirusTotal', 'note' => 'File ini belum pernah dianalisis VirusTotal. Itu wajar untuk file baru dan bukan berarti aman.'];
        }
        if (! empty($flags['archive_unreadable'])) {
            $unchecked[] = ['label' => 'Isi arsip', 'note' => 'Daftar isi arsip tidak bisa dibaca, jadi isinya tidak diperiksa.'];
        }
        if (in_array('archive_encrypted', $ids, true)) {
            $unchecked[] = ['label' => 'Isi arsip', 'note' => 'Arsip dilindungi sandi, jadi isinya tidak bisa diperiksa.'];
        }

        return [
            'kind' => $kind,
            'headline' => $headline,
            'reasons' => $reasons,
            'passed' => array_values(array_unique($passed)),
            'unchecked' => $unchecked,
            'steps' => $this->stepsFor($level, $kind),
        ];
    }

    /** @return array<int,string> */
    private function stepsFor(string $level, string $kind): array
    {
        if ($level === 'Tinggi') {
            $steps = $kind === 'apk'
                ? ['Jangan pasang aplikasi ini. Hapus filenya dari HP.', "Kalau HP meminta izin memasang dari 'sumber tidak dikenal', jangan diizinkan."]
                : ['Jangan buka atau jalankan file ini. Hapus filenya.'];
            $steps[] = 'Tanyakan ke pengirim lewat telepon atau bertemu langsung, bukan lewat chat yang sama. Akunnya bisa saja sudah diretas.';
            $steps[] = $kind === 'apk'
                ? 'Kalau sudah terlanjur terpasang: aktifkan mode pesawat, copot aplikasinya di Setelan > Aplikasi, ganti kata sandi m-banking dan e-mail dari perangkat lain, lalu hubungi bank lewat nomor resmi. Lihat halaman Jam Emas.'
                : 'Kalau sudah terlanjur dibuka: putuskan internet, jalankan antivirus, dan ganti kata sandi penting dari perangkat lain. Lihat halaman Jam Emas.';

            return $steps;
        }
        if ($level === 'Sedang') {
            return [
                'Pastikan kamu kenal pengirimnya dan memang sedang menunggu file ini.',
                'Konfirmasi lewat jalur lain (telepon atau bertemu) sebelum membuka.',
                'Kalau ragu, jangan dibuka. Bertanya dulu tidak merugikan.',
            ];
        }
        if ($level === 'Rendah') {
            return [
                'Buka hanya kalau kamu mengenal pengirimnya.',
                'Pastikan aplikasi pembacanya (PDF, Office, antivirus) sudah diperbarui.',
            ];
        }

        return [
            'Hasil ini bukan jaminan aman. Tetap buka hanya file dari pengirim yang kamu kenal.',
            'Pastikan aplikasi pembacanya (PDF, Office, antivirus) sudah diperbarui.',
        ];
    }

    // ------------------------------------------------------------------------------------------------
    // VirusTotal (hash)
    // ------------------------------------------------------------------------------------------------

    /** @return array{0:array,1:array<int,Signal>} */
    private function virusTotalStep(?string $sha256): array
    {
        $label = 'VirusTotal (sidik jari file)';
        if ($sha256 === null || preg_match('/^[a-f0-9]{64}$/i', $sha256) !== 1) {
            return [$this->check($label, 'skipped', 'Sidik jari (SHA-256) file tidak tersedia, jadi tidak dicocokkan ke VirusTotal.', ['result' => null]), []];
        }

        $r = $this->virusTotal->lookup($sha256);

        if (in_array($r['state'], ['unavailable', 'not_configured'], true)) {
            return [$this->check($label, $r['state'], $this->serviceNote($r['state'], $r['reason']), ['result' => null]), []];
        }
        if ($r['state'] === 'pending') {
            return [$this->check($label, 'pending', 'Analisis VirusTotal untuk file ini belum selesai. Periksa lagi beberapa saat lagi.', ['result' => null]), []];
        }
        if ($r['result'] === 'unknown') {
            return [$this->check($label, 'ok', 'File yang persis sama belum pernah dianalisis VirusTotal. Itu wajar untuk file baru, dan bukan berarti aman. Jeda tidak mengunggah file ke VirusTotal.', ['result' => 'unknown']), []];
        }
        if ($r['result'] === 'clean') {
            return [$this->check($label, 'ok', "{$r['total']} mesin memeriksa file yang persis sama ini, tidak ada yang menandai berbahaya.", ['result' => 'clean', 'total' => $r['total']]), []];
        }

        $hits = $r['malicious'] + $r['suspicious'];
        $family = $r['label'] ? " (dikenali sebagai: {$r['label']})" : '';
        $check = $this->check($label, 'ok', "$hits dari {$r['total']} mesin menandai file ini$family.", ['result' => 'flagged', 'malicious' => $r['malicious'], 'suspicious' => $r['suspicious'], 'total' => $r['total']]);

        // Satu atau dua mesin saja sering keliru. Baru terkonfirmasi jika banyak mesin sepakat.
        if ($r['malicious'] >= 3) {
            return [$check, [new Signal('virustotal_file_malicious', 'reputation', 'Banyak mesin VirusTotal menandai file ini berbahaya',
                "$hits dari {$r['total']} mesin keamanan menandai file yang persis sama ini$family.", 9, floor: 9)]];
        }

        return [$check, [new Signal('virustotal_file_some', 'reputation', 'Sebagian kecil mesin VirusTotal menandai file ini mencurigakan',
            "$hits dari {$r['total']} mesin menandai file ini. Satu atau dua mesin saja sering keliru, jadi ini bukan bukti kuat.", 3)]];
    }

    private function serviceNote(string $state, ?string $reason): string
    {
        if ($state === 'not_configured') {
            return 'Belum diaktifkan di server ini (API key belum diisi).';
        }

        return match ($reason) {
            'rate_limited' => 'Batas permintaan VirusTotal tercapai. Coba lagi beberapa menit lagi.',
            'access_denied' => 'VirusTotal menolak permintaan dari server Jeda.',
            'invalid_request' => 'Sidik jari file tidak valid, jadi tidak dicocokkan ke VirusTotal.',
            'server_error' => 'Layanan VirusTotal sedang bermasalah.',
            default => 'Layanan VirusTotal tidak dapat dihubungi (jaringan atau batas waktu).',
        };
    }

    // ------------------------------------------------------------------------------------------------
    // Kelengkapan
    // ------------------------------------------------------------------------------------------------

    /**
     * lengkap = isi file terbaca DAN reputasi hash berhasil dicek;
     * sebagian = salah satunya belum; terbatas = tidak ada yang berhasil (hanya nama file).
     *
     * @param  array<string,array{label:string,state:string,note?:?string}>  $checks
     * @return array{status:string,missing:array<int,array{label:string,note:?string}>}
     */
    private function coverage(array $checks): array
    {
        $missing = [];
        foreach (['content', 'apk_permissions', 'virustotal'] as $key) {
            if (isset($checks[$key]) && $checks[$key]['state'] !== 'ok') {
                $missing[] = ['label' => $checks[$key]['label'], 'note' => $checks[$key]['note'] ?? null];
            }
        }
        $status = $missing === [] ? 'lengkap' : (isset($checks['content']) && $checks['content']['state'] !== 'ok' && ($checks['virustotal']['state'] ?? '') !== 'ok' ? 'terbatas' : 'sebagian');

        return ['status' => $status, 'missing' => $missing];
    }

    private function coverageNote(array $coverage): string
    {
        $labels = implode(', ', array_column($coverage['missing'], 'label'));

        return "Pemeriksaan belum lengkap ($labels). Hasil di atas hanya berdasarkan pemeriksaan yang berhasil, jadi jangan dianggap aman.";
    }

    private function check(string $label, string $state, ?string $note = null, array $extra = []): array
    {
        return ['label' => $label, 'state' => $state, 'note' => $note] + $extra;
    }
}
