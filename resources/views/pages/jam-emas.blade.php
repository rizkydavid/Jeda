@extends('layouts.app')

@section('title', 'Rencana Jam Emas')

@section('content')

<main class="wrap">
    <h1 style="font-size:40px">
        Rencana Jam Emas
    </h1>
    <p class="mut">
        Pilih apa yang sudah terjadi. Langkah di bawah menyesuaikan.
    </p>
    <div class="row" style="margin:16px 0 24px">
        <span class="chip on">
            Sudah transfer uang
        </span>
        <span class="chip">
            Sudah beri kode OTP
        </span>
        <span class="chip">
            Sudah klik tautan
        </span>
        <span class="chip">
            Akun dibobol
        </span>
    </div>
    <div class="card dark" style="margin-bottom:20px">
        <h2>
            Ini bukan salahmu.
        </h2>
        <p class="mut" style="margin:0;max-width:680px">
            Penipu memang dilatih untuk menekan dan membuat orang panik. Banyak korban malu bercerita, padahal bercerita cepat adalah langkah paling membantu.
        </p>
    </div>
    <div class="grid2">
        <div class="card">
            <h2>
                Lakukan sekarang
            </h2>
            <div class="check">
                <i>
                </i>
                <div>
                    <h3>
                        Hubungi bank atau e-wallet
                    </h3>
                    <p class="mut" style="margin:0">
                        Minta transaksi ditahan atau diblokir. Makin cepat, makin besar peluangnya.
                    </p>
                </div>
            </div>
            <div class="check">
                <i>
                </i>
                <div>
                    <h3>
                        Simpan bukti
                    </h3>
                    <p class="mut" style="margin:0">
                        Tangkapan layar chat, nomor rekening tujuan, dan bukti transfer.
                    </p>
                </div>
            </div>
            <div class="check">
                <i>
                </i>
                <div>
                    <h3>
                        Hentikan kontak dengan pelaku
                    </h3>
                    <p class="mut" style="margin:0">
                        Jangan transfer lagi, jangan ikut ancaman atau bujukan.
                    </p>
                </div>
            </div>
            <div class="check">
                <i>
                </i>
                <div>
                    <h3>
                        Ganti kata sandi dan aktifkan 2FA
                    </h3>
                    <p class="mut" style="margin:0">
                        Mulai dari email dan akun keuangan.
                    </p>
                </div>
            </div>
        </div>
        <div class="stack">
            <div class="card">
                <h2>
                    Lapor lewat kanal resmi
                </h2>
                <p class="mut">
                    Daftar kontak resmi akan ditampilkan di sini.
                </p>
                <a class="btn ghost block" href="javascript:void(0)">
                    Buka panduan pelaporan
                </a>
            </div>
            <div class="card">
                <h2>
                    Bingung mulai cerita?
                </h2>
                <p class="mut">
                    Pilih ke siapa kamu mau cerita. Kalimat pembuka disiapkan.
                </p>
                <div class="row" style="margin-bottom:14px">
                    <span class="chip on">
                        Orang tua
                    </span>
                    <span class="chip">
                        Teman
                    </span>
                    <span class="chip">
                        Pihak bank
                    </span>
                </div>
                <div class="q" style="font-size:15px">
                    "Ma, aku mau cerita. Tadi aku hampir kena tipu, dan aku butuh bantuan Mama buat ngurusnya."
                </div>
                <a class="btn ghost block" href="javascript:void(0)" style="margin-top:12px">
                    Salin kalimat
                </a>
            </div>
            <div class="card dark">
                <h2>
                    Peringatkan orang lain
                </h2>
                <p class="mut">
                    Kirim pesan siap pakai ke teman atau keluarga supaya mereka tidak ikut jadi korban.
                </p>
                <a class="btn block" href="javascript:void(0)">
                    Salin pesan peringatan
                </a>
            </div>
        </div>
    </div>
</main>

@endsection
