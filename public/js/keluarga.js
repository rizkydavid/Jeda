/*
 * Halaman Keluarga (/keluarga).
 *
 * - Checklist per orang: tersimpan di browser (localStorage) dan dicatat ke server lewat
 *   /jeda/api/checklist (hanya id persona dan id langkah, tanpa isi apa pun).
 * - Pesan peringatan per modus dengan sapaan yang bisa diganti.
 * - Kata sandi keluarga: dibuat dengan crypto.getRandomValues dan hanya ada di memori halaman.
 * - Link pemasangan Jeda di HP ortu dan kartu tempel: nama, nomor, dan nomor bank hanya
 *   disimpan di browser ini. Nama dan nomor di link ditaruh di bagian # (tidak dikirim ke server).
 *
 * CATATAN PENTING: layout (app.blade.php) punya handler klik global yang mencocokkan TEKS
 * tombol, mis. "Kirim lewat WhatsApp" atau "Salin pesan". Kalau label tombol di halaman ini
 * sama dengan itu, handler global ikut jalan dan bisa menyalin teks yang salah atau membuka WhatsApp
 * dua kali. Karena itu label tombol di sini sengaja berbeda ("Bagikan ke WhatsApp", "Salin teks").
 * Jangan diubah kembali ke label lama tanpa memeriksa handler tersebut.
 */
(() => {
  'use strict';

  const root = document.getElementById('keluarga');
  if (!root) return;

  const URL_CHECKLIST = root.dataset.checklistUrl || '/jeda/api/checklist';
  const URL_AKSI = root.dataset.aksiUrl || '/jeda/api/aksi-bantuan';
  const URL_BAGIKAN = root.dataset.bagikanUrl || (location.origin + '/bagikan');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  /* ================= Data ================= */

  const NAMA_CHIP = { ortu: 'Orang tua', adik: 'Adik', kakek: 'Kakek dan nenek', teman: 'Teman' };
  const SAPAAN_AWAL = { ortu: 'Ma', adik: 'Dek', kakek: 'Kek, Nek', teman: 'Bro' };
  const LABEL_ORANG = { ortu: 'orang tua', adik: 'adik', kakek: 'kakek dan nenek', teman: 'teman' };

  // {s} diganti dengan sapaan. Isi pesan memakai gaya bahasa sehari-hari.
  const MODUS = {
    ortu: [
      { k: 'nomor-baru', label: 'Ngaku anak, nomor baru',
        teks: '{s}, lagi ramai penipuan: ada yang ngaku jadi aku dari nomor baru, lalu minta transfer cepat-cepat. Kalau ada pesan begitu, telepon aku dulu di nomor yang lama ya, jangan langsung transfer.' },
      { k: 'apk', label: 'APK atau undangan',
        teks: '{s}, lagi ramai penipuan lewat file atau link di WhatsApp yang ngaku "undangan nikah", "resi paket", atau "foto kamu" dan minta kita memasang aplikasi (file .apk). Jangan dibuka ya. Kalau ada yang begitu, kirim ke aku dulu.' },
      { k: 'bank', label: 'Ngaku petugas bank',
        teks: '{s}, lagi ramai penipuan: ada yang ngaku petugas bank lewat telepon atau WA, minta kode OTP, PIN, atau menyuruh memindahkan uang ke "rekening aman". Bank tidak pernah meminta itu. Kalau ada yang begitu, tutup teleponnya dan hubungi aku.' },
    ],
    kakek: [
      { k: 'cucu', label: 'Ngaku cucu kena musibah',
        teks: '{s}, lagi ramai penipuan: ada yang telepon ngaku jadi cucu, bilang lagi kena musibah dan butuh uang cepat. Kalau ada yang begitu, jangan kirim uang dulu. Telepon aku langsung ya.' },
      { k: 'bank', label: 'Minta kode SMS',
        teks: '{s}, lagi ramai penipuan: ada yang telepon ngaku petugas bank atau polisi, lalu minta kode yang masuk lewat SMS. Jangan kasih kode apa pun ke siapa pun. Kalau ada yang telepon begitu, tutup saja dan telepon aku.' },
      { k: 'apk', label: 'Kiriman file atau link',
        teks: '{s}, kalau ada kiriman file atau link di WhatsApp yang minta dibuka atau dipasang, jangan dibuka ya, walaupun katanya undangan atau paket. Tunjukkan dulu ke aku.' },
    ],
    adik: [
      { k: 'hadiah', label: 'Hadiah dan akun murah',
        teks: '{s}, lagi ramai penipuan: ada yang ngaku kenalan atau menawarkan hadiah dan akun game murah lewat chat, lalu minta transfer atau kode OTP. Kalau ada yang begitu, tanya kakak dulu ya, jangan langsung transfer atau klik tautannya.' },
      { k: 'game', label: 'Top-up game murah',
        teks: '{s}, hati-hati kalau ada yang menawarkan skin atau top-up game murah lewat chat, apalagi menyuruh login lewat link yang dia kirim. Itu bisa dipakai buat mencuri akunmu. Top-up cuma lewat toko resmi di dalam game ya.' },
      { k: 'otp', label: 'Minta kode OTP',
        teks: '{s}, jangan pernah kasih kode OTP atau kode verifikasi ke siapa pun, termasuk teman main game atau yang ngaku admin. Kalau ada yang minta, bilang ke kakak.' },
    ],
    teman: [
      { k: 'nomor-baru', label: 'Ngaku teman, nomor baru',
        teks: '{s}, lagi ramai penipuan: ada yang ngaku jadi teman atau saudara dari nomor baru lalu minta transfer. Kalau ada yang begitu, telepon nomor lamanya dulu sebelum kirim uang.' },
      { k: 'kerja', label: 'Kerja atau investasi cepat',
        teks: '{s}, hati-hati sama tawaran kerja online atau investasi yang menjanjikan untung besar tapi minta bayar di depan. Kalau dapat tawaran begitu, cek dulu lewat situs resmi perusahaannya sebelum transfer.' },
      { k: 'apk', label: 'File .apk dan link',
        teks: '{s}, lagi ramai penipuan lewat file .apk atau link yang ngaku undangan, resi paket, atau tilang. Jangan dibuka dan jangan dipasang ya. Kalau ada yang kirim begitu, cek dulu ke orangnya langsung.' },
    ],
  };

  const LANGKAH_SANDI = {
    k: 'sandi', t: 'Tetapkan kata sandi keluarga',
    cara: [
      'Buat kata sandinya di kartu "Kata sandi keluarga" di halaman ini.',
      'Sepakati langsung saat bertemu atau telepon, jangan lewat chat.',
      'Kalau ada yang mengaku keluarga dan minta uang, minta kata sandinya dulu.',
    ],
  };
  const LANGKAH_TELEPON = {
    k: 'telp-dulu', t: 'Sepakat: telepon dulu sebelum transfer',
    cara: [
      'Bilang langsung: untuk permintaan uang apa pun, termasuk yang mengaku anak atau saudara, telepon ke nomor lamanya dulu.',
      'Contoh kalimat: "Kalau ada yang minta uang atas namaku, telepon aku dulu ya."',
      'Kalau tidak diangkat, tunggu. Yang benar-benar butuh akan menelepon balik.',
    ],
  };
  const LANGKAH_BANK = {
    k: 'bank', t: 'Simpan nomor resmi bank di kontak',
    cara: [
      'Cari nomor layanan di aplikasi bank atau di belakang kartu ATM atau debit.',
      'Simpan di kontak dengan nama yang jelas, misalnya "Bank (resmi)".',
      'Nomor ini dipakai untuk menelepon balik, bukan nomor yang tertera di pesan.',
    ],
  };

  const CHECKLIST = {
    ortu: [
      { k: 'wa-2fa', t: 'Aktifkan verifikasi dua langkah di WhatsApp',
        cara: [
          'Buka WhatsApp, ketuk titik tiga di kanan atas, lalu pilih Setelan.',
          'Pilih Akun, lalu Verifikasi dua langkah, lalu Aktifkan.',
          'Buat PIN 6 digit yang hanya diketahui pemiliknya, lalu tambahkan email pemulihan.',
          'PIN ini berbeda dari kode 6 digit yang dikirim lewat SMS. Keduanya tidak boleh diberikan ke siapa pun.',
        ] },
      { k: 'apk', t: 'Matikan pemasangan aplikasi dari sumber tak dikenal',
        cara: [
          'Buka Setelan di HP, lalu cari "aplikasi tidak dikenal" atau "install unknown apps" di kolom pencarian.',
          'Pastikan WhatsApp, Chrome, dan File Manager tidak diizinkan memasang aplikasi.',
          'Nama menunya bisa sedikit berbeda di tiap merek HP.',
        ] },
      LANGKAH_BANK,
      LANGKAH_TELEPON,
      LANGKAH_SANDI,
    ],
    adik: [
      { k: 'otp', t: 'Tidak membagikan kode OTP, termasuk ke teman game',
        cara: [
          'Kode OTP adalah kunci masuk akunmu. Siapa pun yang meminta, termasuk admin atau teman, jangan diberi.',
          'Kalau ada yang bilang "salah kirim kode, tolong kirim balik", itu modus. Abaikan.',
        ] },
      { k: 'privat', t: 'Akun media sosial diatur privat',
        cara: [
          'Di Instagram, buka Profil, menu tiga garis, lalu cari "Privasi akun" dan aktifkan Akun privat.',
          'Di aplikasi lain, cari menu Privasi atau Keamanan.',
          'Terima pengikut hanya dari yang benar-benar dikenal.',
        ] },
      { k: 'topup', t: 'Top-up game hanya lewat toko resmi',
        cara: [
          'Top-up lewat menu toko di dalam game atau situs resmi penerbitnya.',
          'Jangan login ke akun game lewat link dari orang lain.',
          'Harga yang jauh di bawah normal biasanya jebakan.',
        ] },
      { k: 'tanya', t: 'Tanya kakak dulu sebelum klik tautan hadiah',
        cara: [
          'Hadiah gratis yang meminta login, biaya, atau kode adalah ciri penipuan.',
          'Kirim tautannya ke kakak, atau periksa di halaman Cek di Jeda.',
        ] },
      LANGKAH_SANDI,
    ],
    kakek: [
      { k: 'favorit', t: 'Nomormu disimpan sebagai kontak favorit',
        cara: [
          'Buka aplikasi Kontak di HP mereka, lalu pilih nomormu.',
          'Ketuk ikon bintang untuk menjadikannya favorit.',
          'Beri nama yang gampang dikenali, misalnya nama panggilan yang mereka pakai.',
        ] },
      LANGKAH_TELEPON,
      { k: 'otp', t: 'Tidak memberi kode SMS ke siapa pun lewat telepon',
        cara: [
          'Bank, polisi, atau perusahaan mana pun tidak meminta kode yang masuk lewat SMS.',
          'Kalau ada yang meminta, tutup telepon dan hubungi kamu.',
        ] },
      { k: 'bank', t: 'Nomor resmi bank ditempel di dekat telepon',
        cara: [
          'Cetak Kartu tempel di halaman ini dan tulis nomor bank dari belakang kartu ATM.',
          'Tempel di dekat telepon atau di kulkas.',
        ] },
      LANGKAH_SANDI,
    ],
    teman: [
      { k: 'verif', t: 'Telepon langsung sebelum transfer ke siapa pun',
        cara: [
          'Walau chatnya datang dari akun yang dikenal, akunnya bisa saja sudah diretas.',
          'Telepon atau video call dulu untuk memastikan.',
        ] },
      { k: 'nomor-lama', t: 'Kalau ada yang minta uang dari nomor baru, hubungi nomor lamanya',
        cara: [
          'Simpan nomor lama temanmu sebelum dihapus.',
          'Hubungi lewat nomor lama atau kanal lain sebelum percaya.',
        ] },
      { k: 'tautan', t: 'Tidak mengklik tautan yang dikirim tanpa konteks',
        cara: [
          'Tanya dulu: link apa ini dan kenapa dikirim?',
          'Tempel tautannya di halaman Cek di Jeda sebelum dibuka.',
        ] },
      { k: 'saling', t: 'Saling mengingatkan kalau ada modus baru',
        cara: [
          'Kirim satu pesan peringatan singkat ke teman dekat tiap kali menemukan modus baru.',
          'Kartu "Pesan peringatan" di halaman ini bisa langsung dibagikan.',
        ] },
    ],
  };

  // 64 kata sehari-hari yang mudah diucapkan. Kata sandi = 3 kata berbeda dari daftar ini.
  const KATA = [
    'kerupuk', 'payung', 'kapal', 'tikar', 'sepeda', 'rawon', 'senja', 'jendela',
    'lampion', 'gerobak', 'ketupat', 'gentong', 'angklung', 'becak', 'cermin', 'kendi',
    'lesung', 'sarung', 'tampah', 'wajan', 'tangga', 'dermaga', 'pelita', 'layangan',
    'bantal', 'seruling', 'kebun', 'sungai', 'pelangi', 'jembatan', 'kompor', 'lemari',
    'malam', 'biru', 'kecil', 'goreng', 'tua', 'pedas', 'hijau', 'basah',
    'manis', 'terbang', 'hangat', 'sepi', 'ramai', 'harum', 'licin', 'bulat',
    'tinggi', 'pendek', 'kuning', 'dingin', 'lembut', 'keras', 'segar', 'asin',
    'jauh', 'dekat', 'putih', 'merah', 'ungu', 'cepat', 'pelan', 'riang',
  ];

  /* ================= Helper ================= */

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  // Semua teks dari data atau pengguna dimasukkan lewat textContent, tidak pernah innerHTML.
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
    const el = $('#jedaToast');
    if (!el) return;
    el.textContent = message;
    el.classList.add('is-shown');
    clearTimeout(window.__jedaToastTimer);
    window.__jedaToastTimer = setTimeout(() => el.classList.remove('is-shown'), 2800);
  }

  const bacaLokal = (kunci, awal) => {
    try {
      const v = JSON.parse(localStorage.getItem(kunci));
      return v && typeof v === 'object' ? v : awal;
    } catch (_) { return awal; }
  };
  const simpanLokal = (kunci, nilai) => {
    try { localStorage.setItem(kunci, JSON.stringify(nilai)); } catch (_) { /* penyimpanan dimatikan: abaikan */ }
  };

  async function kirimJson(url, data) {
    try {
      await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data),
      });
    } catch (_) { /* pencatatan bersifat opsional */ }
  }
  const catatAksi = (action) => kirimJson(URL_AKSI, { feature: 'keluarga', action });

  async function salin(teks, sukses) {
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(teks);
      } else {
        const area = document.createElement('textarea');
        area.value = teks;
        area.style.cssText = 'position:fixed;opacity:0;left:-9999px';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();
        if (!ok) throw new Error('salin gagal');
      }
      toast(sukses);
    } catch (_) {
      toast('Salin manual: pilih teksnya lalu tekan lama dan pilih Salin.');
    }
  }

  async function bagikanTeks(teks) {
    const seluler = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    if (navigator.share && seluler) {
      try { await navigator.share({ text: teks }); return; } catch (e) { if (e && e.name === 'AbortError') return; }
    }
    window.open('https://wa.me/?text=' + encodeURIComponent(teks), '_blank', 'noopener');
  }

  /* ================= Penyimpanan ================= */

  const K_CHECK = 'jeda-keluarga';           // { persona: { itemId: true } } (sama seperti versi sebelumnya)
  const K_PREF = 'jeda-keluarga-pilihan';    // { sapaan: { persona: teks }, modus: { persona: id } }
  const K_G = 'jeda-guardian';               // { n, w, bank } (dipakai juga oleh halaman /bagikan)

  const bacaGuardian = () => {
    const g = bacaLokal(K_G, {});
    return { n: typeof g.n === 'string' ? g.n : '', w: typeof g.w === 'string' ? g.w : '', bank: typeof g.bank === 'string' ? g.bank : '' };
  };

  /* ================= Nama dan nomor ================= */

  function bersihNama(s) {
    return String(s || '').replace(/[^\p{L}\p{N} .,'’-]/gu, '').replace(/\s+/g, ' ').trim().slice(0, 24);
  }

  // "0812-3456-789" -> "62812…", "+62 812…" -> "62812…". Kembalikan '' kalau tidak masuk akal.
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

  /* ================= Pilihan orang ================= */

  let aktif = 'ortu';
  let pref = bacaLokal(K_PREF, {});
  pref.sapaan = pref.sapaan && typeof pref.sapaan === 'object' ? pref.sapaan : {};
  pref.modus = pref.modus && typeof pref.modus === 'object' ? pref.modus : {};

  const el = {
    orang: $('#orang'),
    ringkas: $('#ringkasProgres'),
    judulChecklist: $('#judulChecklist'),
    progresTeks: $('#progresTeks'),
    progresWrap: $('#progresWrap'),
    progresBar: $('#progresBar'),
    daftar: $('#daftar'),
    modus: $('#modus'),
    sapaan: $('#sapaan'),
    isiPesan: $('#isiPesan'),
    bagikanPesan: $('#bagikanPesan'),
    salinTeks: $('#salinTeks'),
    modeSandi: $('#modeSandi'),
    kataSandi: $('#kataSandi'),
    sandiWrap: $('#sandiSendiriWrap'),
    sandiSendiri: $('#sandiSendiri'),
    acakLagi: $('#acakLagi'),
    gNama: $('#gNama'),
    gWa: $('#gWa'),
    gError: $('#gError'),
    buatLink: $('#buatLink'),
    hasilLink: $('#hasilLink'),
    linkOrtu: $('#linkOrtu'),
    bagikanLink: $('#bagikanLink'),
    salinLink: $('#salinLink'),
    gBank: $('#gBank'),
    kartuNama: $('#kartuNama'),
    kartuWa: $('#kartuWa'),
    kartuBankWrap: $('#kartuBankWrap'),
    kartuBank: $('#kartuBank'),
    cetakKartu: $('#cetakKartu'),
  };

  function setPressed(row, tombol) {
    $$('.chip', row).forEach((c) => {
      const on = c === tombol;
      c.classList.toggle('on', on);
      c.setAttribute('aria-pressed', String(on));
    });
  }

  /* ----- Checklist ----- */

  function hitungSelesai(persona) {
    const simpan = bacaLokal(K_CHECK, {})[persona] || {};
    return CHECKLIST[persona].filter((i) => simpan[i.k]).length;
  }

  function gambarRingkas() {
    const bagian = Object.keys(NAMA_CHIP).map((p) => `${NAMA_CHIP[p]} ${hitungSelesai(p)}/${CHECKLIST[p].length}`);
    el.ringkas.textContent = 'Progres: ' + bagian.join(' · ');
  }

  function gambarProgres() {
    const total = CHECKLIST[aktif].length;
    const selesai = hitungSelesai(aktif);
    const persen = total ? Math.round((selesai / total) * 100) : 0;
    el.progresTeks.textContent = `${selesai} dari ${total} langkah selesai`;
    el.progresBar.style.width = persen + '%';
    el.progresWrap.setAttribute('aria-valuenow', String(persen));
    el.progresWrap.setAttribute('aria-valuetext', `${selesai} dari ${total} langkah selesai`);
    gambarRingkas();
  }

  function gambarChecklist() {
    const tersimpan = bacaLokal(K_CHECK, {})[aktif] || {};
    el.judulChecklist.textContent = 'Checklist untuk ' + LABEL_ORANG[aktif];
    el.daftar.replaceChildren(...CHECKLIST[aktif].map((item) => {
      const kotak = h('input', { type: 'checkbox' });
      kotak.checked = Boolean(tersimpan[item.k]);
      kotak.addEventListener('change', () => {
        const semua = bacaLokal(K_CHECK, {});
        semua[aktif] = semua[aktif] && typeof semua[aktif] === 'object' ? semua[aktif] : {};
        semua[aktif][item.k] = kotak.checked;
        simpanLokal(K_CHECK, semua);
        gambarProgres();
        kirimJson(URL_CHECKLIST, { feature: 'keluarga', situation: aktif, item: item.k, done: kotak.checked });
      });
      return h('li', { class: 'kel-item' },
        h('label', {}, kotak, h('span', {}, item.t)),
        h('details', { class: 'kel-cara' },
          h('summary', {}, 'Caranya'),
          h('ol', {}, item.cara.map((c) => h('li', {}, c)))));
    }));
    gambarProgres();
  }

  /* ----- Pesan ----- */

  function modusAktif() {
    const daftar = MODUS[aktif];
    return daftar.find((m) => m.k === pref.modus[aktif]) || daftar[0];
  }

  function teksPesan() {
    const sapaan = el.sapaan.value.trim() || SAPAAN_AWAL[aktif];
    return modusAktif().teks.split('{s}').join(sapaan);
  }

  function gambarPesan() {
    el.isiPesan.textContent = teksPesan();
  }

  function gambarModus() {
    const terpilih = modusAktif();
    el.modus.replaceChildren(...MODUS[aktif].map((m) => {
      const b = h('button', { type: 'button', class: 'chip' + (m === terpilih ? ' on' : ''), 'aria-pressed': String(m === terpilih) }, m.label);
      b.addEventListener('click', () => {
        pref.modus[aktif] = m.k;
        simpanLokal(K_PREF, pref);
        setPressed(el.modus, b);
        gambarPesan();
      });
      return b;
    }));
    const tersimpan = pref.sapaan[aktif];
    el.sapaan.value = typeof tersimpan === 'string' ? tersimpan : SAPAAN_AWAL[aktif];
    gambarPesan();
  }

  el.sapaan.addEventListener('input', () => {
    pref.sapaan[aktif] = el.sapaan.value.slice(0, 20);
    simpanLokal(K_PREF, pref);
    gambarPesan();
  });

  el.bagikanPesan.addEventListener('click', () => { catatAksi('buka-whatsapp'); bagikanTeks(teksPesan()); });
  el.salinTeks.addEventListener('click', () => salin(teksPesan(), 'Teks pesan disalin.'));

  /* ----- Kata sandi ----- */

  const sandi = {}; // per persona, hanya di memori halaman
  let modeSandi = 'acak';

  function acakInt(n) {
    if (!window.crypto || !crypto.getRandomValues) return Math.floor(Math.random() * n);
    const a = new Uint32Array(1);
    const batas = Math.floor(0x100000000 / n) * n; // buang nilai berlebih supaya tidak bias
    do { crypto.getRandomValues(a); } while (a[0] >= batas);
    return a[0] % n;
  }

  function buatKataSandi() {
    const pool = KATA.slice();
    const hasil = [];
    for (let i = 0; i < 3; i++) hasil.push(pool.splice(acakInt(pool.length), 1)[0]);
    return hasil.join(' ');
  }

  function gambarSandi() {
    if (modeSandi === 'acak') {
      if (!sandi[aktif]) sandi[aktif] = buatKataSandi();
      el.kataSandi.textContent = sandi[aktif];
      el.sandiWrap.hidden = true;
      el.acakLagi.hidden = false;
    } else {
      el.kataSandi.textContent = el.sandiSendiri.value.trim() || 'Ketik kata sandimu di bawah';
      el.sandiWrap.hidden = false;
      el.acakLagi.hidden = true;
    }
  }

  el.modeSandi.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-mode]');
    if (!b) return;
    modeSandi = b.dataset.mode;
    setPressed(el.modeSandi, b);
    gambarSandi();
    if (modeSandi === 'sendiri') el.sandiSendiri.focus();
  });
  el.acakLagi.addEventListener('click', () => { sandi[aktif] = buatKataSandi(); gambarSandi(); });
  el.sandiSendiri.addEventListener('input', gambarSandi);

  /* ----- Pilih orang ----- */

  function pilihOrang(kunci) {
    aktif = kunci;
    gambarChecklist();
    gambarModus();
    gambarSandi();
  }

  el.orang.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-orang]');
    if (!b || !CHECKLIST[b.dataset.orang]) return;
    setPressed(el.orang, b);
    pilihOrang(b.dataset.orang);
  });

  /* ================= Jeda di HP ortu + kartu tempel ================= */

  function simpanGuardian() {
    simpanLokal(K_G, { n: bersihNama(el.gNama.value), w: normalisasiWa(el.gWa.value), bank: el.gBank.value.slice(0, 240) });
  }

  function barisBank() {
    return el.gBank.value.split(/\r?\n/).map((s) => s.trim().slice(0, 80)).filter(Boolean).slice(0, 3);
  }

  function gambarKartu() {
    const nama = bersihNama(el.gNama.value);
    const wa = tampilWa(normalisasiWa(el.gWa.value));
    el.kartuNama.textContent = nama || '(isi nama di sebelah)';
    el.kartuWa.textContent = wa || '(isi nomor di sebelah)';
    const bank = barisBank();
    el.kartuBankWrap.hidden = bank.length === 0;
    el.kartuBank.replaceChildren(...bank.map((b) => h('li', {}, b)));
  }

  function tampilError(pesan) {
    el.gError.textContent = pesan;
    el.gError.hidden = !pesan;
  }

  function buatLinkOrtu() {
    const n = bersihNama(el.gNama.value);
    const w = normalisasiWa(el.gWa.value);
    if (!n) { tampilError('Isi nama kamu dulu, supaya tombol di HP mereka bertuliskan namamu.'); el.gNama.focus(); return null; }
    if (!w) { tampilError('Nomor WhatsApp belum valid. Contoh: 08123456789'); el.gWa.focus(); return null; }
    tampilError('');
    // Bagian # tidak ikut dikirim browser ke server.
    return URL_BAGIKAN + '#' + new URLSearchParams({ w, n }).toString();
  }

  function pesanUntukOrtu(link) {
    const sapaan = (typeof pref.sapaan.ortu === 'string' && pref.sapaan.ortu.trim()) || SAPAAN_AWAL.ortu;
    return `${sapaan}, ini Jeda: alat untuk memeriksa pesan atau link yang mencurigakan. Buka link ini di Chrome, lalu pasang di layar utama. Nanti kalau ragu sama sebuah pesan, tinggal dibagikan ke Jeda:\n${link}`;
  }

  el.buatLink.addEventListener('click', () => {
    const link = buatLinkOrtu();
    if (!link) return;
    simpanGuardian();
    el.linkOrtu.value = link;
    el.hasilLink.hidden = false;
    catatAksi('buat-link-ortu');
  });
  el.bagikanLink.addEventListener('click', () => { if (el.linkOrtu.value) { catatAksi('bagikan-link-ortu'); bagikanTeks(pesanUntukOrtu(el.linkOrtu.value)); } });
  el.salinLink.addEventListener('click', () => { if (el.linkOrtu.value) salin(el.linkOrtu.value, 'Link disalin.'); });
  el.linkOrtu.addEventListener('focus', () => el.linkOrtu.select());

  // Isian yang berubah membuat link lama tidak berlaku lagi, jadi sembunyikan.
  const siapkanUlang = () => { el.hasilLink.hidden = true; tampilError(''); simpanGuardian(); gambarKartu(); };
  el.gNama.addEventListener('input', siapkanUlang);
  el.gWa.addEventListener('input', siapkanUlang);
  el.gBank.addEventListener('input', () => { simpanGuardian(); gambarKartu(); });

  el.cetakKartu.addEventListener('click', () => {
    if (!el.gNama.value.trim() || !normalisasiWa(el.gWa.value)) {
      toast('Isi nama dan nomor WhatsApp dulu supaya kartunya lengkap.');
      (el.gNama.value.trim() ? el.gWa : el.gNama).focus();
      return;
    }
    catatAksi('cetak-kartu');
    window.print();
  });

  /* ================= Mulai ================= */

  const g = bacaGuardian();
  el.gNama.value = g.n;
  el.gWa.value = tampilWa(g.w);
  el.gBank.value = g.bank;
  gambarKartu();
  pilihOrang('ortu');

})();