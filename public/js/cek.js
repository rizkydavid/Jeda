(() => {
  'use strict';

  // Halaman /cek: pemeriksaan tautan dan file. Semua teks dari server ditampilkan lewat textContent (bukan innerHTML).
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const $ = (selector) => document.querySelector(selector);

  function h(tag, attrs = {}, ...kids) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
      if (value === null || value === undefined || value === false) continue;
      if (key === 'class') node.className = value; else node.setAttribute(key, value);
    }
    for (const kid of kids.flat()) {
      if (kid === null || kid === undefined || kid === false) continue;
      node.append(kid instanceof Node ? kid : document.createTextNode(String(kid)));
    }
    return node;
  }

  async function postJson(url, payload) {
    let response;
    try {
      response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      });
    } catch {
      throw new Error('Tidak dapat terhubung ke server. Periksa koneksi internetmu.');
    }
    const body = await response.json().catch(() => ({}));
    if (response.status === 429) throw new Error('Terlalu banyak pemeriksaan dalam waktu singkat. Tunggu sebentar lalu coba lagi.');
    if (response.status === 419) throw new Error('Sesi habis. Muat ulang halaman lalu coba lagi.');
    if (!response.ok) throw new Error(body.errors?.url?.[0] || body.errors?.token?.[0] || body.message || 'Pemeriksaan belum berhasil. Coba lagi.');
    return body;
  }

  const TAG_BY_LEVEL = { 'Tinggi': 't-rose', 'Sedang': 't-amber', 'Rendah': 't-violet' };
  const STATE_LABEL = {
    ok: '', skipped: 'Dilewati', unavailable: 'Belum berhasil', not_configured: 'Belum diaktifkan',
    pending: 'Belum selesai', blocked: 'Tidak dibuka',
  };

  const LEVEL_CLASS = { Tinggi: 'is-high', Sedang: 'is-mid', Rendah: 'is-low' };

  /** Ringkasan yang dibaca manusia, diturunkan dari hasil pemeriksaan (bukan karangan), jadi selalu sesuai skor. */
  function linkSummary(r) {
    const signals = Array.isArray(r.signals) ? r.signals : [];
    const checks = r.checks || {};
    const hasGroup = (...g) => signals.some((x) => g.includes(x.group) && x.points > 0);
    const incomplete = Boolean(r.coverage && r.coverage.status !== 'lengkap');
    const hosts = Array.isArray(r.redirect_hosts) ? r.redirect_hosts : [];

    const headline = r.confirmed ? 'Banyak mesin keamanan menandai tautan ini berbahaya'
      : r.level === 'Tinggi' ? 'Jangan buka tautan ini'
      : r.level === 'Sedang' ? 'Hati-hati sebelum membuka tautan ini'
      : r.level === 'Rendah' ? 'Ada tanda kecil, cek ulang dulu'
      : incomplete ? 'Belum ada tanda bahaya, tetapi pemeriksaan belum lengkap'
      : r.official_brand ? `Alamat cocok dengan situs resmi ${r.official_brand}`
      : 'Tidak ada tanda bahaya yang terdeteksi';

    const reasons = signals.filter((x) => x.points > 0).sort((a, b) => b.points - a.points).slice(0, 3).map((x) => x.label);

    const passed = [];
    if (r.official_brand) passed.push(`Domain cocok dengan situs resmi ${r.official_brand}.`);
    if (!hasGroup('url_pattern', 'keyword')) passed.push('Alamat tidak memakai trik yang lazim dipakai penipu (domain tiruan, kata pancingan, karakter menyamar).');
    if (checks.safe_browsing?.state === 'ok' && checks.safe_browsing.result === 'clean') passed.push('Tidak ada di daftar situs berbahaya Google Safe Browsing.');
    if (checks.virustotal?.state === 'ok' && checks.virustotal.result === 'clean') passed.push(checks.virustotal.note);
    if (checks.redirect?.state === 'ok') {
      passed.push(hosts.length > 1 ? `Tujuan akhir berhasil dilacak: ${r.final_hostname || hosts[hosts.length - 1]}.` : 'Tautan tidak dialihkan ke domain lain.');
    }
    if (checks.page?.state === 'ok' && checks.page.password_form === false) passed.push('Halaman tujuan tidak meminta kata sandi.');
    if (checks.domain_age?.state === 'ok' && !hasGroup('domain') && checks.domain_age.note) passed.push(checks.domain_age.note);

    const unchecked = [];
    Object.values(checks).forEach((c) => {
      if (c.state !== 'ok') unchecked.push(c.note ? `${c.label}: ${c.note}` : c.label);
    });
    if (checks.virustotal?.state === 'ok' && checks.virustotal.result === 'unknown') {
      unchecked.push('VirusTotal: tautan ini belum pernah dianalisis di sana. Itu wajar untuk tautan baru dan bukan berarti aman.');
    }

    const steps = r.level === 'Tinggi' ? [
      'Jangan buka tautan ini dan jangan isi data apa pun (kata sandi, OTP, nomor kartu).',
      'Tanyakan ke pengirim lewat telepon atau bertemu langsung, bukan lewat chat yang sama. Akunnya bisa saja sudah diretas.',
      'Kalau sudah terlanjur diklik atau data sudah diisi: ganti kata sandi dari perangkat lain dan hubungi bank lewat nomor resmi. Lihat halaman Jam Emas.',
    ] : r.level === 'Sedang' ? [
      'Jangan isi data pribadi di halaman itu sebelum yakin.',
      'Konfirmasi ke pengirim lewat jalur lain (telepon atau bertemu).',
      'Lebih aman: buka situs resminya dengan mengetik alamatnya sendiri atau lewat aplikasi resmi.',
    ] : r.level === 'Rendah' ? [
      'Buka hanya kalau kamu mengenal pengirimnya.',
      'Jangan isi kata sandi atau OTP kalau halamannya terasa janggal.',
    ] : [
      'Hasil ini bukan jaminan aman. Tetap waspada kalau halaman meminta kata sandi, OTP, atau transfer.',
      'Untuk bank dan e-wallet, selalu buka lewat aplikasi atau alamat resmi yang kamu ketik sendiri.',
    ];

    return { headline, reasons, passed: [...new Set(passed.filter(Boolean))], unchecked, steps, hosts };
  }

  function routeView(r, hosts) {
    if (hosts.length < 2) return null;
    const last = hosts.length - 1;
    const nodes = [];
    hosts.forEach((host, index) => {
      if (index) nodes.push(h('span', { class: 'cek-arrow', 'aria-hidden': 'true' }, '→'));
      nodes.push(h('code', { class: index === last ? 'is-final' : '' }, host));
    });
    return h('section', { class: 'cek-sec' },
      h('h3', {}, 'Ke mana tautan ini sebenarnya menuju'),
      h('div', { class: 'cek-route' }, nodes),
      h('p', { class: 'small', style: 'margin:0' }, `Alamat yang ditempel ${hosts[0]}, tetapi tujuan akhirnya ${r.final_hostname || hosts[last]}.`));
  }

  function renderResult(target, r) {
    const sm = linkSummary(r);
    const signals = Array.isArray(r.signals) ? r.signals : [];
    const checks = Object.values(r.checks || {});
    const incomplete = r.coverage && r.coverage.status !== 'lengkap';

    target.replaceChildren(
      h('div', { class: 'cek-result' },
        h('div', { class: `cek-verdict ${LEVEL_CLASS[r.level] || 'is-none'}` },
          h('div', { class: 'cek-head' },
            h('span', { class: `tag ${TAG_BY_LEVEL[r.level] || 't-soft'}` }, `Risiko: ${r.level}`),
            h('span', { class: 'cek-score' }, `Skor ${r.score} dari 10`)),
          h('h3', { class: 'cek-headline' }, sm.headline),
          r.hostname ? h('p', { class: 'small cek-file', style: 'margin:0' }, r.hostname) : null,
          h('p', { class: 'mut', style: 'margin:0;font-size:14px' }, r.message)),
        routeView(r, sm.hosts),
        section('Kenapa hasilnya begini', '!', sm.reasons, 'is-reasons'),
        section('Sudah dicek dan tidak ditemukan', '✓', sm.passed, 'is-passed'),
        section('Belum bisa dicek', '?', sm.unchecked, 'is-unchecked'),
        incomplete ? h('p', { class: 'cek-notice', role: 'status' }, r.coverage_note || 'Hasil di atas hanya berdasarkan pemeriksaan yang berhasil, jadi jangan dianggap aman.') : null,
        h('section', { class: 'cek-sec' }, h('h3', {}, 'Yang sebaiknya kamu lakukan'), h('ol', { class: 'cek-steps' }, sm.steps.map((x) => h('li', {}, x)))),
        h('details', { class: 'cek-more' },
          h('summary', {}, `Rincian skor dan sumber (${signals.length} tanda)`),
          signals.length
            ? h('ul', { class: 'cek-signals' }, signals.map((x) =>
                h('li', {},
                  h('div', {}, h('b', {}, x.label), h('span', { class: 'small' }, x.detail)),
                  h('span', { class: 'cek-points' }, x.points > 0 ? `+${x.points}` : 'info'))))
            : h('p', { class: 'small', style: 'margin:0' }, 'Tidak ada tanda yang cocok dengan pola yang berhasil diperiksa.'),
          h('ul', { class: 'cek-sources' }, checks.map((c) =>
            h('li', {}, h('b', {}, `${c.label}: `), STATE_LABEL[c.state] ? `[${STATE_LABEL[c.state]}] ` : '', c.note || '')))),
        h('p', { class: 'small', style: 'margin:0' }, 'Ini indikator risiko, bukan jaminan aman atau tidak.'),
        r.explanation_available && r.explain_token ? explainBox(r.explain_token) : null));
  }

  // ---------- Penjelasan AI (opsional, terpisah dari hasil utama) ----------
  function explainBox(token) {
    const out = h('div', { 'aria-live': 'polite' });
    const button = h('button', { type: 'button', class: 'btn ghost' }, 'Jelaskan dengan bahasa sederhana (AI)');
    button.addEventListener('click', async () => {
      button.disabled = true;
      const label = button.textContent;
      button.textContent = 'AI sedang menjelaskan…';
      try {
        const res = await postJson('/jeda/api/cek-tautan/penjelasan', { token });
        renderExplanation(out, res.explanation);
        button.remove();
      } catch (error) {
        out.replaceChildren(h('p', { class: 'cek-notice', role: 'status' }, error.message));
        button.disabled = false;
        button.textContent = label;
      }
    });
    return h('div', { class: 'cek-ai' },
      button,
      h('p', { class: 'small', style: 'margin:0' },
        'Penjelasan dibuat AI (Google Gemini) hanya dari ringkasan tanda di atas. Alamat tautan tidak dikirim, dan AI tidak mengubah skor. Penjelasan AI bisa keliru, jadi hasil pemeriksaan di atas tetap acuan utama.'),
      out);
  }

  function renderExplanation(target, e) {
    const items = Array.isArray(e.penjelasan_indikator) ? e.penjelasan_indikator : [];
    target.replaceChildren(
      h('div', { class: 'cek-result' },
        h('h3', {}, 'Ringkasan'),
        h('p', { style: 'margin:0' }, e.ringkasan),
        e.peringatan_kelengkapan ? h('p', { class: 'cek-notice', role: 'status' }, e.peringatan_kelengkapan) : null,
        items.length ? h('h3', {}, 'Arti tiap tanda') : null,
        items.length ? h('ul', { class: 'cek-signals' }, items.map((i) =>
          h('li', {}, h('div', {}, h('b', {}, i.indikator), h('span', { class: 'small' }, i.penjelasan))))) : null,
        h('h3', {}, 'Mengapa tingkat risikonya begini'),
        h('p', { style: 'margin:0' }, e.alasan_tingkat_risiko),
        h('h3', {}, 'Yang sebaiknya kamu lakukan'),
        h('ul', { class: 'cek-steps' }, (e.rekomendasi || []).map((line) => h('li', {}, line)))));
  }

  function showMessage(target, text) {
    target.replaceChildren(h('p', { class: 'mut', style: 'margin:0' }, text));
  }

  // ---------- Scroll otomatis (penting di HP, tempat kartu hasil ada di bawah layar) ----------
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const behavior = () => (reduceMotion.matches ? 'auto' : 'smooth');

  function scrollToEl(el) {
    if (el) el.scrollIntoView({ behavior: behavior(), block: 'start' });
  }

  /** Geser ke kartu hasil hanya kalau bagian atasnya belum terlihat nyaman di layar. */
  function revealCard(el) {
    const card = el?.closest('.card') || el;
    if (!card) return;
    const top = card.getBoundingClientRect().top;
    if (top < 0 || top > window.innerHeight * 0.5) requestAnimationFrame(() => scrollToEl(card));
  }

  const linkInput = $('#linkInput');
  const linkButton = $('#linkButton');
  const linkResult = $('#linkResult');
  const vtSubmit = $('#vtSubmit');

  async function runLinkCheck() {
    const url = linkInput.value.trim();
    if (!url) { linkInput.focus(); return; }

    const label = linkButton.textContent;
    linkButton.disabled = true;
    linkButton.textContent = 'Memeriksa…';
    showMessage(linkResult, 'Memeriksa tautan. Ini bisa memakan beberapa detik karena tautan diikuti sampai tujuan akhirnya…');
    linkInput.blur(); // tutup keyboard HP supaya hasil terlihat
    revealCard(linkResult);
    try {
      const result = await postJson('/jeda/api/cek-tautan', { url, submit_to_virustotal: Boolean(vtSubmit?.checked) });
      renderResult(linkResult, result);
      revealCard(linkResult);
    } catch (error) {
      showMessage(linkResult, error.message);
      revealCard(linkResult);
    } finally {
      linkButton.disabled = false;
      linkButton.textContent = label;
    }
  }

  // ---------- Cek file ----------
  // Isi file dibaca di browser oleh public/js/file-inspect.js. Ke server hanya dikirim ringkasan + SHA-256.
  const TYPE_LABEL = {
    pe: 'Program Windows', elf: 'Program Linux', macho: 'Program macOS', java: 'Program Java', lnk: 'Pintasan Windows',
    script: 'Skrip', apk: 'Aplikasi Android (APK)', jar: 'Program Java (JAR)', pdf: 'PDF', ole: 'Dokumen Office lama',
    ooxml: 'Dokumen Office', zip: 'Arsip ZIP', rar: 'Arsip RAR', '7z': 'Arsip 7z', gzip: 'Arsip gzip', rtf: 'Dokumen RTF',
    png: 'Gambar PNG', jpeg: 'Gambar JPEG', gif: 'Gambar GIF', webp: 'Gambar WebP', bmp: 'Gambar BMP',
    html: 'Halaman web', svg: 'Gambar SVG', text: 'Teks biasa', unknown: 'Tidak dikenali',
  };

  function formatSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
  }

  const KIND_LABEL = {
    sms: 'SMS', accessibility: 'Aksesibilitas', notification: 'Notifikasi', overlay: 'Menimpa layar', device_admin: 'Admin perangkat',
    install: 'Pasang aplikasi', sensitive: 'Data pribadi', weak: 'Latar belakang', normal: 'Biasa',
  };
  const KIND_DANGER = new Set(['sms', 'accessibility', 'notification', 'overlay', 'device_admin', 'install']);

  function section(title, icon, items, cls) {
    if (!items || !items.length) return null;
    return h('section', { class: `cek-sec ${cls || ''}` },
      h('h3', {}, title),
      h('ul', { class: 'cek-list' }, items.map((item) =>
        h('li', {}, h('span', { class: 'cek-ico', 'aria-hidden': 'true' }, icon), h('span', {}, item)))));
  }

  function renderApk(apk) {
    if (!apk) return null;
    if (!apk.readable) {
      return h('section', { class: 'cek-sec' }, h('h3', {}, 'Izin yang diminta aplikasi'),
        h('p', { class: 'small', style: 'margin:0' }, 'Daftar izin tidak bisa dibaca dari APK ini, jadi bagian ini tidak ikut dinilai.'));
    }
    const shown = apk.permissions.filter((p) => p.kind !== 'normal');
    const normal = apk.permissions.length - shown.length;
    const extra = normal + (apk.other_count || 0);
    return h('section', { class: 'cek-sec' },
      h('h3', {}, 'Izin yang diminta aplikasi'),
      apk.package ? h('p', { class: 'small', style: 'margin:0' }, 'Nama paket: ', h('code', {}, apk.package)) : null,
      shown.length
        ? h('ul', { class: 'cek-perms' }, shown.map((p) =>
            h('li', {},
              h('span', { class: `cek-kind ${KIND_DANGER.has(p.kind) ? 'is-danger' : (p.kind === 'sensitive' ? 'is-warn' : '')}` }, KIND_LABEL[p.kind] || p.kind),
              h('span', {}, p.label))))
        : h('p', { class: 'small', style: 'margin:0' }, 'Tidak ada izin sensitif yang terbaca.'),
      extra > 0 ? h('p', { class: 'small', style: 'margin:0' }, `+ ${extra} izin lain yang umum atau tidak dikenali.`) : null);
  }

  function renderFileResult(target, r, name) {
    const sm = r.summary || { headline: r.message, reasons: [], passed: [], unchecked: [], steps: [] };
    const signals = Array.isArray(r.signals) ? r.signals : [];
    const checks = Object.values(r.checks || {});
    const lvl = ({ Tinggi: 'is-high', Sedang: 'is-mid', Rendah: 'is-low' })[r.level] || 'is-none';
    const unchecked = (sm.unchecked || []).map((u) => (u.note ? `${u.label}: ${u.note}` : u.label));

    target.replaceChildren(
      h('div', { class: 'cek-result' },
        h('div', { class: `cek-verdict ${lvl}` },
          h('div', { class: 'cek-head' },
            h('span', { class: `tag ${TAG_BY_LEVEL[r.level] || 't-soft'}` }, `Risiko: ${r.level}`),
            h('span', { class: 'cek-score' }, `Skor ${r.score} dari 10`)),
          h('h3', { class: 'cek-headline' }, sm.headline),
          h('p', { class: 'small cek-file', style: 'margin:0' }, name)),
        section('Kenapa hasilnya begini', '!', sm.reasons, 'is-reasons'),
        renderApk(r.apk),
        section('Sudah dicek dan tidak ditemukan', '✓', sm.passed, 'is-passed'),
        section('Belum bisa dicek', '?', unchecked, 'is-unchecked'),
        r.coverage && r.coverage.status !== 'lengkap'
          ? h('p', { class: 'cek-notice', role: 'status' }, 'Hasil di atas hanya berdasarkan pemeriksaan yang berhasil, jadi jangan dianggap aman.')
          : null,
        sm.steps && sm.steps.length
          ? h('section', { class: 'cek-sec' }, h('h3', {}, 'Yang sebaiknya kamu lakukan'), h('ol', { class: 'cek-steps' }, sm.steps.map((x) => h('li', {}, x))))
          : null,
        h('details', { class: 'cek-more' },
          h('summary', {}, `Rincian skor dan sumber (${signals.length} tanda)`),
          h('dl', { class: 'cek-facts' },
            h('dt', {}, 'Ukuran'), h('dd', {}, r.size ? formatSize(r.size) : '(tidak dibaca)'),
            h('dt', {}, 'Ekstensi'), h('dd', {}, r.extension ? `.${r.extension}` : '(tidak ada)'),
            h('dt', {}, 'Isi sebenarnya'), h('dd', {}, r.detected_type ? (TYPE_LABEL[r.detected_type] || r.detected_type) : 'Tidak dibaca')),
          signals.length
            ? h('ul', { class: 'cek-signals' }, signals.map((x) =>
                h('li', {},
                  h('div', {}, h('b', {}, x.label), h('span', { class: 'small' }, x.detail)),
                  x.points > 0 ? h('span', { class: 'cek-points' }, `+${x.points}`) : h('span', { class: 'cek-points' }, 'info'))))
            : h('p', { class: 'small', style: 'margin:0' }, 'Tidak ada tanda yang cocok dengan pola yang diperiksa.'),
          h('ul', { class: 'cek-sources' }, checks.map((c) =>
            h('li', {}, h('b', {}, `${c.label}: `), STATE_LABEL[c.state] ? `[${STATE_LABEL[c.state]}] ` : '', c.note || '')))),
        h('p', { class: 'small', style: 'margin:0' }, 'Ini indikator risiko, bukan jaminan aman atau tidak. Antivirus tetap perlu dipakai.'),
        h('a', { class: 'btn ghost block', href: '/jam-emas' }, 'Sudah terlanjur membuka file ini? Lihat langkahnya')));
  }

  const fileInput = $('#fileInput');
  const fileDrop = $('#fileDrop');
  const fileResult = $('#fileResult');
  const fileNameInput = $('#fileNameInput');
  const fileNameButton = $('#fileNameButton');
  const cekConfig = (() => { try { return JSON.parse($('#cekConfig')?.textContent || '{}'); } catch { return {}; } })();

  async function sendFile(summary) {
    const result = await postJson('/jeda/api/cek-file', summary);
    renderFileResult(fileResult, result, summary.filename);
    revealCard(fileResult);
  }

  async function runFileCheck(file) {
    if (!file) return;
    showMessage(fileResult, `Membaca "${file.name}" di perangkatmu…`);
    try {
      const inspector = window.JedaFileInspect;
      const summary = inspector
        ? await inspector.inspect(file, cekConfig)
        : { filename: file.name, size: file.size, sha256: null, detected_type: null, content_scanned: false, flags: {} };
      showMessage(fileResult, 'Mencocokkan sidik jari file…');
      await sendFile(summary);
    } catch (error) {
      showMessage(fileResult, error.message);
    }
  }

  async function runNameCheck() {
    const filename = fileNameInput.value.trim();
    if (!filename) { fileNameInput.focus(); return; }
    showMessage(fileResult, 'Memeriksa nama file…');
    fileNameInput.blur();
    try {
      await sendFile({ filename, size: 0, sha256: null, detected_type: null, content_scanned: false, flags: {} });
    } catch (error) {
      showMessage(fileResult, error.message);
    }
  }

  fileInput?.addEventListener('change', async () => {
    const file = fileInput.files?.[0];
    await runFileCheck(file);
    fileInput.value = ''; // supaya file yang sama bisa diperiksa ulang
  });
  fileDrop?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); fileInput.click(); }
  });
  ['dragenter', 'dragover'].forEach((type) => fileDrop?.addEventListener(type, (event) => { event.preventDefault(); fileDrop.classList.add('is-over'); }));
  ['dragleave', 'drop'].forEach((type) => fileDrop?.addEventListener(type, (event) => { event.preventDefault(); fileDrop.classList.remove('is-over'); }));
  fileDrop?.addEventListener('drop', (event) => runFileCheck(event.dataTransfer?.files?.[0]));
  fileNameButton?.addEventListener('click', runNameCheck);
  fileNameInput?.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); runNameCheck(); } });


  // ---------- Tombol Penguji Tautan / Penguji File ----------
  // Berupa tautan #anchor biasa (tetap berfungsi tanpa JS); di sini ditambah scroll halus, fokus, dan penanda aktif.
  const tabLinks = [...document.querySelectorAll('[data-cek-target]')];
  const finePointer = window.matchMedia('(pointer: fine)');

  function setActiveTab(id) {
    tabLinks.forEach((a) => {
      const on = a.dataset.cekTarget === id;
      a.classList.toggle('on', on);
      if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
    });
  }

  tabLinks.forEach((a) => a.addEventListener('click', (event) => {
    const id = a.dataset.cekTarget;
    const target = document.getElementById(id);
    if (!target) return; // biarkan browser menangani anchor
    event.preventDefault();
    setActiveTab(id);
    scrollToEl(target);
    try { history.replaceState(null, '', `#${id}`); } catch { /* abaikan */ }
    // Fokus ke kolom input hanya di perangkat dengan mouse; di HP fokus akan memunculkan keyboard yang menutupi layar.
    if (finePointer.matches) (id === 'cek-tautan' ? linkInput : fileDrop)?.focus({ preventScroll: true });
  }));

  // Saat layout ditumpuk (HP/sempit), penanda aktif mengikuti posisi scroll.
  const stacked = window.matchMedia('(max-width: 820px)');
  let ticking = false;
  window.addEventListener('scroll', () => {
    if (!stacked.matches || ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const fileTop = document.getElementById('cek-file')?.getBoundingClientRect().top ?? Infinity;
      setActiveTab(fileTop < window.innerHeight * 0.45 ? 'cek-file' : 'cek-tautan');
      ticking = false;
    });
  }, { passive: true });

  if (location.hash === '#cek-file') setActiveTab('cek-file');

  linkButton?.addEventListener('click', runLinkCheck);
  linkInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') { event.preventDefault(); runLinkCheck(); }
  });
})();
