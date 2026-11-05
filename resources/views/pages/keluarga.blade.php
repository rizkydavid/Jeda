@extends('layouts.app')

@section('title', 'Lindungi Keluarga')

@section('content')

<style>
    /* Dua kolom dengan kartu sebaris yang tingginya sama. */
    .keluarga-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: stretch; }
    .keluarga-grid > .card { height: 100%; box-sizing: border-box; display: flex; flex-direction: column; }
    .keluarga-grid .bawah { margin-top: auto; padding-top: 14px; }
    @media (max-width: 760px) { .keluarga-grid { grid-template-columns: 1fr; } }
</style>

<main class="wrap">
    <section class="hero">
        <h1>Lindungi keluarga.</h1>
        <p class="mut" style="max-width:620px;font-size:18px">
            Kamu guardian di rumahmu. Pilih siapa yang mau dilindungi, Jeda siapkan langkah dan pesan yang paling pas untuk mereka.
        </p>
    </section>

    <h2>Siapa yang mau kamu lindungi?</h2>
    <div class="row" id="orang" style="margin-bottom:24px">
        <span class="chip on" data-orang="ortu" style="cursor:pointer">Orang tua</span>
        <span class="chip" data-orang="adik" style="cursor:pointer">Adik</span>
        <span class="chip" data-orang="kakek" style="cursor:pointer">Kakek dan nenek</span>
        <span class="chip" data-orang="teman" style="cursor:pointer">Teman</span>
    </div>

    <div class="keluarga-grid">
        {{-- Baris 1 --}}
        <div class="card">
            <h2 style="margin-top:0" id="judulChecklist">Checklist perlindungan</h2>
            <p class="small" id="progresTeks" style="margin-bottom:8px"></p>
            <div style="height:8px;border-radius:99px;background:rgba(127,127,127,.25);overflow:hidden;margin-bottom:12px">
                <div id="progresBar" style="height:100%;width:0;background:var(--accent,#6c7bff);transition:width .25s"></div>
            </div>
            <div id="daftar"></div>
        </div>

        <div class="card">
            <h2 style="margin-top:0">Kartu peringatan siap kirim</h2>
            <p class="small">Pratinjau pesan WhatsApp</p>
            <div class="bubble me" style="max-width:100%;border-radius:16px">
                <b style="color:#cfd8ff;display:block;margin-bottom:4px">Dari kamu</b>
                <span id="isiPesan"></span>
            </div>
            <div class="bawah">
                <a class="btn block" id="kirimWa" href="#">Kirim lewat WhatsApp</a>
                <a class="btn block" id="salinPesan" href="#" style="margin-top:10px">Salin pesan</a>
            </div>
        </div>

        {{-- Baris 2 --}}
        <div class="card">
            <h2 style="margin-top:0" id="judulSandi">Kata sandi keluarga</h2>
            <p class="mut" style="margin-top:0">
                Satu kata rahasia yang hanya orang tertentu yang tahu. Kalau ada yang ngaku "ini nomor baru, aku" dan minta transfer, tanya kata sandinya dulu.
            </p>
            <div class="row" id="modeSandi" style="margin-bottom:12px">
                <span class="chip on" data-mode="acak" style="cursor:pointer">Acak ide kata</span>
                <span class="chip" data-mode="sendiri" style="cursor:pointer">Tulis sendiri</span>
            </div>
            <div class="bubble me" style="align-self:stretch;width:100%;max-width:100%;margin:0;box-sizing:border-box;border-radius:16px;text-align:center;padding:18px 16px">
                <b style="display:block;font-size:22px;color:#fff;word-break:break-word" id="kataSandi"></b>
            </div>
            <div id="panelAcak" style="margin-top:12px">
                <a class="btn block" id="acakKata" href="#">Acak lagi</a>
            </div>
            <div id="panelSendiri" style="margin-top:12px;display:none">
                <input id="kataSendiri" type="text" maxlength="40" autocomplete="off"
                       placeholder="Ketik kata atau frasa kalian"
                       style="width:100%;box-sizing:border-box;padding:12px 14px;border-radius:12px;border:1px solid rgba(127,127,127,.4);background:transparent;color:inherit;font:inherit">
            </div>
            <p class="small bawah" style="margin-bottom:0">
                Hindari tanggal lahir atau nama hewan yang ada di media sosial. Sepakati langsung saat bertemu, jangan lewat chat. Jeda tidak menyimpan kata ini.
            </p>
        </div>

        <div class="card">
            <h2 style="margin-top:0">Kalau sudah terlanjur</h2>
            <ol style="margin:0;padding-left:20px;line-height:1.6">
                <li><b>Tenang dan jangan salahkan.</b> Yang terpenting sekarang adalah bergerak cepat.</li>
                <li><b>Hubungi bank</b> lewat kontak resmi di aplikasi atau kartu, minta blokir atau tahan transaksi.</li>
                <li><b>Simpan bukti:</b> tangkapan layar chat, nomor atau rekening tujuan, dan waktu transaksi.</li>
                <li><b>Ganti kata sandi</b> dan keluarkan perangkat asing dari akun yang terkait.</li>
                <li><b>Laporkan</b> ke kanal resmi: bank, kepolisian, atau Komdigi.</li>
            </ol>
        </div>
    </div>
</main>

<script>
    const csrf = '{{ csrf_token() }}';

    const pesan = {
        ortu: "Ma, ada penipuan yang lagi ramai: ada yang ngaku jadi aku dan minta transfer. Kalau ada pesan begitu, telepon aku dulu ya, jangan langsung transfer.",
        adik: "Dek, lagi ramai penipuan: ada yang ngaku kenalan atau nawarin hadiah dan akun game murah lewat chat, terus minta transfer atau kode OTP. Kalau ada yang begitu, tanya kakak dulu ya, jangan langsung transfer atau klik tautannya.",
        kakek: "Kek, Nek, lagi ramai penipuan: ada yang ngaku jadi cucu atau petugas bank lewat telepon, lalu minta transfer atau kode OTP. Kalau ada yang begitu, telepon aku dulu ya, jangan kasih kode apa pun.",
        teman: "Bro, lagi ramai penipuan: ada yang ngaku jadi temen atau kenalan dari nomor baru, terus minta transfer cepet-cepet. Kalau dapat pesan begitu, cek dulu ke orangnya langsung sebelum transfer ya."
    };

    const label = { ortu: 'orang tua', adik: 'adik', kakek: 'kakek dan nenek', teman: 'teman' };

    const checklist = {
        ortu: [
            ['wa-2fa', 'Aktifkan verifikasi dua langkah di WhatsApp'],
            ['apk', 'Matikan pemasangan aplikasi dari sumber tak dikenal'],
            ['bank', 'Simpan nomor resmi bank di kontak'],
            ['telp-dulu', 'Sepakat: telepon dulu sebelum transfer'],
            ['sandi', 'Tetapkan kata sandi keluarga']
        ],
        adik: [
            ['otp', 'Tidak membagikan kode OTP, termasuk ke teman game'],
            ['privat', 'Akun media sosial diatur privat'],
            ['topup', 'Top-up game hanya lewat toko resmi'],
            ['tanya', 'Tanya kakak dulu sebelum klik tautan hadiah'],
            ['sandi', 'Tetapkan kata sandi keluarga']
        ],
        kakek: [
            ['favorit', 'Nomor kamu disimpan sebagai kontak favorit'],
            ['telp-dulu', 'Sepakat: telepon dulu sebelum transfer'],
            ['otp', 'Tidak memberi kode SMS ke siapa pun lewat telepon'],
            ['bank', 'Simpan nomor resmi bank di dekat telepon'],
            ['sandi', 'Tetapkan kata sandi keluarga']
        ],
        teman: [
            ['verif', 'Telepon langsung sebelum transfer ke siapa pun'],
            ['nomor-lama', 'Kalau ada yang minta uang dari nomor baru, hubungi nomor lamanya'],
            ['tautan', 'Tidak klik tautan yang dikirim tanpa konteks'],
            ['saling', 'Saling ingatkan kalau ada modus baru']
        ]
    };

    const kataA = ['kerupuk', 'payung', 'kapal', 'tikar', 'sepeda', 'rawon', 'senja', 'jendela', 'lampion', 'gerobak'];
    const kataB = ['malam', 'biru', 'kecil', 'goreng', 'tua', 'pedas', 'hijau', 'basah', 'manis', 'terbang'];

    const chips = document.querySelectorAll('#orang .chip');
    const isiPesan = document.getElementById('isiPesan');
    const daftar = document.getElementById('daftar');
    let aktif = 'ortu';

    /* ---------- Checklist ---------- */
    function muat() {
        try { return JSON.parse(localStorage.getItem('jeda-keluarga') || '{}'); } catch (e) { return {}; }
    }
    function simpan(data) {
        try { localStorage.setItem('jeda-keluarga', JSON.stringify(data)); } catch (e) {}
    }

    function hitungProgres() {
        const kotak = daftar.querySelectorAll('input');
        const selesai = [...kotak].filter(k => k.checked).length;
        document.getElementById('progresTeks').textContent = selesai + ' dari ' + kotak.length + ' langkah selesai';
        document.getElementById('progresBar').style.width = (kotak.length ? selesai / kotak.length * 100 : 0) + '%';
    }

    function gambarChecklist() {
        const tersimpan = muat()[aktif] || {};
        document.getElementById('judulChecklist').textContent = 'Checklist untuk ' + label[aktif];
        daftar.innerHTML = '';
        checklist[aktif].forEach(([kunci, teks]) => {
            const baris = document.createElement('label');
            baris.style.cssText = 'display:flex;gap:10px;align-items:flex-start;padding:8px 0;cursor:pointer';
            const kotak = document.createElement('input');
            kotak.type = 'checkbox';
            kotak.style.marginTop = '4px';
            kotak.checked = !!tersimpan[kunci];
            kotak.addEventListener('change', () => {
                const semua = muat();
                semua[aktif] = semua[aktif] || {};
                semua[aktif][kunci] = kotak.checked;
                simpan(semua);
                hitungProgres();
                fetch('{{ route('jeda.api.checklist') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ feature: 'keluarga', situation: aktif, item: kunci, done: kotak.checked })
                }).catch(() => {});
            });
            const span = document.createElement('span');
            span.textContent = teks;
            baris.append(kotak, span);
            daftar.appendChild(baris);
        });
        hitungProgres();
    }

    /* ---------- Pilih orang ---------- */
    function pilih(kunci) {
        aktif = kunci;
        chips.forEach(c => c.classList.toggle('on', c.dataset.orang === kunci));
        isiPesan.textContent = pesan[kunci];
        gambarChecklist();
        tampilSandi();
    }
    chips.forEach(c => c.addEventListener('click', () => pilih(c.dataset.orang)));

    /* ---------- Kartu peringatan ---------- */
    const teksAktif = () => isiPesan.textContent.trim();

    document.getElementById('kirimWa').addEventListener('click', async (e) => {
        e.preventDefault();
        const teks = teksAktif();
        if (navigator.share && /Android|iPhone|iPad/i.test(navigator.userAgent)) {
            try { await navigator.share({ text: teks }); return; }
            catch (err) { if (err.name === 'AbortError') return; }
        }
        window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(teks), '_blank', 'noopener');
    });

    const salin = document.getElementById('salinPesan');
    salin.addEventListener('click', async (e) => {
        e.preventDefault();
        try { await navigator.clipboard.writeText(teksAktif()); salin.textContent = 'Tersalin'; }
        catch (err) { salin.textContent = 'Gagal menyalin'; }
        setTimeout(() => { salin.textContent = 'Salin pesan'; }, 1500);
    });

    /* ---------- Kata sandi keluarga (satu per orang, hanya di memori halaman) ---------- */
    const acak = arr => arr[Math.floor(Math.random() * arr.length)];
    const buatKata = () => acak(kataA) + ' ' + acak(kataB);

    const modeChips = document.querySelectorAll('#modeSandi .chip');
    const kataEl = document.getElementById('kataSandi');
    const panelAcak = document.getElementById('panelAcak');
    const panelSendiri = document.getElementById('panelSendiri');
    const inputSendiri = document.getElementById('kataSendiri');
    const sandi = {};

    const sandiAktif = () => sandi[aktif] || (sandi[aktif] = { mode: 'acak', acak: buatKata(), sendiri: '' });

    function tampilSandi() {
        const s = sandiAktif();
        document.getElementById('judulSandi').textContent = 'Kata sandi untuk ' + label[aktif];
        modeChips.forEach(c => c.classList.toggle('on', c.dataset.mode === s.mode));
        panelAcak.style.display = s.mode === 'acak' ? '' : 'none';
        panelSendiri.style.display = s.mode === 'sendiri' ? '' : 'none';
        if (inputSendiri.value !== s.sendiri) inputSendiri.value = s.sendiri;
        kataEl.textContent = s.mode === 'acak' ? s.acak : (s.sendiri.trim() || 'Ketik kata sandimu di bawah');
    }

    modeChips.forEach(c => c.addEventListener('click', () => {
        const s = sandiAktif();
        s.mode = c.dataset.mode;
        tampilSandi();
        if (s.mode === 'sendiri') inputSendiri.focus();
    }));

    document.getElementById('acakKata').addEventListener('click', (e) => {
        e.preventDefault();
        sandiAktif().acak = buatKata();
        tampilSandi();
    });

    inputSendiri.addEventListener('input', () => {
        sandiAktif().sendiri = inputSendiri.value;
        tampilSandi();
    });

    pilih('ortu');
</script>

@endsection