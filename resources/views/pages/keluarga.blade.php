@extends('layouts.app')

@section('title', 'Lindungi Keluarga')

@section('content')

<main class="wrap">
    <section class="hero">
        <h1>
            Lindungi keluarga.
        </h1>
        <p class="mut" style="max-width:620px;font-size:18px">
            Kamu guardian di rumahmu. Pilih siapa yang mau dilindungi, Jeda siapkan skenario yang paling mungkin mereka temui.
        </p>
    </section>
    <h2>
        Siapa yang mau kamu lindungi?
    </h2>
    <div class="row" style="margin-bottom:24px">
        <span class="chip on">
            Orang tua
        </span>
        <span class="chip">
            Adik
        </span>
        <span class="chip">
            Kakek dan nenek
        </span>
        <span class="chip">
            Teman
        </span>
    </div>
    <div class="grid2">
        <div class="stack">
            <h2 style="margin:0">
                Penipuan yang sering menyasar orang tua
            </h2>
            <div class="flag">
                <span class="tag t-rose">
                    Impersonasi
                </span>
                <h3 style="margin-top:8px">
                    "Ma, ini nomor baru aku, tolong transfer dulu"
                </h3>
                <p class="mut" style="margin:0">
                    Mengaku anak sendiri dari nomor tak dikenal, dengan alasan mendesak.
                </p>
            </div>
            <div class="flag">
                <span class="tag t-amber">
                    Urgensi
                </span>
                <h3 style="margin-top:8px">
                    Undangan atau resi paket berformat file APK
                </h3>
                <p class="mut" style="margin:0">
                    File yang diminta dipasang bisa mengambil alih ponsel. Contoh di Jeda fiktif dan aman.
                </p>
            </div>
            <div class="flag">
                <span class="tag t-violet">
                    Otoritas
                </span>
                <h3 style="margin-top:8px">
                    "Kami dari bank, akun Bapak diblokir"
                </h3>
                <p class="mut" style="margin:0">
                    Mengaku petugas dan meminta kode OTP.
                </p>
            </div>
            <a class="btn block" href="{{ route('simulasi') }}">
                Coba simulasi sebagai orang tua
            </a>
        </div>
        <div class="stack">
            <div class="card">
                <h2>
                    Kartu peringatan siap kirim
                </h2>
                <p class="small">
                    Pratinjau pesan WhatsApp
                </p>
                <div class="bubble me" style="max-width:100%;border-radius:16px">
                    <b style="color:#cfd8ff">
                        Dari kamu
                    </b>
                    Ma, ada penipuan yang lagi ramai: ada yang ngaku jadi aku dan minta transfer. Kalau ada pesan begitu, telepon aku dulu ya, jangan langsung transfer.
                </div>
                <a class="btn block" href="javascript:void(0)" style="margin-top:14px">
                    Kirim lewat WhatsApp
                </a>
            </div>
            <div class="card dark">
                <h2>
                    Cara ngobrol tanpa menggurui
                </h2>
                <p class="mut">
                    Mulai dari cerita, bukan larangan. Misalnya: "Ma, aku baru belajar soal penipuan model begini, boleh aku tunjukin?"
                </p>
                <a class="btn ghost block" href="javascript:void(0)">
                    Lihat tips lengkap
                </a>
            </div>
        </div>
    </div>
</main>

@endsection
