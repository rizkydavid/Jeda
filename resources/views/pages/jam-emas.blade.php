@extends('layouts.app')

@section('title', 'Rencana Jam Emas')

@section('content')

<main class="wrap">
    <style>
        .jg-choices { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0 22px; }
        .jg-choices:last-child { margin-bottom: 0; }
        .jg-choice { font: inherit; font-weight: 600; font-size: 14px; padding: 10px 16px; border-radius: 999px; border: 1.5px solid var(--line); background: var(--card); color: var(--ink); cursor: pointer; }
        .jg-choice[aria-pressed="true"] { border-color: var(--blue); background: var(--soft); font-weight: 700; }
        .jg-choice:focus-visible, .jg-box:focus-visible { outline: 3px solid var(--blue); outline-offset: 2px; }

        .jg-step { display: flex; gap: 14px; padding: 16px; border: 1px solid var(--line); border-radius: 16px; background: var(--card); cursor: pointer; }
        .jg-step + .jg-step { margin-top: 12px; }
        .jg-step.is-done { opacity: .72; }
        .jg-step.is-done h3 { text-decoration: line-through; }
        .jg-box { flex: none; width: 26px; height: 26px; border: 2px solid var(--blue); border-radius: 8px; background: transparent; padding: 0; cursor: pointer; color: #fff; font-size: 15px; line-height: 1; }
        .jg-step.is-done .jg-box { background: var(--blue); }
        .jg-step.is-done .jg-box::after { content: "✓"; }
        .jg-body { min-width: 0; flex: 1; }
        .jg-meta { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 6px; }
        .jg-acts { margin-top: 10px; gap: 8px; }
        .jg-acts .btn { padding: 9px 14px; font-size: 14px; }

        .jg-bar { height: 10px; border-radius: 999px; background: var(--soft); overflow: hidden; margin: 6px 0 16px; }
        .jg-bar span { display: block; height: 100%; width: 0; background: var(--blue); transition: width .3s; }

        .jg-form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .jg-form .full { grid-column: 1 / -1; }
        .jg-form label { display: block; margin-bottom: 4px; }
        .jg-out { white-space: pre-wrap; word-break: break-word; font-size: 14px; max-height: 440px; overflow: auto; margin-top: 0; }
        .jg-gap > * + * { margin-top: 20px; }
        @@media (max-width: 820px) { .jg-form { grid-template-columns: 1fr; } }
    </style>

    <h1 style="font-size:40px">Rencana Jam Emas</h1>
    <p class="mut">Jawab dua pertanyaan. Langkah, urutan, dan kalimat laporannya menyesuaikan.</p>

    <div class="jg-gap" style="margin-top:18px">

        <div class="card">
            <h2>1. Apa yang sudah terjadi?</h2>
            <div class="jg-choices" id="jgSit" role="group" aria-label="Apa yang sudah terjadi">
                <button type="button" class="jg-choice" data-sit="transfer" aria-pressed="true">Sudah transfer uang</button>
                <button type="button" class="jg-choice" data-sit="otp" aria-pressed="false">Sudah beri kode OTP</button>
                <button type="button" class="jg-choice" data-sit="tautan" aria-pressed="false">Sudah klik tautan</button>
                <button type="button" class="jg-choice" data-sit="dibobol" aria-pressed="false">Akun dibobol</button>
            </div>
            <h2>2. Kejadiannya kapan?</h2>
            <div class="jg-choices" id="jgTime" role="group" aria-label="Kejadiannya kapan">
                <button type="button" class="jg-choice" data-time="baru" aria-pressed="true">Kurang dari 1 jam lalu</button>
                <button type="button" class="jg-choice" data-time="hari" aria-pressed="false">Hari ini (dalam 24 jam)</button>
                <button type="button" class="jg-choice" data-time="lama" aria-pressed="false">Lebih dari sehari</button>
            </div>
        </div>

        <div class="card dark">
            <h2 id="jgHead"></h2>
            <p class="mut" id="jgLead" style="margin:0;max-width:720px"></p>
        </div>

        <div class="grid2">
            <div class="card">
                <h2>Langkahmu, berurutan</h2>
                <p class="small" id="jgCount" style="margin:0"></p>
                <div class="jg-bar" aria-hidden="true"><span id="jgBar"></span></div>
                <div id="jgSteps"></div>
            </div>

            <div class="stack">
                <div class="card">
                    <h2>Ke mana melapor</h2>
                    <p class="mut">Jalur resmi untuk penipuan transaksi keuangan di Indonesia.</p>
                    <div class="stack">
                        <a class="btn block" href="tel:157">Telepon OJK 157 (24 jam)</a>
                        <a class="btn ghost block" href="https://wa.me/6281157157157" target="_blank" rel="noopener">WhatsApp OJK 081-157-157-157</a>
                        <a class="btn ghost block" href="https://iasc.ojk.go.id" target="_blank" rel="noopener">Lapor di iasc.ojk.go.id</a>
                    </div>
                    <div class="q" style="margin-top:14px">
                        <b>Awas, ada yang mencatut nama IASC.</b> Pelaporan hanya lewat situs resminya. Kalau ada pihak yang menghubungimu lebih dulu dan mengaku dari IASC, jangan dipercaya. Untuk bank dan e-wallet, pakai nomor di kartu atau aplikasi resminya, bukan dari pesan atau hasil pencarian.
                    </div>
                    <p class="small" style="margin:12px 0 0">Kontak dicek ke sumber OJK pada November 2026. Nomor bisa berubah, cek juga di ojk.go.id.</p>
                </div>

                <div class="card">
                    <h2>Bingung mulai cerita?</h2>
                    <p class="mut">Kalimat ini mengikuti situasi yang kamu pilih. Salin dan kirim ke orang yang kamu percaya.</p>
                    <div class="q" id="jgCerita" style="font-size:15px"></div>
                    <button type="button" class="btn ghost block" id="jgSalinCerita" style="margin-top:12px">Salin teks cerita</button>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Draf laporan siap salin</h2>
            <p class="mut">Isi yang kamu ingat, kolom yang kosong akan ditandai. Teks ini bisa kamu tempel ke bank atau IASC. Isian tidak dikirim ke mana pun dan hilang saat halaman ditutup.</p>
            <div class="grid2">
                <div class="jg-form">
                    <div class="full">
                        <label class="small" for="jgWaktu">Waktu kejadian</label>
                        <input class="jeda-field" id="jgWaktu" type="text" autocomplete="off" placeholder="Contoh: 5 November 2026, sekitar pukul 14.30">
                    </div>
                    <div>
                        <label class="small" for="jgMedia">Pelaku menghubungi lewat</label>
                        <select class="jeda-field" id="jgMedia">
                            <option>WhatsApp</option>
                            <option>Instagram</option>
                            <option>Telegram</option>
                            <option>Facebook</option>
                            <option>SMS</option>
                            <option>Telepon</option>
                            <option>Marketplace atau situs jual beli</option>
                            <option>Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="small" for="jgNominal">Nominal kerugian</label>
                        <input class="jeda-field" id="jgNominal" type="text" autocomplete="off" placeholder="Contoh: Rp1.500.000 (kosongkan jika tidak ada)">
                    </div>
                    <div class="full">
                        <label class="small" for="jgRek">Rekening atau akun tujuan</label>
                        <input class="jeda-field" id="jgRek" type="text" autocomplete="off" placeholder="Bank atau e-wallet, nomor, nama pemilik">
                    </div>
                    <div class="full">
                        <label class="small" for="jgKronologi">Kronologi singkat</label>
                        <textarea class="jeda-field" id="jgKronologi" rows="4" style="min-height:96px;margin:0" placeholder="Ceritakan urutan kejadian dengan singkat"></textarea>
                    </div>
                    <div>
                        <label class="small" for="jgNama">Nama pelapor</label>
                        <input class="jeda-field" id="jgNama" type="text" autocomplete="off">
                    </div>
                    <div>
                        <label class="small" for="jgKontak">Nomor yang bisa dihubungi</label>
                        <input class="jeda-field" id="jgKontak" type="text" autocomplete="off">
                    </div>
                </div>

                <div>
                    <div class="q jg-out" id="jgLaporan" aria-live="polite"></div>
                    <button type="button" class="btn block" id="jgSalinLaporan" style="margin-top:12px">Salin draf laporan</button>
                </div>
            </div>
        </div>

        <div class="grid2">
            <div class="card">
                <h2>Waspada penipuan kedua</h2>
                <p class="mut" style="margin:0">
                    Setelah kena tipu, korban sering didekati orang yang mengaku bisa mengembalikan uang asal bayar dulu: biaya admin, pajak, atau “dana jaminan”. Itu kelanjutan penipuannya. Jangan membayar siapa pun yang menjanjikan uangmu pasti kembali.
                </p>
            </div>

            <div class="card dark">
                <h2>Peringatkan orang lain</h2>
                <p class="mut">Kirim teks ini ke teman atau keluarga supaya mereka tidak ikut jadi korban.</p>
                <button type="button" class="btn block" id="jgSalinPeringatan">Salin teks peringatan</button>
            </div>
        </div>

        <p class="small">Jeda bukan layanan resmi dan tidak bisa menjamin dana kembali. Jeda hanya membantumu bergerak cepat dan menyiapkan laporan.</p>
    </div>
</main>

<script>
(() => {
    'use strict';

    /* ---------- Data ---------- */

    const SIT = {
        transfer: {
            head: 'Ini bukan salahmu. Kecepatan yang paling menentukan.',
            report: 'Transfer uang ke pihak yang ternyata penipu',
            cerita: 'Aku mau cerita. Tadi aku sudah transfer uang ke orang yang ternyata penipu. Aku butuh bantuan buat hubungi bank dan lapor.'
        },
        otp: {
            head: 'Ini bukan salahmu. Amankan akunmu dulu.',
            report: 'Memberikan kode OTP kepada pihak yang mengaku resmi',
            cerita: 'Aku mau cerita. Tadi aku sempat kasih kode OTP ke orang yang mengaku dari pihak resmi. Sepertinya itu penipuan, dan aku butuh bantuan buat amankan akunku.'
        },
        tautan: {
            head: 'Ini bukan salahmu. Banyak orang hampir tertipu tautan semacam ini.',
            report: 'Mengeklik tautan mencurigakan (dugaan phishing)',
            cerita: 'Aku mau cerita. Tadi aku klik tautan dari pesan yang mencurigakan. Aku takut datanya bocor, tolong bantu aku cek dan amankan akunku ya.'
        },
        dibobol: {
            head: 'Ini bukan salahmu. Ambil kembali kendali akunmu.',
            report: 'Akun saya dibobol atau diambil alih pihak lain',
            cerita: 'Aku mau cerita. Akunku sepertinya dibobol orang. Aku butuh bantuan buat ambil alih lagi dan cek apakah ada yang hilang.'
        }
    };

    const LEAD = {
        baru: 'Kamu masih di jam pertama, waktu paling berharga. Mulai dari langkah 1 sekarang. Penipu memang dilatih untuk menekan dan membuat orang panik, dan bercerita cepat adalah langkah yang paling membantu.',
        hari: 'Masih dalam 24 jam, jadi masih ada yang bisa diupayakan. Mulai dari langkah 1 dan jangan tunda. Banyak korban malu bercerita, padahal bercerita cepat adalah langkah yang paling membantu.',
        lama: 'Sudah lewat sehari, jadi peluang menahan dana lebih kecil. Tapi laporan tetap penting supaya rekening atau akun pelaku bisa ditinjau dan tidak memakan korban lain. Mulai dari langkah 1.'
    };

    const ACT = {
        hotline: { text: 'Telepon 157', href: 'tel:157' },
        wa: { text: 'WhatsApp OJK', href: 'https://wa.me/6281157157157', ext: true },
        iasc: { text: 'Buka iasc.ojk.go.id', href: 'https://iasc.ojk.go.id', ext: true }
    };

    // title/detail boleh berupa fungsi (situasi/waktu). acts = tombol aksi di bawah langkah.
    const STEPS = {
        bank: {
            title: () => 'Telepon bank atau e-wallet lewat jalur resmi',
            detail: t => 'Pakai nomor di kartu atau aplikasi resmi, bukan dari pesan atau hasil pencarian. Sebutkan waktu, nominal, dan rekening tujuan, lalu minta transaksi ditinjau atau ditahan. ' +
                (t === 'lama' ? 'Sudah lewat sehari, peluang menahan dana lebih kecil, tapi laporanmu tetap tercatat.' : 'Makin cepat, makin besar peluangnya.')
        },
        bukti: {
            title: () => 'Simpan bukti sebelum hilang',
            detail: (t, s) => s === 'tautan'
                ? 'Tangkapan layar pesan dan nomor pengirim. Kalau perlu menyalin alamat tautannya, tahan lama lalu salin, jangan diketuk.'
                : 'Tangkapan layar chat, profil dan nomor pelaku, nomor rekening tujuan, serta bukti transaksi. Jangan hapus percakapan, dan jangan blokir pelaku sebelum buktinya tersimpan.'
        },
        iasc: {
            title: s => s === 'transfer' ? 'Lapor ke Indonesia Anti-Scam Centre (OJK)' : 'Kalau ada uang yang sudah keluar, lapor ke IASC (OJK)',
            detail: () => 'Lapor lewat situs resmi iasc.ojk.go.id, hotline 157, atau WhatsApp OJK. Laporan ini yang memungkinkan rekening pelaku ditinjau. Tidak ada jaminan dana kembali, tapi melapor membantu mencegah pelaku menipu orang lain.',
            acts: ['iasc', 'hotline', 'wa']
        },
        putus: {
            title: () => 'Putus kontak, jangan transfer lagi',
            detail: () => 'Pelaku biasanya meminta “biaya tambahan” supaya uangmu bisa cair atau dikembalikan. Itu kelanjutan penipuan. Blokir pelaku setelah buktinya tersimpan.'
        },
        akunuang: {
            title: () => 'Amankan akun keuanganmu',
            detail: () => 'Ganti PIN dan kata sandi m-banking dan e-wallet dari perangkat yang aman, dan aktifkan verifikasi dua langkah bila tersedia.'
        },
        polisi: {
            title: () => 'Pertimbangkan lapor ke polisi',
            detail: () => 'Untuk kerugian besar atau butuh proses hukum, datang ke kantor polisi terdekat dengan membawa semua bukti. Laporan ke bank dan IASC tetap berjalan, jangan ditunda karena menunggu ini.'
        },
        cerita: {
            title: () => 'Ceritakan ke orang yang kamu percaya',
            detail: () => 'Jangan disimpan sendiri. Dukungan orang lain membuatmu lebih tenang dan tidak mudah ditekan lagi. Pakai kalimat pembuka di kartu samping.'
        },
        kendali: {
            title: () => 'Ambil kembali kendali akunmu',
            detail: () => 'Ganti kata sandi dari perangkat yang aman, lalu keluar dari semua perangkat dan sesi yang sedang aktif. Lakukan lewat aplikasi atau situs resmi, bukan tautan dari pesan.'
        },
        layanan: {
            title: () => 'Hubungi bank, e-wallet, atau layanan terkait',
            detail: () => 'Beri tahu bahwa kodemu sempat diberikan ke pihak lain. Minta akun dipantau atau dibekukan sementara bila perlu. Pakai nomor di aplikasi resmi.'
        },
        mutasi: {
            title: () => 'Cek mutasi dan riwayat login',
            detail: () => 'Cari transaksi atau login yang tidak kamu kenal, juga perangkat asing yang terhubung. Catat waktu dan nominalnya untuk laporan.'
        },
        wa: {
            title: () => 'Kalau kodenya dari WhatsApp',
            detail: () => 'Buka Pengaturan lalu Perangkat tertaut, dan keluarkan perangkat yang tidak kamu kenal. Aktifkan juga verifikasi dua langkah di pengaturan akun.'
        },
        tutup: {
            title: () => 'Berhenti dan jangan isi apa pun lagi',
            detail: () => 'Tutup halaman itu dan jangan mengetuk tautannya lagi. Kalau sempat mengunduh file atau aplikasi, jangan dibuka.'
        },
        sandi: {
            title: () => 'Kalau sudah mengisi sandi atau data',
            detail: () => 'Ganti kata sandi akun itu lewat situs atau aplikasi resmi dari perangkat yang aman. Kalau sandinya dipakai di tempat lain, ganti juga di sana.'
        },
        apk: {
            title: () => 'Kalau sempat memasang aplikasi dari tautan',
            detail: () => 'Hapus aplikasinya, lalu minta bantuan orang yang paham atau gerai resmi untuk memeriksa ponselmu. Ganti sandi penting dari perangkat lain, karena aplikasi dari luar toko resmi bisa membaca SMS dan kode OTP.'
        },
        bankdata: {
            title: () => 'Kalau data kartu atau m-banking sempat terisi',
            detail: () => 'Hubungi bank lewat nomor resmi, minta kartu atau akses diblokir sementara, dan periksa mutasi terbaru.'
        },
        email: {
            title: () => 'Amankan email utamamu',
            detail: () => 'Email adalah kunci ke akun lain. Ganti kata sandinya, aktifkan verifikasi dua langkah, dan cek apakah ada aturan penerusan email yang tidak kamu buat.'
        },
        sesi: {
            title: () => 'Keluar dari semua perangkat',
            detail: () => 'Buka pengaturan keamanan akun, keluarkan semua sesi dan perangkat yang tidak dikenal, lalu cek nomor dan email pemulihan.'
        },
        kontak: {
            title: () => 'Peringatkan kontakmu',
            detail: () => 'Pelaku sering memakai akunmu untuk meminta uang ke teman dan keluarga. Kabari mereka lewat jalur lain bahwa akunmu dibobol. Teks siap pakai ada di kartu “Peringatkan orang lain”.'
        },
        bankakun: {
            title: () => 'Kalau akun itu terhubung ke uang',
            detail: () => 'Hubungi bank atau e-wallet lewat jalur resmi, cek mutasi, dan bekukan sementara bila ada transaksi yang tidak kamu kenal.'
        },
        platform: {
            title: () => 'Laporkan akunmu ke platformnya',
            detail: () => 'Pakai menu bantuan atau laporan di aplikasi resmi platform tersebut.'
        }
    };

    // [id langkah, prioritas untuk [kurang dari 1 jam, hari ini, lebih dari sehari]]. 1 = sekarang, 2 = hari ini, 3 = setelahnya.
    const PLANS = {
        transfer: [
            ['bank', [1, 1, 2]], ['bukti', [1, 1, 1]], ['iasc', [2, 1, 1]], ['putus', [2, 2, 2]],
            ['akunuang', [3, 3, 3]], ['polisi', [3, 3, 2]], ['cerita', [3, 2, 2]]
        ],
        otp: [
            ['kendali', [1, 1, 1]], ['layanan', [1, 1, 2]], ['mutasi', [2, 2, 1]], ['wa', [2, 2, 2]],
            ['bukti', [2, 2, 2]], ['putus', [2, 2, 3]], ['iasc', [3, 3, 2]], ['cerita', [3, 3, 3]]
        ],
        tautan: [
            ['tutup', [1, 1, 1]], ['sandi', [1, 1, 1]], ['apk', [1, 1, 1]], ['bankdata', [1, 1, 2]],
            ['bukti', [2, 2, 2]], ['iasc', [3, 3, 2]], ['cerita', [3, 3, 3]]
        ],
        dibobol: [
            ['kendali', [1, 1, 1]], ['email', [1, 1, 1]], ['sesi', [2, 1, 1]], ['kontak', [1, 1, 2]],
            ['bankakun', [2, 2, 2]], ['bukti', [2, 2, 2]], ['platform', [3, 3, 3]], ['iasc', [3, 3, 2]], ['cerita', [3, 3, 3]]
        ]
    };

    const TIME_INDEX = { baru: 0, hari: 1, lama: 2 };
    const PRIORITY = { 1: ['Sekarang', 't-rose'], 2: ['Hari ini', 't-amber'], 3: ['Setelahnya', 't-violet'] };

    const WARNING = 'Hati-hati, lagi ramai penipuan yang minta transfer, kode OTP, atau menyuruh klik tautan dengan alasan mendesak. Jangan buru-buru. Cek dulu lewat nomor atau aplikasi resmi, jangan bagikan OTP ke siapa pun, dan kalau terlanjur, segera hubungi bank dan lapor ke OJK 157.';

    /* ---------- Util ---------- */

    const $ = id => document.getElementById(id);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function toast(message) {
        const el = $('jedaToast');
        if (!el) return;
        el.textContent = message;
        el.classList.add('is-shown');
        clearTimeout(window.__jedaToastTimer);
        window.__jedaToastTimer = setTimeout(() => el.classList.remove('is-shown'), 2800);
    }

    async function copyText(text, success) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                const area = document.createElement('textarea');
                area.value = text;
                area.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(area);
                area.select();
                const ok = document.execCommand('copy');
                area.remove();
                if (!ok) throw new Error('copy failed');
            }
            toast(success);
        } catch (_) {
            toast('Salin manual: pilih teks lalu tekan Ctrl+C.');
        }
    }

    function el(tag, props, ...kids) {
        const node = document.createElement(tag);
        for (const [k, v] of Object.entries(props || {})) {
            if (k === 'class') node.className = v;
            else node.setAttribute(k, v);
        }
        for (const kid of kids.flat()) {
            if (kid === null || kid === undefined) continue;
            node.append(kid.nodeType ? kid : document.createTextNode(String(kid)));
        }
        return node;
    }

    // Pencatatan progres opsional. Gagal tidak mengganggu halaman.
    function logStep(id, done) {
        fetch('/jeda/api/checklist', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ feature: 'jam-emas', situation: sit + '-' + time, item: id, done })
        }).catch(() => {});
    }

    /* ---------- State ---------- */

    let sit = 'transfer';
    let time = 'baru';
    const done = {}; // `${sit}|${time}|${id}` -> true

    const key = id => sit + '|' + time + '|' + id;

    function currentPlan() {
        const idx = TIME_INDEX[time];
        return PLANS[sit]
            .map(([id, p], i) => ({ id, p: p[idx], i }))
            .sort((a, b) => a.p - b.p || a.i - b.i);
    }

    /* ---------- Render ---------- */

    function renderHeader() {
        $('jgHead').textContent = SIT[sit].head;
        $('jgLead').textContent = LEAD[time];
        $('jgCerita').textContent = '“' + SIT[sit].cerita + '”';
    }

    function updateProgress() {
        const plan = currentPlan();
        const total = plan.length;
        const finished = plan.filter(s => done[key(s.id)]).length;
        $('jgBar').style.width = Math.round((finished / total) * 100) + '%';
        $('jgCount').textContent = finished === total
            ? 'Semua langkah selesai. Jaga dirimu, dan kabari orang yang kamu percaya.'
            : finished + ' dari ' + total + ' langkah selesai';
    }

    function toggleStep(id, node) {
        const k = key(id);
        done[k] = !done[k];
        node.classList.toggle('is-done', !!done[k]);
        node.querySelector('.jg-box').setAttribute('aria-checked', String(!!done[k]));
        updateProgress();
        logStep(id, !!done[k]);
    }

    function renderSteps() {
        const list = $('jgSteps');
        list.replaceChildren();
        currentPlan().forEach((step, n) => {
            const def = STEPS[step.id];
            const [label, tone] = PRIORITY[step.p];
            const title = def.title(sit);
            const isDone = !!done[key(step.id)];

            const acts = (def.acts || []).map(a => {
                const info = ACT[a];
                const props = { class: a === def.acts[0] ? 'btn' : 'btn ghost', href: info.href };
                if (info.ext) { props.target = '_blank'; props.rel = 'noopener'; }
                return el('a', props, info.text);
            });

            const box = el('button', { type: 'button', class: 'jg-box', role: 'checkbox', 'aria-checked': String(isDone), 'aria-label': 'Tandai selesai: ' + title });
            const node = el('div', { class: 'jg-step' + (isDone ? ' is-done' : '') },
                box,
                el('div', { class: 'jg-body' },
                    el('div', { class: 'jg-meta' }, el('span', { class: 'tag ' + tone }, label), el('span', { class: 'small' }, 'Langkah ' + (n + 1))),
                    el('h3', {}, title),
                    el('p', { class: 'mut', style: 'margin:0' }, def.detail(time, sit)),
                    acts.length ? el('div', { class: 'row jg-acts' }, acts) : null));

            node.addEventListener('click', e => {
                if (e.target.closest('a')) return;
                toggleStep(step.id, node);
            });
            list.append(node);
        });
        updateProgress();
    }

    function buildReport() {
        const v = id => ($(id).value || '').trim() || '(belum diisi)';
        return [
            'Kepada Yth. Tim Penanganan Pengaduan,',
            '',
            'Saya ingin melaporkan dugaan penipuan dengan rincian berikut.',
            '',
            'Jenis kejadian: ' + SIT[sit].report,
            'Waktu kejadian: ' + v('jgWaktu'),
            'Pelaku menghubungi lewat: ' + $('jgMedia').value,
            'Nominal kerugian: ' + v('jgNominal'),
            'Rekening atau akun tujuan: ' + v('jgRek'),
            '',
            'Kronologi singkat:',
            v('jgKronologi'),
            '',
            'Bukti yang saya simpan: tangkapan layar percakapan, bukti transaksi, serta data rekening atau akun pelaku.',
            '',
            'Mohon bantuan untuk menindaklanjuti laporan ini, termasuk bila memungkinkan menahan transaksi dan meninjau rekening atau akun yang terkait.',
            '',
            'Terima kasih.',
            v('jgNama'),
            'Kontak: ' + v('jgKontak')
        ].join('\n');
    }

    function renderReport() {
        $('jgLaporan').textContent = buildReport();
    }

    function renderAll() {
        renderHeader();
        renderSteps();
        renderReport();
    }

    /* ---------- Events ---------- */

    function bindGroup(groupId, attr, apply) {
        const buttons = [...$(groupId).querySelectorAll('button')];
        buttons.forEach(btn => btn.addEventListener('click', () => {
            buttons.forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
            apply(btn.dataset[attr]);
            renderAll();
        }));
    }

    bindGroup('jgSit', 'sit', v => { sit = v; });
    bindGroup('jgTime', 'time', v => { time = v; });

    ['jgWaktu', 'jgMedia', 'jgNominal', 'jgRek', 'jgKronologi', 'jgNama', 'jgKontak']
        .forEach(id => $(id).addEventListener('input', renderReport));

    $('jgSalinCerita').addEventListener('click', () => copyText(SIT[sit].cerita, 'Teks cerita disalin.'));
    $('jgSalinLaporan').addEventListener('click', () => copyText(buildReport(), 'Draf laporan disalin.'));
    $('jgSalinPeringatan').addEventListener('click', () => copyText(WARNING, 'Teks peringatan disalin.'));

    renderAll();
})();
</script>

@endsection