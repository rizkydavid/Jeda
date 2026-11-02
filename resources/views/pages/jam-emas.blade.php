@extends('layouts.app')

@section('title', 'Rencana Jam Emas')

@section('content')

<main class="wrap">
    <style>
    .grid2 { align-items: stretch; }

    /* kolom kanan jadi flex, kartu terakhir mengisi sisa tinggi */
    .grid2 > .stack { display: flex; flex-direction: column; }
    .grid2 > .stack > .card:last-child {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    /* tombol selalu menempel di bawah kartu */
    .grid2 > .stack > .card:last-child .btn { margin-top: auto; }
</style>
    <h1 style="font-size:40px">Rencana Jam Emas</h1>
    <p class="mut">Pilih apa yang sudah terjadi. Langkah di bawah menyesuaikan.</p>

    <div class="row" id="situasi" style="margin:16px 0 24px">
        <span class="chip on" data-situasi="transfer" style="cursor:pointer">Sudah transfer uang</span>
        <span class="chip" data-situasi="otp" style="cursor:pointer">Sudah beri kode OTP</span>
        <span class="chip" data-situasi="tautan" style="cursor:pointer">Sudah klik tautan</span>
        <span class="chip" data-situasi="dibobol" style="cursor:pointer">Akun dibobol</span>
    </div>

    <div class="card dark" style="margin-bottom:20px">
        <h2>Ini bukan salahmu.</h2>
        <p class="mut" style="margin:0;max-width:680px">
            Penipu memang dilatih untuk menekan dan membuat orang panik. Banyak korban malu bercerita, padahal bercerita cepat adalah langkah paling membantu.
        </p>
    </div>

    <div class="grid2">
        <div class="card">
            <h2>Lakukan sekarang</h2>
            <div class="check"><i></i><div>
                <h3>Hubungi bank atau e-wallet</h3>
                <p class="mut" style="margin:0">Minta transaksi ditahan atau diblokir. Makin cepat, makin besar peluangnya.</p>
            </div></div>
            <div class="check"><i></i><div>
                <h3>Simpan bukti</h3>
                <p class="mut" style="margin:0">Tangkapan layar chat, nomor rekening tujuan, dan bukti transfer.</p>
            </div></div>
            <div class="check"><i></i><div>
                <h3>Hentikan kontak dengan pelaku</h3>
                <p class="mut" style="margin:0">Jangan transfer lagi, jangan ikut ancaman atau bujukan.</p>
            </div></div>
            <div class="check"><i></i><div>
                <h3>Ganti kata sandi dan aktifkan 2FA</h3>
                <p class="mut" style="margin:0">Mulai dari email dan akun keuangan.</p>
            </div></div>
        </div>

        <div class="stack">
            <div class="card">
                <h2>Bingung mulai cerita?</h2>
                <p class="mut">Kalimat pembuka ini mengikuti pilihanmu di atas. Tinggal salin dan kirim ke orang yang kamu percaya.</p>
                <div class="q" id="kalimat" style="font-size:15px"></div>
                <a class="btn ghost block" id="btnSalin" href="javascript:void(0)" style="margin-top:12px">Salin kalimat</a>
            </div>

            <div class="card dark">
                <h2>Peringatkan orang lain</h2>
                <p class="mut">Kirim pesan siap pakai ke teman atau keluarga supaya mereka tidak ikut jadi korban.</p>
                <a class="btn block" href="javascript:void(0)">Salin pesan peringatan</a>
            </div>
        </div>
    </div>
</main>

<script>
    const cerita = {
        transfer: "Aku mau cerita. Tadi aku sudah transfer uang ke orang yang ternyata penipu. Aku butuh bantuan buat hubungi bank dan lapor.",
        otp: "Aku mau cerita. Tadi aku sempat kasih kode OTP ke orang yang mengaku dari pihak resmi. Sepertinya itu penipuan, dan aku butuh bantuan buat amankan akunku.",
        tautan: "Aku mau cerita. Tadi aku klik tautan dari pesan yang mencurigakan. Aku takut datanya bocor, tolong bantu aku cek dan amankan akunku ya.",
        dibobol: "Aku mau cerita. Akunku sepertinya dibobol orang. Aku butuh bantuan buat ambil alih lagi dan cek apakah ada yang hilang."
    };

    const chips = document.querySelectorAll('#situasi .chip');
    const kalimat = document.getElementById('kalimat');
    const btnSalin = document.getElementById('btnSalin');
    let dipilih = 'transfer';

    function tampil(situasi) {
        dipilih = situasi;
        chips.forEach(c => c.classList.toggle('on', c.dataset.situasi === situasi));
        kalimat.textContent = '"' + cerita[situasi] + '"';
    }

    chips.forEach(c => c.addEventListener('click', () => tampil(c.dataset.situasi)));

    btnSalin.addEventListener('click', () => {
        navigator.clipboard.writeText(cerita[dipilih]).then(() => {
            btnSalin.textContent = 'Tersalin ✓';
            setTimeout(() => btnSalin.textContent = 'Salin kalimat', 2000);
        });
    });

    tampil(dipilih);
</script>

@endsection