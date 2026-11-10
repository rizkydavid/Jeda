(() => {
  'use strict';

  const root = document.getElementById('bedahRoot');
  if (!root) return;

  const AI_URL = root.dataset.aiUrl;
  const LOG_URL = root.dataset.logUrl;
  const CSRF = root.dataset.csrf;
  const AI_MAX = 4000;
  const AI_TIMEOUT = 45000;

  const $ = (id) => document.getElementById(id);
  const input = $('bedahInput');
  const runBtn = $('bedahRun');
  const sampleBtn = $('bedahSample');
  const aiToggle = $('bedahAiToggle');
  const statusEl = $('bedahAiOut');
  const levelEl = $('bedahLevel');
  const meterEl = $('bedahMeter');
  const summaryEl = $('bedahSummary');
  const flagsEl = $('bedahFlags');
  const stepsEl = $('bedahSteps');

  const TITLES = {
    urgensi: 'Tekanan waktu', kerahasiaan: 'Disuruh merahasiakan', uang: 'Diminta mengirim uang',
    kode: 'Diminta kode atau OTP', iming: 'Iming-iming tidak wajar', pihak_resmi: 'Mengaku pihak resmi',
    ancaman: 'Ancaman', nomor_baru: 'Mengaku nomor baru', tautan: 'Ada tautan',
    tautan_menyamar: 'Tautan menyamar', unduh_aplikasi: 'Diminta mengunduh atau memasang aplikasi',
    rantai_jebakan: 'Imbalan dipasangkan dengan permintaan aksi', kombinasi: 'Beberapa tanda sekaligus',
  };
  const DERIVED_NOTES = {
    rantai_jebakan: 'Pola klasik: dijanjikan bayaran atau hadiah, lalu diminta mengunduh, mengirim, atau membuka sesuatu.',
    kombinasi: 'Penipuan biasanya memakai beberapa tekanan sekaligus supaya kamu tidak sempat berpikir.',
  };

  const AKSI = ['uang', 'kode', 'unduh_aplikasi', 'tautan_menyamar'];
  const RANTAI_BONUS = 25;
  const COMBO_MIN = 3;
  const COMBO_BONUS = 15;
  const GATED_MAX = 10;

  const RULES = [
    { id: 'urgensi', weight: 15, why: 'Pelaku sengaja memburu-buru supaya kamu tidak sempat mengecek.',
      re: /(segera|sekarang juga|hari ini juga|buruan|batas waktu|dalam \d+ ?(jam|menit)|mendesak|kadaluarsa)/i },
    { id: 'kerahasiaan', weight: 15, why: 'Penipu tidak mau kamu bertanya ke orang lain yang bisa menyadarkanmu.',
      re: /(jangan (bilang|kasih tahu|beritahu|cerita)|rahasiakan|jangan sampai (ada yang )?tahu)/i },
    { id: 'uang', weight: 20, why: 'Kamu diminta mengeluarkan uang di depan. Ini tanda umum penipuan.',
      re: /(transfer(kan)? (dulu|dana|uang|sejumlah)|kirim (uang|dana|dulu)|top ?up|biaya (admin|administrasi|pendaftaran)|bayar (dulu|di muka|admin|biaya)|setor(kan)? |deposit|isi (pulsa|saldo))/i },
    { id: 'kode', weight: 30, why: 'Kode OTP dan PIN tidak pernah boleh dibagikan, bahkan ke petugas bank.',
      re: /(\botp\b|kode (verifikasi|otp|rahasia|sms|login)|\bpin\b|password|kata sandi|\bcvv\b)/i },
    { id: 'iming', weight: 20, why: 'Imbalan atau hadiah yang mencolok tanpa usaha hampir selalu jebakan.',
      re: /(hadiah|anda menang|kamu menang|undian|passive income|untung besar|(?:ku|aku|saya|gue|gw) bayar\w*\s+(?:rp\.?\s*)?\d[\d.,]*\s*(?:rb|ribu|jt|juta|k)\b|gaji\s+(?:rp\.?\s*)?\d)/i },
    { id: 'pihak_resmi', weight: 15, why: 'Pelaku sering mengaku dari instansi supaya terlihat meyakinkan.',
      re: /(\bbank\b|\bojk\b|polisi|kejaksaan|bea cukai|kurir|\bpln\b|\bbpjs\b|customer service|petugas)/i },
    { id: 'ancaman', weight: 20, why: 'Ancaman dipakai supaya kamu panik dan menuruti permintaan.',
      re: /(diblokir|akan blokir|ditutup|denda|pidana|dipenjara|ditangkap|disita)/i },
    { id: 'nomor_baru', weight: 10, why: 'Pelaku menyamar jadi kenalan yang "ganti nomor" untuk minta bantuan.',
      re: /(nomor baru|no\.? baru|ganti nomor|hp (saya|aku) (rusak|hilang))/i },
    { id: 'tautan', weight: 10, why: 'Tautan dari pesan tak dikenal bisa membawa ke situs palsu.',
      re: /(https?:\/\/\S+|www\.\S+|\b[a-z0-9-]+\.(com|id|xyz|top|click|link|site|online)\b|wa\.me\/\S+)/i },
    { id: 'tautan_menyamar', weight: 25, why: 'Tautan ini memakai pemendek atau meniru nama layanan resmi.',
      re: /(bit\.ly\/\S+|tinyurl\.com\/\S+|s\.id\/\S+|cutt\.ly\/\S+|rb\.gy\/\S+|\b(bca|bri|bni|mandiri|dana|ovo|gopay|shopee|tokopedia)[a-z0-9-]*\.(xyz|top|click|link|site|online)\b)/i },
    { id: 'unduh_aplikasi', weight: 25, why: 'Aplikasi dari luar toko resmi bisa mencuri data atau mengendalikan HP-mu.',
      re: /(\b(download|unduh|install|instal|pasang)\b[^.!?\n]{0,25}\b(app|aplikasi|apk|file)\b|\.apk\b|anydesk|teamviewer|rustdesk|screen ?share|bagikan layar)/i },
  ];

  const WEIGHTS = Object.fromEntries(RULES.map((r) => [r.id, r.weight]));

  const SAFE_STEPS = [
    'Jangan klik tautan, unduh aplikasi, atau balas dulu. Tarik napas, kamu tidak wajib menjawab sekarang.',
    'Cek lewat jalur resmi: aplikasi atau nomor resmi yang kamu cari sendiri, bukan yang ada di pesan.',
    'Jangan bagikan OTP, PIN, atau data pribadi ke siapa pun.',
    'Tanyakan ke orang yang kamu percaya sebelum bertindak.',
  ];
  const NEUTRAL_STEPS = [
    'Kalau ragu, tanyakan langsung ke pengirimnya lewat jalur lain (telepon atau bertemu).',
    'Jangan klik tautan, unduh aplikasi, atau kirim kode dan uang kalau diminta.',
  ];

  const SAMPLE = '[CONTOH FIKTIF] Selamat! Nomor kamu terpilih dapat hadiah Rp10 juta dari Bank Contoh. '
    + 'Segera klik http://bank-contoh-hadiah.xyz dalam 1 jam dan kirim kode OTP yang masuk. Jangan kasih tahu siapa-siapa ya.';

  const LEVEL_COLORS = {
    'Belum terdeteksi': '#16a34a',
    'Belum cukup konteks': '#64748b',
    'Perlu diperiksa': '#d97706',
    'Sedang': '#ea580c',
    'Tinggi': '#dc2626',
  };

  const levelOf = (score) =>
    score === 0 ? 'Belum terdeteksi' : score >= 65 ? 'Tinggi' : score >= 30 ? 'Sedang' : 'Perlu diperiksa';

  function evaluate(ids) {
    let score = ids.reduce((s, id) => s + WEIGHTS[id], 0);
    const aksi = ids.filter((id) => AKSI.includes(id));
    const pendukung = ids.filter((id) => !AKSI.includes(id));

    if (ids.length > 0 && aksi.length === 0 && pendukung.length < 2) {
      return { score: Math.min(score, GATED_MAX), level: 'Belum cukup konteks', gated: true, derived: [] };
    }

    const derived = [];
    if (ids.includes('iming') && aksi.length > 0) { score += RANTAI_BONUS; derived.push('rantai_jebakan'); }
    if (ids.length >= COMBO_MIN) { score += COMBO_BONUS; derived.push('kombinasi'); }
    score = Math.min(100, score);

    return { score, level: levelOf(score), gated: false, derived };
  }

  function analyzeLocal(text) {
    const indikator = [];
    const ids = [];
    for (const r of RULES) {
      const m = text.match(r.re);
      if (!m) continue;
      ids.push(r.id);
      indikator.push({ id: r.id, judul: TITLES[r.id], bukti: m[0].trim().slice(0, 80), penjelasan: r.why });
    }

    const ev = evaluate(ids);
    ev.derived.forEach((id) => indikator.push({ id, judul: TITLES[id], bukti: '', penjelasan: DERIVED_NOTES[id] }));

    let ringkasan;
    if (ev.gated) {
      ringkasan = 'Belum ada permintaan berisiko (kirim uang, kode OTP, unduh aplikasi) di pesan ini. '
        + 'Pesannya belum cukup lengkap untuk disimpulkan sebagai penipuan.';
    } else if (ids.length === 0) {
      ringkasan = 'Belum ada permintaan atau tekanan yang mencurigakan di pesan ini. Ini bukan jaminan aman. '
        + 'Kalau pesannya belum utuh, konteksnya mungkin masih kurang untuk dinilai.';
    } else {
      ringkasan = `Ditemukan ${ids.length} tanda penipuan di pesan ini. Ini penilaian risiko, bukan vonis.`;
    }

    return {
      score: ev.score,
      level: ev.level,
      jenis: '',
      ringkasan,
      ids: ids.concat(ev.derived),
      indikator: ev.gated ? [] : indikator,
      langkah_aman: ev.gated || ids.length === 0 ? NEUTRAL_STEPS : SAFE_STEPS,
    };
  }

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }

  function render(r) {
    const color = LEVEL_COLORS[r.level] || LEVEL_COLORS['Belum cukup konteks'];
    const lit = r.score === 0 ? 0 : Math.max(1, Math.ceil(r.score / 20));

    levelEl.textContent = r.level;
    levelEl.style.color = color;
    levelEl.style.fontWeight = '700';

    const card = levelEl.closest('.card');
    if (card) card.style.boxShadow = `inset 5px 0 0 ${color}`;

    meterEl.dataset.level = r.level;
    [...meterEl.children].forEach((s, i) => {
      const on = i < lit;
      s.classList.toggle('on', on);
      s.style.opacity = '1';
      s.style.background = on ? color : '';
    });

    summaryEl.textContent = r.jenis ? `${r.jenis}. ${r.ringkasan}` : r.ringkasan;

    flagsEl.replaceChildren(...r.indikator.map((i) => {
      const card = el('div', 'card');
      card.append(el('h3', null, i.judul));
      if (i.bukti) {
        const q = el('p', 'small', `“${i.bukti}”`);
        q.style.fontStyle = 'italic';
        card.append(q);
      }
      card.append(el('p', 'mut', i.penjelasan));
      return card;
    }));

    if (r.langkah_aman && r.langkah_aman.length) {
      const card = el('div', 'card');
      card.append(el('h3', null, 'Langkah aman'));
      const ul = el('ul');
      r.langkah_aman.forEach((s) => ul.append(el('li', null, s)));
      card.append(ul);
      stepsEl.replaceChildren(card);
    } else {
      stepsEl.replaceChildren();
    }
  }

  const setStatus = (msg) => { statusEl.textContent = msg; };

  async function postJson(url, body, signal) {
    const res = await fetch(url, {
      method: 'POST',
      signal,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': CSRF,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    return { res, data };
  }

  function logLocal(r, textLength) {
    if (!LOG_URL) return;
    postJson(LOG_URL, {
      indicators: r.ids || r.indikator.map((i) => i.id),
      text_length: Math.min(12000, Math.max(1, textLength)),
    }).catch(() => {});
  }

  let runId = 0;

  async function run() {
    const text = input.value.trim();
    if (text.length < 10) {
      setStatus('Tempel pesan yang lebih lengkap dulu (minimal 10 karakter).');
      input.focus();
      return;
    }

    const my = ++runId;
    const local = analyzeLocal(text);
    render(local);

    if (!aiToggle || !aiToggle.checked || !AI_URL) {
      setStatus('Hasil dari pemeriksaan dasar di perangkatmu. Tidak ada teks yang dikirim.');
      logLocal(local, text.length);
      return;
    }

    runBtn.disabled = true;
    runBtn.textContent = 'Menganalisis dengan AI…';
    setStatus('Menunggu analisis AI. Sementara ini yang tampil adalah hasil pemeriksaan dasar.');

    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), AI_TIMEOUT);

    try {
      const { res, data } = await postJson(AI_URL, { text: text.slice(0, AI_MAX) }, ctrl.signal);
      if (my !== runId) return;
      if (!res.ok || !data.ok) throw new Error(data.message || 'Layanan AI bermasalah.');

      render(data);
      const parts = ['Dianalisis AI, bisa salah.'];
      if (data.masked) parts.push('Nomor HP, rekening, atau email disamarkan sebelum dikirim.');
      if (text.length > AI_MAX) parts.push(`Hanya ${AI_MAX} karakter pertama yang dianalisis.`);
      setStatus(parts.join(' '));
    } catch (e) {
      if (my !== runId) return;
      const reason = e.name === 'AbortError' ? 'AI terlalu lama menjawab.' : e.message;
      setStatus(`${reason} Memakai hasil pemeriksaan dasar.`);
      logLocal(local, text.length);
    } finally {
      clearTimeout(timer);
      if (my === runId) {
        runBtn.disabled = false;
        runBtn.textContent = 'Bedah pesan';
      }
    }
  }

  runBtn.addEventListener('click', run);
  sampleBtn.addEventListener('click', () => {
    input.value = SAMPLE;
    input.focus();
  });
})();