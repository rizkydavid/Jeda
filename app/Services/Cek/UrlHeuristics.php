<?php

namespace App\Services\Cek;

/**
 * Pemeriksaan pola alamat secara lokal (tanpa API, tanpa membuka tautan).
 * Tanda lemah seperti kata "login" hanya dihitung jika ada tanda lain (lihat RiskScorer).
 */
final class UrlHeuristics
{
    /**
     * Merapikan dan memvalidasi input. Mengembalikan null jika bukan alamat http(s) yang valid.
     *
     * @return array{host:string,registrable:string,port:?int,scheme:string,path:string,query:?string,
     *   explicit_http:bool,has_userinfo:bool,is_ip:bool,safe_url:string,fetch_url:string}|null
     */
    public function parse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '' || preg_match('/\s/u', $raw) === 1) {
            return null;
        }

        $hasScheme = preg_match('~^[a-z][a-z0-9+.\-]*://~i', $raw) === 1;
        if ($hasScheme && preg_match('~^https?://~i', $raw) !== 1) {
            return null; // ftp://, file://, dll.
        }

        $parts = parse_url($hasScheme ? $raw : 'https://'.$raw);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        // Huruf non-ASCII diubah ke punycode agar tipuan huruf mirip (homograf) terdeteksi.
        if (preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
            $ascii = function_exists('idn_to_ascii')
                ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
                : false;
            if (! is_string($ascii) || $ascii === '') {
                return null;
            }
            $host = strtolower($ascii);
        }

        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        if (! $isIp) {
            if (strlen($host) > 253 || ! str_contains($host, '.')) {
                return null;
            }
            $labels = explode('.', $host);
            foreach ($labels as $label) {
                if ($label === '' || strlen($label) > 63 || $label[0] === '-' || str_ends_with($label, '-')) {
                    return null;
                }
            }
            if (ctype_digit(end($labels))) {
                return null;
            }
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $path = $parts['path'] ?? '/';
        $query = $parts['query'] ?? null;
        $authority = $host.($port ? ':'.$port : '');

        return [
            'host' => $host,
            'registrable' => $isIp ? $host : $this->registrableOf($host),
            'port' => $port,
            'scheme' => $scheme,
            'path' => $path,
            'query' => $query,
            'explicit_http' => $hasScheme && $scheme === 'http',
            'has_userinfo' => isset($parts['user']),
            'is_ip' => $isIp,
            // Untuk Safe Browsing: tanpa parameter (?...), fragmen (#...), dan user:pass@.
            'safe_url' => $scheme.'://'.$authority.$path,
            // Untuk dibuka server dan dihitung hash-nya: tanpa fragmen dan user:pass@.
            'fetch_url' => $scheme.'://'.$authority.$path.($query !== null ? '?'.$query : ''),
        ];
    }

    /**
     * @param  array<string,mixed>  $p  hasil parse()
     * @param  string|null  $where  keterangan tambahan, mis. "tujuan akhir" saat memeriksa tujuan redirect
     * @return array{signals: array<int,Signal>, official: array{brand:string,domain:string}|null}
     */
    public function analyze(array $p, ?string $where = null): array
    {
        $signals = [];
        $host = $p['host'];
        $note = $where ? " (pada $where)" : '';
        $official = $p['is_ip'] ? null : $this->matchOfficial($p['registrable']);
        if ($official !== null && $this->isSharedPlatform($host)) {
            $official['shared'] = true;
        }
        $shared = $official !== null && ! empty($official['shared']);

        if ($p['has_userinfo']) {
            $signals[] = new Signal('userinfo_at', 'url_pattern', 'Ada tanda @ di alamat',
                "Teks sebelum tanda @ hanya pengecoh. Tujuan sebenarnya adalah $host.$note", 3);
        }

        if ($p['is_ip']) {
            $signals[] = new Signal('ip_host', 'url_pattern', 'Alamat berupa angka IP',
                'Situs resmi hampir selalu memakai nama domain, bukan angka IP langsung.'.$note, 3);
        }

        if (str_contains($host, 'xn--')) {
            $signals[] = new Signal('punycode', 'url_pattern', 'Domain memakai karakter khusus (punycode)',
                'Huruf yang tampak mirip bisa dipakai untuk menyamar sebagai merek lain.'.$note, 3);
        }

        if ($shared) {
            $signals[] = new Signal('shared_platform', 'info', 'Layanan berbagi konten milik '.$official['brand'],
                "$host milik {$official['brand']}, tetapi isinya (dokumen, formulir, halaman, atau tujuan pengalihan) bisa dibuat siapa saja. Domain resmi tidak membuktikan isinya aman.".$note, 0);
            $owner = $official['brand'];
            // Path satu segmen (wa.me/…, youtu.be/…) hanyalah kode pendek, bukan nama halaman; tidak dinilai.
            $pathBrand = substr_count(trim($p['path'], '/'), '/') >= 1 ? $this->brandInPath($p['path'], $owner) : null;
            if ($pathBrand !== null) {
                $signals[] = new Signal('brand_in_path', 'url_pattern', 'Nama '.$pathBrand.' muncul di halaman layanan berbagi',
                    "Nama $pathBrand ada di alamat halaman, padahal alamat ini ada di layanan berbagi milik $owner, bukan milik $pathBrand. Penipu sering memakai cara ini.".$note, 3);
            }
        } elseif ($official !== null) {
            $signals[] = new Signal('official_domain', 'info', 'Cocok dengan domain resmi '.$official['brand'],
                $official['domain'].' ada di daftar domain resmi Jeda. Tetap pastikan alamatnya memang yang kamu tuju.'.$note, 0);
        } elseif (! $p['is_ip']) {
            $brand = $this->lookalikeBrand($host);
            if ($brand !== null) {
                $signals[] = new Signal('brand_lookalike', 'url_pattern', 'Mirip nama '.$brand,
                    "Nama $brand muncul di alamat ini, tetapi domain utamanya ({$p['registrable']}) bukan domain resmi yang kami kenal.$note", 4);
            }
        }

        if (! $p['is_ip'] && $this->isFreeHosting($host)) {
            $signals[] = new Signal('free_hosting', 'url_pattern', 'Situs di layanan hosting gratis',
                'Siapa saja bisa membuat halaman di layanan ini tanpa verifikasi, jadi nama domainnya tidak membuktikan siapa pemilik halamannya.'.$note, 1);
        }

        if (in_array($p['registrable'], config('jeda_cek.shorteners'), true)) {
            $signals[] = new Signal('shortener', 'url_pattern', 'Tautan dipendekkan',
                'Pemendek tautan menyembunyikan tujuan asli.'.$note, 1);
        }

        $tld = substr(strrchr($host, '.') ?: '', 1);
        if (! $p['is_ip'] && in_array($tld, config('jeda_cek.suspicious_tlds'), true)) {
            $signals[] = new Signal('suspicious_tld', 'url_pattern', "Akhiran domain .$tld",
                'Akhiran ini murah dan sering dipakai situs sekali pakai, walau tidak semuanya berbahaya.'.$note, 1);
        }

        if ($official === null && ! $p['is_ip']
            && preg_match('/(login|verify|verifikasi|secure|hadiah|claim|gift|bonus|otp|undian|cashback|promo|akun|konfirmasi)/i', $host) === 1) {
            $signals[] = new Signal('bait_words', 'keyword', 'Kata pancingan pada domain',
                'Kata seperti login atau hadiah sering dipakai untuk memancing klik, tetapi juga dipakai situs sah. Dihitung hanya jika ada tanda lain.'.$note,
                1, needsOther: true);
        }

        // Kata pancingan di path berlaku juga untuk layanan berbagi konten (mis. sites.google.com/view/bca-login).
        if (($official === null || $shared) && ! $p['is_ip']
            && preg_match('/(login|signin|verify|verifikasi|otp|password|konfirmasi|akun|secure|update)/i', $p['path'].' '.($p['query'] ?? '')) === 1) {
            $signals[] = new Signal('bait_path', 'keyword', 'Kata pancingan pada alamat halaman',
                'Alamat halaman memuat kata seperti login atau verifikasi. Dihitung hanya jika ada tanda lain.'.$note,
                1, needsOther: true);
        }

        if ($official === null && ! $p['is_ip']) {
            if (substr_count($host, '.') >= 3) {
                $signals[] = new Signal('deep_subdomain', 'url_pattern', 'Subdomain bertingkat',
                    'Periksa domain utamanya, yaitu bagian tepat sebelum akhiran: '.$p['registrable'].'.'.$note, 1);
            }

            $first = explode('.', $host)[0];
            if (in_array($first, config('jeda_cek.redirector_labels'), true)
                && preg_match('~^/[A-Za-z0-9]{5,12}/?$~', $p['path']) === 1) {
                $signals[] = new Signal('redirector_pattern', 'url_pattern', 'Bentuknya mirip tautan pengalih',
                    'Alamat pendek dengan kode acak di subdomain "'.$first.'" lazim dipakai untuk mengalihkan ke tujuan lain. Tanda lemah.'.$note, 1);
            }
        }

        if (strlen($host) > 45) {
            $signals[] = new Signal('long_host', 'url_pattern', 'Nama domain sangat panjang',
                'Domain yang terlalu panjang sering dipakai untuk menyembunyikan nama aslinya.'.$note, 1);
        }

        if ($p['explicit_http']) {
            $signals[] = new Signal('http_only', 'url_pattern', 'Tidak memakai HTTPS',
                'Koneksi tidak terenkripsi, jadi data yang kamu kirim bisa terbaca pihak lain.'.$note, 1);
        }

        if ($p['port'] !== null && ! in_array($p['port'], [80, 443], true)) {
            $signals[] = new Signal('odd_port', 'url_pattern', 'Port tidak biasa',
                "Alamat memakai port {$p['port']}, yang jarang dipakai situs umum.".$note, 1);
        }

        $query = (string) ($p['query'] ?? '');
        if ($query !== '') {
            if (preg_match('~=(https?%3a|https?:)~i', $query) === 1) {
                $signals[] = new Signal('query_embedded_url', 'url_pattern', 'Parameter memuat alamat lain',
                    'Parameter yang berisi alamat web lain sering dipakai untuk mengalihkan korban.'.$note, 2);
            }
            if (preg_match('/(^|&)(password|passwd|pwd|pass|otp|pin|cvv)=/i', $query) === 1) {
                $signals[] = new Signal('query_credentials', 'url_pattern', 'Parameter meminta data sensitif',
                    'Alamat membawa parameter bernama password, OTP, atau PIN.'.$note, 2);
            }
        }

        return ['signals' => $signals, 'official' => $official];
    }

    /** Domain utama (tanpa subdomain), mis. www.bca.co.id menjadi bca.co.id. */
    public function registrableOf(string $host): string
    {
        $labels = explode('.', $host);
        if (count($labels) <= 2) {
            return $host;
        }
        $lastTwo = implode('.', array_slice($labels, -2));
        if (in_array($lastTwo, config('jeda_cek.second_level_suffixes'), true)
            || in_array($lastTwo, config('jeda_cek.free_hosting_suffixes', []), true)) {
            return implode('.', array_slice($labels, -3));
        }

        return $lastTwo;
    }

    /** Host di layanan hosting/pembuat situs gratis (bukan penyedianya sendiri). */
    public function isFreeHosting(string $host): bool
    {
        foreach (config('jeda_cek.free_hosting_suffixes', []) as $suffix) {
            if (str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    /** Host resmi yang isinya bisa dibuat siapa saja atau yang hanya mengalihkan (lihat config shared_platform_hosts). */
    public function isSharedPlatform(string $host): bool
    {
        foreach (config('jeda_cek.shared_platform_hosts', []) as $shared) {
            if ($host === $shared || str_ends_with($host, '.'.$shared)) {
                return true;
            }
        }

        return false;
    }

    /** Dua domain milik merek resmi yang sama (mis. wa.me dan whatsapp.com). */
    public function sameOfficialBrand(string $registrableA, string $registrableB): bool
    {
        $a = $this->matchOfficial($registrableA);
        $b = $this->matchOfficial($registrableB);

        return $a !== null && $b !== null && $a['brand'] === $b['brand'];
    }

    /**
     * Nama merek LAIN (bukan pemilik layanan) di path alamat, mis. sites.google.com/view/bca-resmi.
     * Cocok per kata utuh; kata kunci panjang (5+ huruf) juga cocok bila ada di dalam kata.
     */
    private function brandInPath(string $path, string $ownerBrand): ?string
    {
        $tokens = array_filter(preg_split('/[^a-z0-9]+/', strtolower(rawurldecode($path))) ?: []);
        foreach (config('jeda_cek.brands') as $brand => $cfg) {
            if ($brand === $ownerBrand) {
                continue;
            }
            foreach ($tokens as $token) {
                foreach ($cfg['keywords'] as $keyword) {
                    if ($token === $keyword || (strlen($keyword) >= 5 && str_contains($token, $keyword))) {
                        return $brand;
                    }
                }
            }
        }

        return null;
    }

    /** @return array{brand:string,domain:string}|null */
    public function matchOfficial(string $registrable): ?array
    {
        foreach (config('jeda_cek.brands') as $brand => $cfg) {
            if (in_array($registrable, $cfg['domains'], true)) {
                return ['brand' => $brand, 'domain' => $registrable];
            }
        }

        return null;
    }

    /**
     * Mencari nama merek di sebuah teks (mis. judul halaman). Hanya kata berukuran 4+ huruf
     * agar singkatan pendek (bri, bni, ovo) tidak salah cocok.
     *
     * @return array{brand:string,domains:array<int,string>}|null
     */
    public function brandInText(string $text): ?array
    {
        $text = strtolower($text);
        foreach (config('jeda_cek.brands') as $brand => $cfg) {
            foreach ($cfg['keywords'] as $keyword) {
                if (strlen($keyword) >= 4 && preg_match('/\b'.preg_quote($keyword, '/').'\b/u', $text) === 1) {
                    return ['brand' => $brand, 'domains' => $cfg['domains']];
                }
            }
        }

        return null;
    }

    private function lookalikeBrand(string $host): ?string
    {
        $labels = explode('.', $host);
        array_pop($labels); // buang akhiran (TLD)
        $tokens = array_filter(preg_split('/[.\-_]+/', implode('.', $labels)) ?: []);

        foreach (config('jeda_cek.brands') as $brand => $cfg) {
            foreach ($tokens as $token) {
                foreach ($this->variants($token) as $variant) {
                    foreach ($cfg['keywords'] as $keyword) {
                        if ($this->matches($variant, $keyword)) {
                            return $brand;
                        }
                    }
                }
            }
        }

        return null;
    }

    /** Varian penulisan: angka pengganti huruf (g0pay), "rn" jadi "m", dll. */
    private function variants(string $token): array
    {
        $base = strtr($token, ['rn' => 'm', 'vv' => 'w']);
        $map = ['0' => 'o', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '$' => 's'];
        $asL = strtr($base, $map + ['1' => 'l']);
        $asI = strtr($base, $map + ['1' => 'i']);

        return array_values(array_unique([$token, $base, $asL, $asI]));
    }

    private function matches(string $token, string $keyword): bool
    {
        if ($token === $keyword) {
            return true;
        }
        $len = strlen($keyword);
        if ($len >= 6 && str_contains($token, $keyword)) {
            return true;
        }

        return $len >= 5 && abs(strlen($token) - $len) <= 1 && levenshtein($token, $keyword) === 1;
    }
}
