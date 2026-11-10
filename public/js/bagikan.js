/*
 * Halaman Bagikan (/bagikan): mode ortu.
 *
 * Alur:
 *  1. Pesan datang dari menu Bagikan Android (lewat service worker), dari tombol "Tempel", atau diketik.
 *  2. Pesan diperiksa DI HP INI memakai mesin Bedah Pesan (window.JedaBedah dari bedah.js).
 *     Isi pesan tidak dikirim ke server.
 *  3. Hasil ditampilkan dengan bahasa sederhana. Hasil TIDAK pernah dibilang "aman": level terendah
 *     berbunyi "belum ada tanda yang dikenali", karena pemeriksaan berbasis aturan bisa terlewat.
 *  4. Kalau ada link, pemeriksaan lebih dalam ke server (Cek Tautan) hanya jalan setelah ditekan,
 *     dan hanya alamat link yang dikirim.
 *  5. Tombol "Tanya keluarga" membuka WhatsApp ke nomor keluarga yang disimpan di HP ini.
 *
 * Kontak keluarga (nama dan nomor) datang dari bagian # pada link pemasangan (tidak dikirim ke server)
 * dan DISIMPAN HANYA SETELAH pemilik HP menyetujui, supaya link palsu tidak bisa diam-diam mengganti
 * kontak dengan nomor penipu.
 */
(() => {
  'use strict';

  const root = document.getElementById('bagikan');
  if (!root) return;

  const SW_URL = root.dataset.swUrl || '';
  const CEK_URL = root.dataset.cekUrl || '/jeda/api/cek-tautan';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const SHARE_CACHE = 'jeda-share-v1';   // harus sama dengan public/sw.js
  const SHARE_PATH = '__jeda_terima__';  // harus sama dengan public/sw.js
  const K_G = 'jeda-guardian';           // { n, w, bank } (dipakai juga oleh halaman /keluarga)

  const $ = (id) => document.getElementById(id);
  const el = {
    konfirmasi: $('bgKonfirmasi'), konfirmasiJudul: $('bgKonfirmasiJudul'), konfirmasiNama: $('bgKonfirmasiNama'),
    konfirmasiWa: $('bgKonfirmasiWa'), konfirmasiYa: $('bgKonfirmasiYa'), konfirmasiTidak: $('bgKonfirmasiTidak'),
    isian: $('bgIsian'), input: $('bgInput'), periksa: $('bgPeriksa'), tempel: $('bgTempel'),
    hasil: $('bgHasil'), banner: $('bgBanner'), ikon: $('bgIkon'), judul: $('bgJudul'), ringkas: $('bgRingkas'),
    alasan: $('bgAlasan'), langkah: $('bgLangkah'),
    tautanWrap: $('bgTautanWrap'), tautanDaftar: $('bgTautanDaftar'), tautanHasil: $('bgTautanHasil'),
    kirim: $('bgKirim'), ulang: $('bgUlang'),
    pengaturan: $('bgPengaturan'), nama: $('bgNama'), wa: $('bgWa'), simpan: $('bgSimpanKontak'),
    hapus: $('bgHapusKontak'), status: $('bgKontakStatus'),
    pasangCard: $('bgPasangCard'), pasang: $('bgPasang'), pasangManual: $('bgPasangManual'), pasangIos: $('bgPasangIos'),
  };

  /* ================= Helper ================= */

  // Semua teks dari pesan, server, atau pengguna dimasukkan lewat textContent, tidak pernah innerHTML.
  function h(tag, props, ...kids) {
    const e = document.createElement(tag);
    for (const [k, v] of Object.entries(props || {})) {
      if (v === null || v === undefined || v === false) continue;
      if (k === 'class') e.className = v;
      else e.setAttribute(k, v);
    }
    for (const kid of kids.flat()) {
      if (kid === null || kid === undefined || kid === false) continue;
      e.append(kid.nodeType ? kid : document.createTextNode(String(kid)));
    }
    return e;
  }

  function toast(message) {
    const t = $('jedaToast');
    if (!t) return;
    t.textContent = message;
    t.classList.add('is-shown');
    clearTimeout(window.__jedaToastTimer);
    window.__jedaToastTimer = setTimeout(() => t.classList.remove('is-shown'), 3200);
  }

  /* ================= Kontak keluarga ================= */

  function bacaG() {
    try {
      const g = JSON.parse(localStorage.getItem(K_G)) || {};
      return { n: typeof g.n === 'string' ? g.n : '', w: typeof g.w === 'string' ? g.w : '', bank: typeof g.bank === 'string' ? g.bank : '' };
    } catch (_) { return { n: '', w: '', bank: '' }; }
  }
  function simpanG(g) {
    try { localStorage.setItem(K_G, JSON.stringify(g)); return true; } catch (_) { return false; }
  }

  function bersihNama(s) {
    return String(s || '').replace(/[^\p{L}\p{N} .,'’-]/gu, '').replace(/\s+/g, ' ').trim().slice(0, 24);
  }

  function normalisasiWa(input) {
    let d = String(input || '').trim();
    const plus = d.startsWith('+');
    d = d.replace(/\D/g, '');
    if (!d) return '';
    if (!plus) {
      if (d.startsWith('00')) d = d.slice(2);
      else if (d.startsWith('0')) d = '62' + d.slice(1);
      else if (d.startsWith('8')) d = '62' + d;
    }
    return /^[1-9]\d{8,14}$/.test(d) ? d : '';
  }

  const tampilWa = (w) => (!w ? '' : w.startsWith('62') ? '0' + w.slice(2) : '+' + w);

  /* ================= Hasil: tampilan per tingkat ================= */

  const URUT = ['nol', 'periksa', 'sedang', 'tinggi'];

  const siapa = () => bacaG().n || 'keluarga';

  const TAMPIL = {
    tinggi: {
      kelas: 'is-high', ikon: '!', judul: 'JANGAN DIBALAS DAN JANGAN DIKLIK',
      ringkas: () => `Kemungkinan besar ini penipuan. Tanya ${siapa()} kalau masih ragu.`,
      singkat: 'kemungkinan besar penipuan',
    },
    sedang: {
      kelas: 'is-mid', ikon: '!', judul: 'HATI-HATI. JANGAN TRANSFER DULU',
      ringkas: () => `Ada beberapa tanda penipuan di pesan ini. Tanya ${siapa()} dulu sebelum bertindak.`,
      singkat: 'ada beberapa tanda penipuan',
    },
    periksa: {
      kelas: 'is-low', ikon: '!', judul: 'ADA TANDA YANG PERLU DICEK',
      ringkas: () => `Tanya ${siapa()} dulu sebelum membalas.`,
      singkat: 'ada tanda yang perlu dicek',
    },
    nol: {
      kelas: 'is-none', ikon: '?', judul: 'BELUM ADA TANDA YANG DIKENALI',
      ringkas: () => `Ini BUKAN jaminan aman. Kalau menyangkut uang, kode, atau data pribadi, tanya ${siapa()} dulu.`,
      singkat: 'belum ada tanda yang dikenali (bukan jaminan aman)',
    },
  };

  const kunciLevel = (levelBedah) => (levelBedah === 'Tinggi' ? 'tinggi'
    : levelBedah === 'Sedang' ? 'sedang'
    : levelBedah === 'Perlu diperiksa' ? 'periksa' : 'nol');

  const state = { teks: '', level: 'nol', catatanTautan: '' };

  function susunPesan() {
    const g = bacaG();
    const sapa = g.n ? `Halo ${g.n}` : 'Halo';
    const potong = state.teks.length > 600 ? state.teks.slice(0, 600) + '…' : state.teks;
    const tautan = state.catatanTautan ? ` Pemeriksaan tautan: ${state.catatanTautan}.` : '';
    return `${sapa}, aku dapat pesan ini dan ragu. Hasil periksa Jeda: ${TAMPIL[state.level].singkat}.${tautan}\n\nPesannya:\n"${potong}"`;
  }

  function perbaruiTombolKirim() {
    const g = bacaG();
    el.kirim.textContent = g.n ? `Tanya ${g.n} lewat WA` : 'Tanya keluarga lewat WA';
    el.kirim.href = (g.w ? `https://wa.me/${g.w}` : 'https://wa.me/') + '?text=' + encodeURIComponent(susunPesan());
  }

  function terapkanLevel(kunci) {
    const t = TAMPIL[kunci];
    state.level = kunci;
    el.banner.className = 'bg-banner ' + t.kelas;
    el.ikon.textContent = t.ikon;
    el.judul.textContent = t.judul;
    el.ringkas.textContent = t.ringkas();
    perbaruiTombolKirim();
  }

  function isiAlasan(r) {
    const bobot = Object.fromEntries((((window.JedaBedah && window.JedaBedah.RULES) || [])).map((x) => [x.id, x.weight]));
    const biasa = r.findings.filter((f) => f.id !== 'kombinasi').sort((a, b) => (bobot[b.id] || 0) - (bobot[a.id] || 0));
    const kombinasi = r.findings.find((f) => f.id === 'kombinasi');
    const daftar = kombinasi ? [kombinasi, ...biasa.slice(0, 2)] : biasa.slice(0, 3);
    if (!daftar.length) {
      el.alasan.replaceChildren(h('li', {}, 'Tidak ada pola penipuan umum yang cocok. Itu belum tentu berarti aman.'));
      return;
    }
    el.alasan.replaceChildren(...daftar.map((f) => h('li', {},
      h('b', {}, f.label),
      f.evidence && f.evidence.length ? h('span', { class: 'small bg-bukti' }, 'Terdeteksi: ' + f.evidence.slice(0, 2).join(', ')) : null,
      h('span', { class: 'small' }, f.why))));
  }

  function isiLangkah(r, kunci) {
    // Untuk "belum ada tanda", langkah bawaan mesin ("Berhenti dulu...") terlalu keras, jadi diganti.
    const langkah = kunci === 'nol'
      ? [
        'Kalau pesan ini meminta uang, kode OTP, atau data pribadi, jangan dilakukan dulu.',
        'Pastikan lewat telepon ke nomor yang sudah dikenal, bukan nomor yang ada di pesan.',
        `Kalau masih ragu, tanya ${siapa()}.`,
      ]
      : r.steps.slice(0, 3);
    el.langkah.replaceChildren(...langkah.map((s) => h('li', {}, s)));
  }

  /* ================= Tautan ================= */

  const RX_URL = /(?:https?:\/\/|www\.)[^\s<>"')\]]+|\b(?:bit\.ly|s\.id|tinyurl\.com|cutt\.ly|shorturl\.at|is\.gd|t\.co|wa\.me)\/[^\s<>"')\]]+/gi;

  function ambilTautan(teks) {
    const hasil = [];
    for (const m of String(teks).matchAll(RX_URL)) {
      const u = m[0].replace(/[.,;:!?]+$/, '');
      if (u && !hasil.includes(u)) hasil.push(u);
      if (hasil.length >= 3) break;
    }
    return hasil;
  }

  function namaHost(u) {
    try { return new URL(/^[a-z]+:\/\//i.test(u) ? u : 'https://' + u).hostname; } catch (_) { return u.slice(0, 60); }
  }

  async function postJson(url, payload) {
    let res;
    try {
      res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload),
      });
    } catch (_) {
      throw new Error('Tidak bisa terhubung ke server. Periksa koneksi internet.');
    }
    const body = await res.json().catch(() => ({}));
    if (res.status === 429) throw new Error('Terlalu sering memeriksa. Tunggu sebentar lalu coba lagi.');
    if (res.status === 419) throw new Error('Sesi habis. Muat ulang halaman lalu coba lagi.');
    if (!res.ok) throw new Error((body.errors && body.errors.url && body.errors.url[0]) || body.message || 'Pemeriksaan belum berhasil. Coba lagi.');
    return body;
  }

  function tampilHasilTautan(url, r) {
    const kunci = (r.confirmed || r.level === 'Tinggi') ? 'tinggi'
      : r.level === 'Sedang' ? 'sedang'
      : r.level === 'Rendah' ? 'periksa' : 'nol';
    const belumLengkap = Boolean(r.coverage && r.coverage.status !== 'lengkap');
    const judul = r.confirmed ? 'Banyak mesin keamanan menandai tautan ini berbahaya. Jangan dibuka.'
      : kunci === 'tinggi' ? 'Jangan buka tautan ini.'
      : kunci === 'sedang' ? 'Hati-hati. Jangan isi data apa pun di tautan ini.'
      : kunci === 'periksa' ? 'Ada tanda kecil. Buka hanya kalau kenal pengirimnya.'
      : belumLengkap ? 'Belum ada tanda bahaya, tapi pemeriksaan belum lengkap. Bukan jaminan aman.'
      : 'Belum ada tanda bahaya di tautan ini. Bukan jaminan aman.';
    const alasan = (Array.isArray(r.signals) ? r.signals : [])
      .filter((x) => x && x.points > 0).sort((a, b) => b.points - a.points).slice(0, 3).map((x) => x.label);

    const kelas = { tinggi: 'is-high', sedang: 'is-mid', periksa: 'is-low', nol: '' }[kunci];
    el.tautanHasil.replaceChildren(h('div', { class: 'bg-kotak ' + kelas },
      h('p', { class: 'bg-host' }, namaHost(url)),
      h('p', { class: 'bg-kotak__judul' }, judul),
      alasan.length ? h('ul', {}, alasan.map((a) => h('li', {}, a))) : null));

    // Kalau tautannya lebih berbahaya daripada isi pesannya, banner utama ikut naik.
    state.catatanTautan = judul.replace(/\.$/, '');
    if (URUT.indexOf(kunci) > URUT.indexOf(state.level)) terapkanLevel(kunci);
    else perbaruiTombolKirim();
  }

  async function periksaTautan(url, tombol) {
    const label = tombol.textContent;
    tombol.disabled = true;
    tombol.textContent = 'Memeriksa…';
    el.tautanHasil.replaceChildren(h('div', { class: 'bg-kotak' }, h('p', {}, 'Sedang memeriksa tautan. Bisa makan waktu beberapa detik.')));
    try {
      tampilHasilTautan(url, await postJson(CEK_URL, { url }));
    } catch (e) {
      el.tautanHasil.replaceChildren(h('div', { class: 'bg-kotak' },
        h('p', { class: 'bg-kotak__judul' }, 'Tautan belum berhasil diperiksa.'), h('p', {}, e.message)));
    } finally {
      tombol.disabled = false;
      tombol.textContent = label;
    }
  }

  function isiTautan(teks) {
    const daftar = ambilTautan(teks);
    el.tautanWrap.hidden = daftar.length === 0;
    el.tautanHasil.replaceChildren();
    el.tautanDaftar.replaceChildren(...daftar.map((u) => {
      const b = h('button', { type: 'button', class: 'btn ghost' }, daftar.length > 1 ? `Periksa ${namaHost(u)}` : 'Periksa link ini lebih dalam');
      b.addEventListener('click', () => periksaTautan(u, b));
      return b;
    }));
  }

  /* ================= Periksa ================= */

  function periksa(teks) {
    const t = String(teks || '').trim();
    if (!t) { toast('Tempel pesan atau link dulu.'); el.input.focus(); return; }
    const Bedah = window.JedaBedah;
    if (!Bedah || typeof Bedah.analyze !== 'function') {
      toast('Pemeriksa belum siap. Muat ulang halaman.');
      return;
    }
    const r = Bedah.analyze(t);
    const kunci = kunciLevel(r.level);
    state.teks = t;
    state.catatanTautan = '';

    isiAlasan(r);
    isiLangkah(r, kunci);
    isiTautan(t);
    terapkanLevel(kunci);

    el.isian.hidden = true;
    el.hasil.hidden = false;
    el.banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
    el.banner.focus({ preventScroll: true });
  }

  el.periksa.addEventListener('click', () => periksa(el.input.value));
  el.input.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') periksa(el.input.value); });

  el.tempel.addEventListener('click', async () => {
    try {
      const t = await navigator.clipboard.readText();
      if (!t || !t.trim()) { toast('Belum ada pesan yang disalin.'); return; }
      el.input.value = t.slice(0, 5000);
      periksa(el.input.value);
    } catch (_) {
      toast('Tempel manual: tekan lama di kotak, lalu pilih Tempel.');
      el.input.focus();
    }
  });

  el.ulang.addEventListener('click', () => {
    el.hasil.hidden = true;
    el.isian.hidden = false;
    el.input.value = '';
    state.teks = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    el.input.focus();
  });

  /* ================= Kontak keluarga: pengaturan manual ================= */

  function isiPengaturan() {
    const g = bacaG();
    el.nama.value = g.n;
    el.wa.value = tampilWa(g.w);
  }

  el.simpan.addEventListener('click', () => {
    const n = bersihNama(el.nama.value);
    const w = normalisasiWa(el.wa.value);
    if (!w) { el.status.textContent = 'Nomor WhatsApp belum valid. Contoh: 08123456789'; el.wa.focus(); return; }
    if (!simpanG({ ...bacaG(), n, w })) { el.status.textContent = 'Penyimpanan di browser ini dimatikan, jadi kontak tidak bisa disimpan.'; return; }
    el.status.textContent = `Tersimpan. Hasil akan dikirim ke ${n || tampilWa(w)}.`;
    perbaruiTombolKirim();
  });

  el.hapus.addEventListener('click', () => {
    simpanG({ ...bacaG(), n: '', w: '' });
    el.nama.value = '';
    el.wa.value = '';
    el.status.textContent = 'Kontak dihapus dari HP ini.';
    perbaruiTombolKirim();
  });

  /* ================= Kontak dari link pemasangan (#w=...&n=...) ================= */

  function bacaHash() {
    if (!location.hash || location.hash.length < 2) return null;
    let p;
    try { p = new URLSearchParams(location.hash.slice(1)); } catch (_) { return null; }
    const w = normalisasiWa(p.get('w') || '');
    if (!w) return null;
    return { w, n: bersihNama(p.get('n') || '') };
  }

  function tanyaKonfirmasi(kontak) {
    el.konfirmasiNama.textContent = kontak.n || 'Keluarga';
    el.konfirmasiWa.textContent = tampilWa(kontak.w);
    el.konfirmasi.hidden = false;
    el.konfirmasiJudul.tabIndex = -1;
    el.konfirmasiJudul.focus();

    const tutup = () => { el.konfirmasi.hidden = true; };
    el.konfirmasiYa.onclick = () => {
      if (simpanG({ ...bacaG(), n: kontak.n, w: kontak.w })) toast(`Tersimpan. Hasil periksa bisa dikirim ke ${kontak.n || tampilWa(kontak.w)}.`);
      else toast('Penyimpanan di browser ini dimatikan, kontak tidak bisa disimpan.');
      isiPengaturan();
      perbaruiTombolKirim();
      tutup();
    };
    el.konfirmasiTidak.onclick = () => { toast('Kontak tidak disimpan.'); tutup(); };
  }

  function mulaiDariLink() {
    const kontak = bacaHash();
    if (!kontak) return;
    // Hapus # dari alamat supaya nomor tidak tertinggal di riwayat browser.
    try { history.replaceState(null, '', location.pathname + location.search); } catch (_) { /* abaikan */ }
    const sekarang = bacaG();
    if (sekarang.w === kontak.w && sekarang.n === kontak.n) { toast('Kontak keluarga sudah tersimpan.'); return; }
    tanyaKonfirmasi(kontak);
  }

  /* ================= Kiriman dari menu Bagikan (lewat service worker) ================= */

  const scopeBase = SW_URL ? new URL('./', new URL(SW_URL, location.href)).href : (location.origin + '/');

  async function bacaKiriman() {
    const p = new URLSearchParams(location.search);
    if (p.get('dibagikan') !== '1') return null;
    try { history.replaceState(null, '', location.pathname + location.hash); } catch (_) { /* abaikan */ }
    if (!('caches' in window)) return null;
    try {
      const cache = await caches.open(SHARE_CACHE);
      const kunci = new URL(SHARE_PATH, scopeBase).href;
      const res = await cache.match(kunci);
      if (!res) return null;
      const data = await res.json();
      await cache.delete(kunci); // dihapus segera: pesan tidak disimpan lebih lama dari yang perlu
      const bagian = [data.title, data.text, data.url].map((s) => String(s || '').trim()).filter(Boolean);
      const gabung = [];
      for (const b of bagian) if (!gabung.some((x) => x.includes(b))) gabung.push(b); // URL sering muncul di title dan text sekaligus
      return gabung.join('\n').slice(0, 5000) || null;
    } catch (_) {
      return null;
    }
  }

  /* ================= Pasang di layar utama ================= */

  const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  let tawaranPasang = null;

  function siapkanPasang() {
    if (standalone) return;
    el.pasangCard.hidden = false;
    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) el.pasangIos.hidden = false;

    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      tawaranPasang = e;
      el.pasang.hidden = false;
      el.pasangManual.hidden = true;
    });
    el.pasang.addEventListener('click', async () => {
      if (!tawaranPasang) return;
      tawaranPasang.prompt();
      try { await tawaranPasang.userChoice; } catch (_) { /* abaikan */ }
      tawaranPasang = null;
      el.pasang.hidden = true;
    });
    window.addEventListener('appinstalled', () => { el.pasangCard.hidden = true; toast('Jeda sudah terpasang di layar utama.'); });
  }

  if ('serviceWorker' in navigator && SW_URL) {
    window.addEventListener('load', () => { navigator.serviceWorker.register(SW_URL).catch(() => { /* tanpa service worker, halaman tetap bisa dipakai manual */ }); });
  }

  /* ================= Mulai ================= */

  isiPengaturan();
  mulaiDariLink();
  siapkanPasang();
  bacaKiriman().then((teks) => {
    if (teks) { el.input.value = teks; periksa(teks); }
  });
})();