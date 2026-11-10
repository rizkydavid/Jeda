<?php

namespace App\Services\Cek;

/**
 * Orkestrator pemeriksaan tautan. Urutan:
 *  1. pola alamat (lokal)  2. pengalihan  3. isi halaman tujuan
 *  4. Google Safe Browsing  5. VirusTotal  6. RDAP  7. skor (RiskScorer)
 *
 * Setiap pemeriksaan eksternal yang gagal dicatat sebagai status "belum lengkap",
 * tidak pernah dianggap aman.
 */
final class LinkAnalysisService
{
    public function __construct(
        private readonly UrlHeuristics $heuristics,
        private readonly RedirectTracer $tracer,
        private readonly PageInspector $pages,
        private readonly SafeBrowsingClient $safeBrowsing,
        private readonly VirusTotalUrlClient $virusTotal,
        private readonly DomainAgeLookup $domainAge,
        private readonly RiskScorer $scorer,
    ) {}

    /** @return array<string,mixed>|null null jika input bukan alamat web yang valid */
    public function analyze(string $rawUrl, bool $submitToVirusTotal = false): ?array
    {
        $parsed = $this->heuristics->parse($rawUrl);
        if ($parsed === null) {
            return null;
        }

        $local = $this->heuristics->analyze($parsed);
        $signals = $local['signals'];
        $official = $local['official'];
        $checks = [];

        // --- 1) Pengalihan dan 2) isi halaman tujuan -------------------------------------------
        $finalParsed = null;
        $finalIsOfficial = false;
        $hosts = [$parsed['host']];
        $trace = null;

        // Domain resmi biasa tidak perlu dibuka. Layanan berbagi konten milik merek resmi (docs.google.com,
        // sites.google.com, wa.me, dll.) TETAP dilacak dan dibaca: domainnya resmi, isinya bisa dibuat siapa saja.
        if ($official !== null && empty($official['shared'])) {
            $checks['redirect'] = $this->check('Pengalihan tautan', 'skipped', 'Domain resmi yang dikenal, tidak perlu dibuka.');
            $checks['page'] = $this->check('Isi halaman tujuan', 'skipped', 'Domain resmi yang dikenal, tidak perlu dibuka.');
        } else {
            $trace = $this->tracer->trace($parsed['fetch_url']);
            [$checks['redirect'], $redirectSignals, $hosts] = $this->redirectStep($trace, $parsed);
            $signals = array_merge($signals, $redirectSignals);

            if ($trace['status'] === 'ok' && $trace['final_url'] !== null && $trace['final_url'] !== $parsed['fetch_url']) {
                $finalParsed = $this->heuristics->parse($trace['final_url']);
            }
            if ($finalParsed !== null) {
                // Tujuan akhir diperiksa dengan aturan yang sama. Id yang sama tidak dihitung dua kali.
                $finalLocal = $this->heuristics->analyze($finalParsed, 'tujuan akhir: '.$finalParsed['host']);
                $signals = array_merge($signals, $finalLocal['signals']);
                $finalIsOfficial = $finalLocal['official'] !== null && empty($finalLocal['official']['shared']);
            }

            [$checks['page'], $pageSignals] = $this->pageStep($trace, $finalIsOfficial);
            $signals = array_merge($signals, $pageSignals);
        }

        // Alamat yang TERTULIS langsung sebagai jaringan internal (localhost, IP privat, nama .local) TIDAK dikirim
        // ke layanan eksternal mana pun: nama jaringan internal tidak boleh bocor ke pihak ketiga, dan
        // memeriksanya di sana tidak ada gunanya. Nama domain publik yang hanya TERJEMAHAN DNS-nya internal
        // (reason dns_internal) tetap diperiksa: nama itu bukan rahasia dan justru perlu dicek.
        $internalOnly = $trace !== null
            && $trace['status'] === 'blocked'
            && in_array($trace['reason'], ['ip_internal', 'host_internal'], true)
            && count($trace['chain']) === 1;

        $youngest = null;
        if ($internalOnly) {
            $note = 'Alamat internal tidak dikirim ke layanan eksternal.';
            $checks['safe_browsing'] = $this->check('Google Safe Browsing', 'skipped', $note, ['result' => null]);
            $checks['virustotal'] = $this->check('VirusTotal', 'skipped', $note, ['result' => null]);
            $checks['domain_age'] = $this->check('Umur domain (RDAP)', 'skipped', $note);
        } else {
            // --- 3) Google Safe Browsing -----------------------------------------------------------
            $urls = [$parsed['safe_url']];
            if ($finalParsed !== null) {
                $urls[] = $finalParsed['safe_url'];
            }
            [$checks['safe_browsing'], $sbSignals] = $this->safeBrowsingStep($urls);
            $signals = array_merge($signals, $sbSignals);

            // --- 4) VirusTotal ----------------------------------------------------------------------
            [$checks['virustotal'], $vtSignals] = $this->virusTotalStep($parsed, $finalParsed, $submitToVirusTotal);
            $signals = array_merge($signals, $vtSignals);

            // --- 5) RDAP ---------------------------------------------------------------------------------
            $domains = [];
            foreach ([$parsed, $finalParsed] as $candidate) {
                if ($candidate !== null && ! $candidate['is_ip']
                    && $this->heuristics->matchOfficial($candidate['registrable']) === null
                    && ! $this->heuristics->isFreeHosting($candidate['host'])) {
                    $domains[$candidate['registrable']] = true;
                }
            }
            [$checks['domain_age'], $ageSignals, $youngest] = $this->domainStep(array_keys($domains));
            $signals = array_merge($signals, $ageSignals);
        }

        // --- 6) Skor ------------------------------------------------------------------------------------------------
        $evaluation = $this->scorer->evaluate($signals, $checks);

        return $evaluation + [
            'parsed' => $parsed,
            'final_hostname' => $finalParsed['host'] ?? null,
            'redirect_hosts' => $hosts,
            'official_brand' => $official['brand'] ?? null,
            'official_shared' => ! empty($official['shared']),
            'domain_age_days' => $youngest,
            'checks' => $checks,
        ];
    }

    /** @return array{0:array,1:array<int,Signal>,2:array<int,string>} */
    private function redirectStep(array $trace, array $parsed): array
    {
        $hosts = [];
        foreach ($trace['chain'] as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host && ! in_array(strtolower($host), $hosts, true)) {
                $hosts[] = strtolower($host);
            }
        }
        $count = count($trace['chain']) - 1;
        $signals = [];
        $max = (int) config('jeda_cek.redirect.max_hops', 5);

        $state = match ($trace['status']) {
            'ok' => 'ok',
            'blocked' => 'blocked',
            default => 'unavailable',
        };

        $note = match ($trace['reason']) {
            'ip_internal', 'host_internal' => 'Jeda menolak membuka tautan ini karena mengarah ke alamat internal atau jaringan pribadi (perlindungan keamanan).',
            'dns_internal' => 'DNS di jaringan server Jeda menerjemahkan domain ini ke alamat jaringan pribadi'.(! empty($trace['blocked_ip']) ? " ({$trace['blocked_ip']})" : '')
                .'. Itu bisa terjadi karena VPN, pemblokir DNS, atau router di jaringan itu, atau karena domainnya memang diarahkan ke jaringan internal. Demi keamanan Jeda tidak membukanya, tetapi pemeriksaan lain tetap dijalankan.',
            'port' => 'Jeda hanya membuka port web standar (80/443), jadi tautan ini tidak dibuka.',
            'dns_failed' => 'Nama domain tidak ditemukan (DNS). Situsnya mungkin sudah mati atau salah ketik.',
            'no_ipv4' => 'Domain ini hanya punya alamat IPv6, yang belum didukung pemeriksaan ini.',
            'timeout' => 'Pemeriksaan pengalihan melewati batas waktu.',
            'unreachable' => 'Situs tidak dapat dihubungi dari server Jeda (mati, memblokir, atau koneksi HTTPS gagal).',
            'curl_missing' => 'Ekstensi cURL PHP belum aktif di server ini.',
            'too_many_redirects' => "Lebih dari $max pengalihan beruntun, jadi tujuan akhirnya tidak diikuti.",
            'invalid_redirect' => 'Alamat pengalihan tidak valid atau memakai protokol yang tidak diizinkan.',
            'too_large' => 'Halaman terlalu besar, jadi isinya tidak diperiksa.',
            default => $count === 0 ? 'Tidak ada pengalihan.' : "$count pengalihan diikuti.",
        };
        if ($trace['status'] === 'ok' && $trace['reason'] === null && $count > 0) {
            $note = "$count pengalihan diikuti: ".implode(' → ', $hosts).'.';
        }

        if ($trace['status'] === 'blocked' && in_array($trace['reason'], ['ip_internal', 'host_internal'], true)) {
            $signals[] = new Signal('internal_target', 'redirect', 'Mengarah ke alamat internal',
                'Tautan ini mengarah ke jaringan pribadi atau internal. Jeda menolak membukanya.', 3);
        }
        if (in_array($trace['status'], ['too_many_redirects'], true) || $count >= 3) {
            $signals[] = new Signal('redirect_many', 'redirect', 'Terlalu banyak pengalihan beruntun',
                "Tautan dialihkan $count kali sebelum sampai tujuan. Rantai panjang sering dipakai untuk menyembunyikan tujuan.", 2);
        }
        if ($trace['status'] === 'ok' && $trace['final_url'] !== null) {
            $finalHost = strtolower((string) parse_url($trace['final_url'], PHP_URL_HOST));
            $finalRegistrable = $finalHost !== '' ? $this->heuristics->registrableOf($finalHost) : '';
            if ($count >= 1 && $finalHost !== '' && $finalRegistrable !== $parsed['registrable']
                && ! $this->heuristics->sameOfficialBrand($parsed['registrable'], $finalRegistrable)) {
                $signals[] = new Signal('redirect_cross_domain', 'redirect', 'Tautan dialihkan ke domain lain',
                    "Alamat yang kamu tempel berakhir di $finalHost, domain yang berbeda.", 1);
            }
        }

        return [$this->check('Pengalihan tautan', $state, $note, ['hosts' => $hosts, 'count' => $count]), $signals, $hosts];
    }

    /** @return array{0:array,1:array<int,Signal>} */
    private function pageStep(?array $trace, bool $finalIsOfficial): array
    {
        if ($finalIsOfficial) {
            return [$this->check('Isi halaman tujuan', 'skipped', 'Tujuan akhir adalah domain resmi yang dikenal.'), []];
        }
        if ($trace === null || $trace['status'] !== 'ok') {
            return [$this->check('Isi halaman tujuan', 'skipped', 'Halaman tidak dibuka.'), []];
        }
        if ($trace['body'] === null) {
            return [$this->check('Isi halaman tujuan', 'skipped', 'Isi halaman tidak dibaca (bukan HTML, terlalu besar, atau terkompresi).'), []];
        }

        $page = $this->pages->inspect($trace['body'], $trace['final_url']);
        $note = $page['password_form']
            ? 'Halaman memiliki kolom kata sandi. Jeda tidak mengisi atau mengirim form apa pun.'
            : 'Tidak ditemukan kolom kata sandi pada halaman (halaman yang dibuat dengan JavaScript tidak terbaca).';

        return [$this->check('Isi halaman tujuan', 'ok', $note, ['password_form' => $page['password_form'], 'title' => $page['title']]), $page['signals']];
    }

    /** @return array{0:array,1:array<int,Signal>} */
    private function safeBrowsingStep(array $urls): array
    {
        $r = $this->safeBrowsing->check($urls);
        $label = 'Google Safe Browsing';

        if ($r['state'] !== 'ok') {
            return [$this->check($label, $r['state'], $this->serviceNote($label, $r['state'], $r['reason']), ['result' => null]), []];
        }
        if ($r['result'] === 'flagged') {
            $threats = implode(', ', $r['threats']);

            return [
                $this->check($label, 'ok', "Terdaftar oleh Google: $threats.", ['result' => 'flagged', 'threats' => $r['threats']]),
                [new Signal('safe_browsing', 'reputation', 'Terdaftar sebagai situs berbahaya oleh Google',
                    "Google Safe Browsing menandai alamat ini: $threats.", 10, floor: 10)],
            ];
        }

        return [$this->check($label, 'ok', 'Tidak ditemukan di daftar Google. Ini tidak membuktikan tautan aman, karena situs baru sering belum terdaftar.', ['result' => 'clean']), []];
    }

    /** @return array{0:array,1:array<int,Signal>} */
    private function virusTotalStep(array $parsed, ?array $finalParsed, bool $submit): array
    {
        $label = 'VirusTotal';
        $r = $this->virusTotal->lookup($parsed['fetch_url']);
        if ($r['state'] === 'ok' && $r['result'] === 'unknown' && $submit) {
            $r = $this->virusTotal->submit($parsed['fetch_url']);
        }
        if ($finalParsed !== null && $r['result'] !== 'flagged' && ! in_array($r['state'], ['not_configured'], true)) {
            $f = $this->virusTotal->lookup($finalParsed['fetch_url']);
            if ($this->vtRank($f) > $this->vtRank($r)) {
                $r = $f;
            }
        }

        if (in_array($r['state'], ['unavailable', 'not_configured'], true)) {
            return [$this->check($label, $r['state'], $this->serviceNote($label, $r['state'], $r['reason']), ['result' => null]), []];
        }
        if ($r['state'] === 'pending') {
            return [$this->check($label, 'pending', 'Analisis VirusTotal belum selesai. Periksa lagi beberapa saat lagi.', ['result' => null]), []];
        }
        if ($r['result'] === 'unknown') {
            $note = $submit
                ? 'Alamat ini belum pernah dianalisis VirusTotal.'
                : 'Alamat ini belum pernah dianalisis VirusTotal (bukan berarti aman). Jeda tidak mengirim alamat ke VirusTotal kecuali kamu mengizinkan.';

            return [$this->check($label, 'ok', $note, ['result' => 'unknown']), []];
        }
        if ($r['result'] === 'clean') {
            return [$this->check($label, 'ok', "{$r['total']} mesin memeriksa, tidak ada yang menandai berbahaya.", ['result' => 'clean', 'total' => $r['total']]), []];
        }

        $hits = $r['malicious'] + $r['suspicious'];
        $check = $this->check($label, 'ok', "$hits dari {$r['total']} mesin menandai alamat ini.", ['result' => 'flagged', 'malicious' => $r['malicious'], 'suspicious' => $r['suspicious'], 'total' => $r['total']]);

        // Satu atau dua mesin saja sering keliru (false positive). Baru terkonfirmasi jika banyak mesin sepakat.
        if ($r['malicious'] >= 3) {
            return [$check, [new Signal('virustotal_malicious', 'reputation', 'Banyak mesin VirusTotal menandai berbahaya',
                "$hits dari {$r['total']} mesin keamanan menandai alamat ini.", 9, floor: 9)]];
        }

        return [$check, [new Signal('virustotal_some', 'reputation', 'Sebagian kecil mesin VirusTotal menandai mencurigakan',
            "$hits dari {$r['total']} mesin menandai alamat ini. Satu atau dua mesin saja sering keliru, jadi ini bukan bukti kuat.", 3)]];
    }

    /** @return array{0:array,1:array<int,Signal>,2:?int} */
    private function domainStep(array $domains): array
    {
        $label = 'Umur domain (RDAP)';
        if ($domains === []) {
            return [$this->check($label, 'skipped', 'Dilewati (domain resmi, alamat IP, atau situs di layanan hosting gratis, yang umur domainnya tidak menggambarkan halamannya).'), [], null];
        }

        $entries = [];
        $state = 'ok';
        $reason = null;
        $youngest = null;
        foreach ($domains as $domain) {
            $r = $this->domainAge->lookup($domain);
            if ($r['state'] !== 'ok') {
                $state = 'unavailable';
                $reason = $r['reason'];

                continue;
            }
            $entries[] = ['domain' => $domain, 'days' => $r['days'], 'registered_at' => $r['registered_at'], 'registrar' => $r['registrar']];
            if ($r['days'] !== null && ($youngest === null || $r['days'] < $youngest)) {
                $youngest = $r['days'];
            }
        }

        $parts = [];
        foreach ($entries as $e) {
            $parts[] = $e['days'] === null
                ? "{$e['domain']}: tanggal pendaftaran tidak tersedia"
                : "{$e['domain']}: terdaftar sekitar {$e['days']} hari lalu".($e['registrar'] ? " ({$e['registrar']})" : '');
        }
        $note = $state === 'unavailable' && $entries === []
            ? $this->serviceNote($label, 'unavailable', $reason)
            : implode('; ', $parts).'.';
        if ($state === 'unavailable' && $entries !== []) {
            $note .= ' Sebagian domain tidak dapat diperiksa.';
        }

        $signals = [];
        if ($youngest !== null) {
            if ($youngest < 30) {
                $signals[] = new Signal('domain_new', 'domain', 'Domain masih sangat baru',
                    "Domain terdaftar sekitar $youngest hari lalu. Domain baru hanyalah satu indikator, bukan bukti berbahaya.", 2);
            } elseif ($youngest < 90) {
                $signals[] = new Signal('domain_recent', 'domain', 'Domain tergolong baru',
                    "Domain terdaftar sekitar $youngest hari lalu.", 1);
            }
        }

        return [$this->check($label, $state, $note, ['domains' => $entries]), $signals, $youngest];
    }

    private function vtRank(array $r): int
    {
        return match (true) {
            $r['result'] === 'flagged' => 5,
            $r['state'] === 'unavailable' => 4,
            $r['state'] === 'pending' => 3,
            $r['state'] === 'not_configured' => 2,
            $r['result'] === 'unknown' => 1,
            default => 0,
        };
    }

    private function serviceNote(string $service, string $state, ?string $reason): string
    {
        if ($state === 'not_configured') {
            return 'Belum diaktifkan di server ini (API key belum diisi).';
        }

        $note = match ($reason) {
            'rate_limited' => "Batas permintaan $service tercapai. Coba lagi beberapa menit lagi.",
            'access_denied' => "$service menolak permintaan dari server Jeda.",
            'invalid_request' => "$service menolak permintaan (API key kemungkinan tidak valid).",
            'server_error' => "Layanan $service sedang bermasalah.",
            default => "Layanan $service tidak dapat dihubungi (jaringan atau batas waktu).",
        };

        // Rincian teknis hanya untuk pengembang (APP_DEBUG=true), supaya pengunjung tidak melihat konfigurasi.
        if (config('app.debug') && in_array($reason, ['access_denied', 'invalid_request'], true)) {
            $note .= ' Periksa API key di .env, pastikan API sudah diaktifkan, dan key tidak dibatasi ke "HTTP referrer".';
        }

        return $note;
    }

    private function check(string $label, string $state, ?string $note = null, array $extra = []): array
    {
        return ['label' => $label, 'state' => $state, 'note' => $note] + $extra;
    }
}
