/*
 * Bedah Pesan: mesin penilaian lokal, tampilan hasil, dan analisis AI opsional.
 *
 * - Penilaian berjalan 100% di browser. Teks pesan TIDAK dikirim ke server.
 * - Ke server hanya dikirim skor, daftar id indikator, dan panjang teks (untuk progres sesi).
 * - Teks baru dikirim ke server kalau pengguna menekan "Kirim ke AI dan analisis".
 */
(() => {
  'use strict';

  /* ================= 1. Normalisasi teks ================= */

  const LEET = { '0': 'o', '1': 'i', '3': 'e', '4': 'a', '5': 's', '7': 't', '@': 'a', '$': 's' };

  // "0TP", "tr4nsfer", "o t p" -> "otp", "transfer", "otp"
  function normalize(input) {
    let s = String(input).normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    s = s.replace(/\b(?:[a-z][\s.\-_*]){2,}[a-z]\b/g, m => m.replace(/[\s.\-_*]/g, ''));
    s = s.replace(/[a-z0-9@$]+/g, tok =>
      /[a-z]/.test(tok) && /[0-9@$]/.test(tok) && !/\d{2,}/.test(tok)
        ? tok.replace(/[0-9@$]/g, c => LEET[c])
        : tok);
    return s;
  }

  /* ================= 2. Pemeriksaan tautan di dalam pesan ================= */

  const URL_RX = /(?:https?:\/\/|www\.)[^\s<>"')\]]+|\b(?:bit\.ly|s\.id|tinyurl\.com|cutt\.ly|shorturl\.at|is\.gd|wa\.me|t\.me)\/[^\s<>"')\]]+/gi;
  const SHORTENERS = ['bit.ly', 's.id', 'tinyurl.com', 'cutt.ly', 'shorturl.at', 'is.gd', 't.co', 'rebrand.ly'];
  const SLD = ['co', 'com', 'net', 'org', 'go', 'or', 'ac', 'sch', 'web', 'my', 'biz'];
  const BRANDS = ['bank', 'bca', 'bri', 'bni', 'mandiri', 'cimb', 'btn', 'dana', 'ovo', 'gopay', 'shopee',
    'tokopedia', 'lazada', 'bpjs', 'pajak', 'djp', 'kominfo', 'polri', 'ojk', 'jne', 'jnt', 'sicepat', 'pln',
    'telkomsel', 'indosat', 'tiktok', 'instagram', 'facebook', 'whatsapp', 'google', 'paypal', 'netflix'];
  const LURE = /(login|log-in|verif|verifikasi|secure|security|klaim|claim|hadiah|gift|bonus|promo|update|akun|account|otp|reward)/;

  function inspectUrl(raw) {
    const reasons = [];
    const s = raw.replace(/[.,;:!?]+$/, '');
    let host;
    let path = '';
    try {
      const u = new URL(/^[a-z]+:\/\//i.test(s) ? s : 'https://' + s);
      host = u.hostname.toLowerCase().replace(/\.$/, '');
      path = u.pathname + u.search;
    } catch (_) {
      return reasons;
    }

    if (/^\d{1,3}(\.\d{1,3}){3}$/.test(host)) reasons.push('Alamat berupa angka IP, bukan nama situs');
    if (host.includes('xn--')) reasons.push('Nama domain memakai karakter yang menyerupai huruf biasa');
    if (SHORTENERS.includes(host)) reasons.push('Tautan dipendekkan sehingga tujuan aslinya tersembunyi');

    const labels = host.split('.');
    const n = labels.length;
    const regCount = n >= 3 && labels[n - 1].length === 2 && SLD.includes(labels[n - 2]) ? 3 : 2;
    const registrable = labels.slice(-regCount).join('.');
    const front = labels.slice(0, Math.max(0, n - regCount));
    const frontTokens = front.join('.').split(/[.\-_]/).filter(Boolean);

    const brand = BRANDS.find(b => frontTokens.some(t => t === b || (b.length >= 4 && t.startsWith(b))));
    if (brand) {
      reasons.push(`Nama "${brand}" hanya ada di depan alamat, domain utamanya ${registrable}`);
    } else if (front.some(l => SLD.includes(l))) {
      reasons.push(`Ada akhiran seperti ".com" di tengah alamat untuk mengecoh, domain utamanya ${registrable}`);
    }
    if (n >= 2 && LURE.test(labels[n - regCount])) reasons.push('Nama domain memuat kata pancingan (login, verifikasi, klaim, dsb.)');
    if (/\.(apk|exe|scr|bat|msi)(?:$|\?)/i.test(path)) reasons.push('Tautan mengarah ke file yang memasang program atau aplikasi');
    return reasons;
  }

  /* ================= 3. Aturan penilaian ================= */

  // Cari kecocokan di teks asli dulu (supaya kutipan persis seperti yang diketik), lalu di teks ternormalisasi.
  function grab(ctx, patterns) {
    const out = [];
    for (const p of patterns) {
      for (const src of [ctx.lower, ctx.norm]) {
        const re = new RegExp(p.source, p.flags.includes('g') ? p.flags : p.flags + 'g');
        let m;
        let found = false;
        while ((m = re.exec(src)) && out.length < 3) {
          found = true;
          const t = m[0].trim();
          if (t && !out.includes(t)) out.push(t);
          if (m.index === re.lastIndex) re.lastIndex++;
        }
        if (found) break;
      }
    }
    return out;
  }

  const RULES = [
    {
      id: 'urgensi', label: 'Tekanan waktu', tone: 'amber', weight: 16,
      why: 'Penipu membuatmu panik supaya tidak sempat berpikir atau bertanya ke orang lain.',
      find: ctx => grab(ctx, [
        /\b(?:segera|secepatnya|buruan|detik ini|hari ini juga|batas waktu|kedaluwarsa|kadaluarsa|hangus|sisa slot|slot (?:tinggal|terbatas)|terakhir)\b/,
        /\b(?:dalam|selama|maksimal|paling lambat)\s+\d+\s*(?:menit|jam)\b/,
        /\b\d+\s*menit\b/,
        /\b(?:kirim|transfer|klik|bayar|konfirmasi|balas|isi)\b[^.!?\n]{0,40}\bsekarang\b/,
      ]),
    },
    {
      id: 'kerahasiaan', label: 'Disuruh merahasiakan', tone: 'rose', weight: 20,
      why: 'Orang yang jujur tidak melarangmu bertanya ke keluarga, teman, atau pihak resmi. Rahasia membuatmu terisolasi.',
      find: ctx => grab(ctx, [
        /\b(?:jangan|jgn)\s+(?:sampai\s+)?(?:beri tahu|kasih tahu|bilang|cerita|ceritakan|sebarkan|bagikan|kabari|hubungi)\b/,
        /\brahasia(?:kan|kan ini)?\b/,
        /\bhanya\s+(?:kita|kamu|anda)\s+(?:berdua|yang tahu)\b/,
      ]),
    },
    {
      id: 'uang', label: 'Permintaan uang', tone: 'rose', weight: 22,
      why: 'Lowongan, hadiah, atau layanan resmi tidak meminta biaya di muka lewat transfer ke rekening pribadi.',
      find: ctx => grab(ctx, [
        /\b(?:transfer|setor(?:kan)?|top ?up|bayar(?:kan)?|deposit|uang muka|dp|dana talangan|pulsa|e-?wallet)\b/,
        /\bbiaya\s+(?:admin|administrasi|pendaftaran|aktivasi|asuransi|pengiriman|verifikasi|pajak)\b/,
        /\b(?:no\.?\s*)?rekening\b/,
      ]),
    },
    {
      id: 'kode', label: 'Minta kode atau data pribadi', tone: 'rose', weight: 30,
      why: 'OTP, PIN, dan kata sandi tidak pernah diminta oleh bank, e-wallet, atau marketplace yang asli.',
      find: ctx => grab(ctx, [
        /\b(?:otp|kode (?:verifikasi|rahasia|login|sms|keamanan|pin)|kata sandi|password|pin|cvv|cvc|(?:6|enam)\s*digit)\b/,
        /\b(?:data (?:kartu|ktp|diri|pribadi)|nomor kartu|nomor ktp|foto ktp|selfie ktp)\b/,
      ]),
    },
    {
      id: 'iming', label: 'Iming-iming tidak wajar', tone: 'amber', weight: 20,
      why: 'Hadiah atau gaji yang terlalu besar tanpa proses jelas adalah umpan klasik.',
      find: ctx => grab(ctx, [
        /\bselamat[^.!?\n]{0,50}\b(?:lolos|menang|terpilih|mendapat|mendapatkan|berhasil)\b/,
        /\b(?:lolos seleksi|hadiah|undian|pemenang|giveaway|komisi|cuan|profit|passive income|bonus)\b/,
        /\bgaji\s*(?:besar|rp|\d)[^\n]{0,20}/,
        /\bper\s+(?:minggu|hari)\b/,
        /\b(?:tanpa\s+(?:modal|syarat|wawancara|interview)|lowongan|karyawan remote|kerja (?:online|dari rumah|sampingan|santai)|investasi (?:pasti|cepat))\b/,
      ]),
    },
    {
      id: 'pihak_resmi', label: 'Mengaku pihak resmi', tone: 'amber', weight: 16,
      why: 'Penipu sering memakai nama bank, kurir, atau instansi supaya terlihat meyakinkan. Cek lewat kanal resmi, bukan kontak di pesan.',
      find: ctx => grab(ctx, [
        /\b(?:admin|petugas|customer service|cs|tim|pihak|layanan|call center|kurir|staf|staff)\s+(?:resmi\s+)?(?:bank|bca|bri|bni|mandiri|cimb|dana|ovo|gopay|shopee|tokopedia|bpjs|polisi|kejaksaan|ojk|pajak|jne|jnt|sicepat|pln|telkomsel|indosat|whatsapp|instagram|facebook|tiktok)\b/,
        /\b(?:dari|atas nama)\s+(?:kepolisian|polisi|kejaksaan|kominfo|ojk|bank indonesia|bpjs|dirjen pajak)\b/,
        /\bmengaku\b/,
      ]),
    },
    {
      id: 'ancaman', label: 'Ancaman atau kondisi darurat', tone: 'amber', weight: 16,
      why: 'Rasa takut mematikan nalar. Pesan resmi tidak mengancam lewat chat.',
      find: ctx => grab(ctx, [
        /\b(?:diblokir|dibekukan|ditangguhkan|dinonaktifkan|ditutup permanen|ditangkap|dipidana|pidana|denda|ditahan|kasus hukum|tuntutan|surat panggilan|tilang|kecelakaan|darurat)\b/,
      ]),
    },
    {
      id: 'nomor_baru', label: 'Mengaku kenalan dengan nomor baru', tone: 'amber', weight: 14,
      why: 'Modus pura-pura jadi keluarga atau teman. Telepon nomor lama orang itu untuk memastikan.',
      find: ctx => grab(ctx, [
        /\b(?:ganti nomor|nomor baru|nomor lama (?:hilang|rusak|tidak aktif)|hp (?:aku|saya|ku)?\s*(?:rusak|hilang))\b/,
        /\bini (?:aku|saya)\b[^.!?\n]{0,25}\b(?:nomor|no)\b/,
      ]),
    },
    {
      id: 'tautan', label: 'Ada tautan', tone: 'amber', weight: 10,
      why: 'Tautan di pesan tak dikenal bisa mengarah ke situs tiruan atau memasang aplikasi berbahaya.',
      find: ctx => ctx.urls.slice(0, 3),
    },
    {
      id: 'tautan_menyamar', label: 'Tautan menyamar', tone: 'rose', weight: 24,
      why: ctx => ctx.urlReasons.join('. ') + '.',
      find: ctx => {
        const hit = [];
        ctx.urlReasons = [];
        for (const u of ctx.urls) {
          const r = inspectUrl(u);
          if (r.length) {
            hit.push(u);
            r.forEach(x => { if (!ctx.urlReasons.includes(x)) ctx.urlReasons.push(x); });
          }
        }
        return hit.slice(0, 3);
      },
    },
  ];

  const COMBOS = [
    { bonus: 10, text: 'minta uang sambil menekan atau menyuruh merahasiakan', test: h => h.uang && (h.kerahasiaan || h.urgensi) },
    { bonus: 15, text: 'minta kode atau data sambil menekan atau menyamar', test: h => h.kode && (h.urgensi || h.tautan || h.pihak_resmi || h.ancaman) },
    { bonus: 15, text: 'hadiah atau pekerjaan yang disertai permintaan bayar di muka', test: h => h.iming && h.uang },
  ];

  const levelOf = s => (s === 0 ? 'Belum terdeteksi' : s >= 65 ? 'Tinggi' : s >= 30 ? 'Sedang' : 'Perlu diperiksa');

  function safeSteps(h, score) {
    const steps = ['Berhenti dulu: jangan balas, jangan klik, jangan transfer.'];
    if (h.kode) steps.push('Jangan bagikan OTP, PIN, atau kata sandi ke siapa pun, termasuk yang mengaku dari bank.');
    if (h.uang) steps.push('Verifikasi lewat aplikasi atau nomor resmi, bukan kontak yang ada di pesan.');
    if (h.tautan_menyamar || h.tautan) steps.push('Buka situs atau aplikasi resminya sendiri, jangan lewat tautan di pesan.');
    if (h.nomor_baru) steps.push('Telepon nomor lama orang itu untuk memastikan.');
    if (h.iming) steps.push('Cek lowongan atau hadiah lewat situs resmi. Yang asli tidak meminta biaya di muka.');
    if (score >= 30) steps.push('Tanyakan ke orang yang kamu percaya sebelum bertindak.');
    return steps.slice(0, 4);
  }

  /* ================= 4. Fungsi utama ================= */

  function analyze(text) {
    const src = String(text);
    const ctx = {
      lower: src.normalize('NFKC').toLowerCase(),
      norm: normalize(src),
      urls: Array.from(new Set(src.match(URL_RX) || [])).slice(0, 5),
      urlReasons: [],
    };

    const findings = [];
    const hit = {};
    let total = 0;
    for (const rule of RULES) {
      const evidence = rule.find(ctx);
      if (evidence && evidence.length) {
        hit[rule.id] = true;
        total += rule.weight;
        findings.push({
          id: rule.id, label: rule.label, tone: rule.tone, evidence,
          why: typeof rule.why === 'function' ? rule.why(ctx) : rule.why,
        });
      }
    }

    const combos = COMBOS.filter(c => c.test(hit));
    if (combos.length) {
      total += combos.reduce((a, c) => a + c.bonus, 0);
      findings.push({
        id: 'kombinasi', label: 'Kombinasi berbahaya', tone: 'rose', evidence: [],
        why: 'Pola ini muncul bersamaan: ' + combos.map(c => c.text).join('; ') + '.',
      });
    }

    const score = Math.min(100, total);
    return {
      score,
      level: levelOf(score),
      fill: score === 0 ? 0 : Math.min(5, Math.ceil(score / 20)),
      findings,
      steps: safeSteps(hit, score),
    };
  }

  const api = { analyze, normalize, inspectUrl, RULES };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  if (typeof document === 'undefined') return;
  window.JedaBedah = api;

  /* ================= 5. Tampilan ================= */

  const $ = (s, r = document) => r.querySelector(s);
  const input = $('#bedahInput');
  if (!input) return;
  const runBtn = $('#bedahRun');
  const sampleBtn = $('#bedahSample');
  const aiBtn = $('#bedahAiRun');
  const aiOut = $('#bedahAiOut');
  const levelEl = $('#bedahLevel');
  const meter = $('#bedahMeter');
  const summary = $('#bedahSummary');
  const flagsEl = $('#bedahFlags');
  const stepsEl = $('#bedahSteps');

  const SAMPLE = 'Selamat, kamu lolos jadi karyawan remote dengan gaji Rp8 juta per minggu. Konfirmasi dalam 30 menit dan jangan beri tahu siapa pun. Transfer biaya admin Rp150.000 untuk mengunci posisi. Klik: https://bankcontoh.com.login-verif.co.id/verifikasi';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function toast(message) {
    const el = $('#jedaToast');
    if (!el) return;
    el.textContent = message;
    el.classList.add('is-shown');
    clearTimeout(window.__jedaToastTimer);
    window.__jedaToastTimer = setTimeout(() => el.classList.remove('is-shown'), 2800);
  }

  // Semua teks dari pengguna/AI dimasukkan lewat textContent, tidak pernah innerHTML.
  function h(tag, props, ...kids) {
    const e = document.createElement(tag);
    for (const [k, v] of Object.entries(props || {})) {
      if (k === 'class') e.className = v;
      else if (k === 'style') e.style.cssText = v;
      else e.setAttribute(k, v);
    }
    for (const kid of kids.flat()) {
      if (kid === null || kid === undefined || kid === false) continue;
      e.append(kid.nodeType ? kid : document.createTextNode(String(kid)));
    }
    return e;
  }

  function evidenceLine(evidence, tone) {
    const parts = [];
    evidence.forEach((t, i) => {
      if (i) parts.push(', ');
      parts.push(h('span', { class: 'mark t-' + tone }, h('b', {}, t)));
    });
    return parts;
  }

  function flagEl(f) {
    return h('div', { class: 'flag' },
      h('span', { class: 'tag t-' + f.tone }, f.label),
      f.evidence.length ? h('p', { class: 'mut', style: 'margin:8px 0 0;word-break:break-word' }, 'Terdeteksi: ', evidenceLine(f.evidence, f.tone)) : null,
      h('p', { class: 'mut', style: 'margin:6px 0 0' }, f.why));
  }

  function listEl(items) {
    return h('ul', { style: 'margin:6px 0 0;padding-left:20px' }, items.map(s => h('li', {}, s)));
  }

  function render(r) {
    levelEl.textContent = r.level;
    meter.classList.toggle('hi', r.level === 'Tinggi');
    [...meter.children].forEach((sp, i) => sp.classList.toggle('f', i < r.fill));
    const n = r.findings.filter(f => f.id !== 'kombinasi').length;
    summary.textContent = n
      ? `${n} pola perlu diperiksa. Ini tanda yang perlu dicek, bukan vonis penipuan.`
      : 'Belum ada pola umum yang cocok. Ini bukan jaminan pesan aman: tetap verifikasi pengirim kalau menyangkut uang atau data pribadi.';
    flagsEl.replaceChildren(...r.findings.map(flagEl));
    stepsEl.replaceChildren(h('div', { class: 'q' }, h('b', {}, 'Langkah aman:'), listEl(r.steps)));
  }

  // Hanya skor + id indikator + panjang teks yang dikirim. Isi pesan tidak.
  function logResult(r, length) {
    fetch('/jeda/api/analisis-pesan', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ score: r.score, indicators: r.findings.map(f => f.id), text_length: length }),
    }).catch(err => console.warn('[Jeda] progres tidak tersimpan', err));
  }

  function run() {
    const value = input.value.trim();
    if (!value) {
      toast('Tempel pesan yang ingin diperiksa terlebih dahulu.');
      input.focus();
      return;
    }
    const r = analyze(value);
    render(r);
    logResult(r, value.length);
    toast('Pemeriksaan selesai di perangkatmu.');
  }

  runBtn.addEventListener('click', run);
  sampleBtn?.addEventListener('click', () => { input.value = SAMPLE; run(); });
  input.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') run(); });

  /* ================= 6. Analisis AI (opsional) ================= */

  const LEVEL_TONE = { Tinggi: 'rose', Sedang: 'amber', Rendah: 'violet' };

  function renderAi(d) {
    aiOut.replaceChildren(
      h('div', { class: 'stack', style: 'margin-top:14px' },
        h('div', { class: 'row', style: 'gap:8px;flex-wrap:wrap' },
          h('span', { class: 'tag t-violet' }, 'Dianalisis AI, bisa salah'),
          h('span', { class: 'tag t-' + (LEVEL_TONE[d.level] || 'amber') }, 'Risiko: ' + d.level)),
        d.jenis ? h('p', { style: 'margin:0' }, h('b', {}, d.jenis)) : null,
        d.ringkasan ? h('p', { class: 'mut', style: 'margin:0' }, d.ringkasan) : null,
        d.indikator.map(i => h('div', { class: 'flag' },
          h('span', { class: 'tag t-amber' }, i.judul),
          i.bukti ? h('p', { class: 'mut', style: 'margin:8px 0 0;word-break:break-word' }, 'Kutipan: ', h('span', { class: 'mark t-amber' }, h('b', {}, i.bukti))) : null,
          i.penjelasan ? h('p', { class: 'mut', style: 'margin:6px 0 0' }, i.penjelasan) : null)),
        d.langkah_aman.length ? h('div', { class: 'q' }, h('b', {}, 'Langkah aman:'), listEl(d.langkah_aman)) : null,
        h('p', { class: 'small', style: 'margin:0' },
          d.masked ? 'Nomor HP, rekening, dan email disamarkan sebelum dikirim. Hasil ini tidak disimpan.' : 'Hasil ini tidak disimpan.')));
  }

  function aiError(message) {
    aiOut.replaceChildren(h('div', { class: 'notice', role: 'status', style: 'margin-top:14px' },
      h('strong', {}, 'Analisis AI belum bisa dipakai. '), message,
      h('br'), h('small', {}, 'Pemeriksaan dasar di sebelah kanan tetap berfungsi.')));
  }

  aiBtn?.addEventListener('click', async () => {
    const value = input.value.trim();
    if (value.length < 10) { toast('Tempel pesan (minimal 10 karakter) dulu.'); input.focus(); return; }
    if (value.length > 4000) { toast('Pesan terlalu panjang untuk analisis AI (maksimal 4.000 karakter).'); return; }

    const label = aiBtn.textContent;
    aiBtn.disabled = true;
    aiBtn.textContent = 'Menganalisis…';
    aiOut.replaceChildren(h('div', { class: 'notice', role: 'status', style: 'margin-top:14px' }, 'AI sedang membaca pesanmu…'));

    try {
      const res = await fetch('/jeda/api/bedah-ai', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ text: value }),
      });
      const data = await res.json().catch(() => ({}));
      if (res.status === 429) throw new Error('Terlalu sering. Tunggu sebentar lalu coba lagi.');
      if (res.status === 419) throw new Error('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
      if (!res.ok || !data.ok) throw new Error(data.message || 'Terjadi kesalahan. Coba lagi nanti.');
      renderAi(data);
    } catch (err) {
      aiError(err instanceof TypeError ? 'Tidak bisa terhubung ke server.' : err.message);
    } finally {
      aiBtn.disabled = false;
      aiBtn.textContent = label;
    }
  });
})();
