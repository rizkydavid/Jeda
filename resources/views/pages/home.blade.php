@extends('layouts.app')

@section('title', 'Bedah Pesan')

@section('content')

<main class="wrap">
    <section class="hero">
        <h1>
            Ragu? Jeda dulu.
        </h1>
        <p class="mut" style="max-width:640px;font-size:18px">
            Tempel pesan atau tautan yang bikin ragu. Jeda menunjukkan tanda tekanannya, bukan memberi vonis.
        </p>
    </section>
    <div class="grid2">
        <div class="stack">
            <div class="card">
                <h2>
                    Pesan atau tautan yang bikin ragu
                </h2>
                <textarea aria-label="Pesan">Selamat, kamu lolos jadi karyawan remote dengan gaji Rp8 juta per minggu. Konfirmasi dalam 30 menit dan jangan beri tahu siapa pun. Transfer biaya admin Rp150.000 untuk mengunci posisi. Klik: https://bankcontoh.com.login-verif.co.id/verifikasi</textarea>
                <div class="row">
                    <button class="btn">
                        Bedah pesan
                    </button>
                    <button class="btn ghost">
                        Pakai contoh fiktif
                    </button>
                </div>
                <p class="small" style="margin-top:12px">
                    Pemeriksaan dasar berjalan di perangkatmu. Tidak ada teks yang dikirim ke mana pun kecuali kamu memilih analisis AI.
                </p>
            </div>
            <div class="card">
                <h2>
                    Cek mandiri
                </h2>
                <p class="mut">
                    Tandai sendiri apa yang kamu alami. Penilaian tetap ada di tanganmu.
                </p>
                <div class="sw on">
                    <i>
                    </i>
                    Saya diminta bergerak cepat
                </div>
                <div class="sw on">
                    <i>
                    </i>
                    Saya disuruh merahasiakan
                </div>
                <div class="sw">
                    <i>
                    </i>
                    Pengirim tidak bisa dihubungi lewat kanal lain
                </div>
                <div class="sw">
                    <i>
                    </i>
                    Saya diminta uang, kode, atau klik tautan
                </div>
            </div>
            <div class="card dark">
                <h2>
                    Tiga pertanyaan Jeda
                </h2>
                <p class="mut">
                    Selalu muncul, apa pun hasil pemeriksaannya.
                </p>
                <div class="check" style="border-color:#2b3a55">
                    <i style="border-color:#8fa3ff">
                    </i>
                    <div>
                        Siapa pengirimnya, dan bisa kamu pastikan lewat kanal lain?
                    </div>
                </div>
                <div class="check" style="border-color:#2b3a55">
                    <i style="border-color:#8fa3ff">
                    </i>
                    <div>
                        Apakah ada yang mendesak atau menyuruhmu diam?
                    </div>
                </div>
                <div class="check" style="border-color:#2b3a55">
                    <i style="border-color:#8fa3ff">
                    </i>
                    <div>
                        Apakah aku diminta uang, kode, atau klik tautan?
                    </div>
                </div>
            </div>
        </div>
        <div class="stack">
            <div class="card">
                <div class="lvl">
                    <span>
                        Tingkat tekanan
                    </span>
                    <em>
                        Tinggi
                    </em>
                </div>
                <div class="meter hi">
                    <span class="f">
                    </span>
                    <span class="f">
                    </span>
                    <span class="f">
                    </span>
                    <span class="f">
                    </span>
                    <span>
                    </span>
                </div>
                <p class="small" style="margin:10px 0 0">
                    Ini tanda yang perlu dicek, bukan vonis penipuan.
                </p>
            </div>
            <div class="row" style="justify-content:space-between;align-items:baseline">
                <h2 style="margin:0">
                    5 tanda bahaya ditemukan
                </h2>
                <span class="small">
                    Bukan vonis
                </span>
            </div>
            <div class="flag">
                <span class="tag t-amber">
                    Urgensi
                </span>
                <h3 style="margin-top:8px">
                    "Konfirmasi dalam 30 menit"
                </h3>
                <p class="mut" style="margin:0 0 0">
                    Batas waktu singkat membuatmu tidak sempat memeriksa.
                </p>
                <div class="q">
                    <b>
                        Pertanyaan pemutus:
                    </b>
                    Boleh saya konfirmasi besok setelah cek lewat situs resmi?
                </div>
            </div>
            <div class="flag">
                <span class="tag t-rose">
                    Kerahasiaan
                </span>
                <h3 style="margin-top:8px">
                    "Jangan beri tahu siapa pun"
                </h3>
                <p class="mut" style="margin:0">
                    Menyuruhmu diam memutus bantuan dari orang di sekitarmu.
                </p>
                <div class="q">
                    <b>
                        Pertanyaan pemutus:
                    </b>
                    Kenapa tidak boleh bercerita ke keluarga?
                </div>
            </div>
            <div class="flag">
                <span class="tag t-violet">
                    Permintaan uang
                </span>
                <h3 style="margin-top:8px">
                    "Transfer biaya admin Rp150.000"
                </h3>
                <p class="mut" style="margin:0">
                    Lowongan resmi tidak memungut biaya dari pelamar.
                </p>
                <div class="q">
                    <b>
                        Pertanyaan pemutus:
                    </b>
                    Bisakah saya melihat kontrak dan legalitas perusahaan dulu?
                </div>
            </div>
            <div class="card">
                <h3>
                    Pemeriksaan tautan
                </h3>
                <div class="q" style="font-size:14px;word-break:break-all">
                    https://
                    <span style="opacity:.55">
                        bankcontoh.com.
                    </span>
                    <span class="mark t-rose">
                        <b>
                            login-verif.co.id
                        </b>
                    </span>
                    <span style="opacity:.55">
                        /verifikasi
                    </span>
                </div>
                <p class="mut" style="margin:0">
                    Nama merek hanya subdomain, domain utamanya berbeda.
                    <a href="{{ route('cek') }}" style="color:var(--blue);font-weight:700">
                        Lihat detail di Cek
                    </a>
                </p>
            </div>
            <div class="card" style="border:1.5px dashed var(--blue)">
                <div class="row" style="justify-content:space-between;align-items:center">
                    <h3 style="margin:0">
                        Analisis lebih dalam dengan AI
                    </h3>
                    <span class="tag t-violet">
                        Opsional
                    </span>
                </div>
                <p class="mut" style="margin:10px 0 12px">
                    Menangkap variasi pola yang lolos dari pemeriksaan dasar.
                </p>
                <div class="notice" style="margin-bottom:12px">
                    <b>
                        Teks akan dikirim ke layanan AI pihak ketiga.
                    </b>
                    Jangan tempel nomor, nama, atau data pribadi. Teks tidak kami simpan.
                </div>
                <button class="btn ghost block">
                    Kirim ke AI dan analisis
                </button>
                <p class="small" style="margin:10px 0 0">
                    Hasil AI tampil terpisah dan berlabel "Dianalisis AI, bisa salah".
                </p>
            </div>
            <div class="card dark">
                <h3>
                    Sudah terlanjur transfer atau klik?
                </h3>
                <p class="mut">
                    Ada langkah cepat yang bisa kamu lakukan sekarang.
                </p>
                <a class="btn block" href="{{ route('jam-emas') }}">
                    Lihat Rencana Jam Emas
                </a>
                <a class="btn ghost block" href="{{ route('simulasi') }}" style="margin-top:10px">
                    Latih skenario serupa
                </a>
            </div>
        </div>
    </div>
</main>

@endsection
