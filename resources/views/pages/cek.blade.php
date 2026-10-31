@extends('layouts.app')

@section('title', 'Cek Tautan dan File')

@section('content')

<main class="wrap">
    <section class="hero" style="text-align:center">
        <div class="row" style="justify-content:center;margin-bottom:18px">
            <span class="chip on">
                Penguji Tautan
            </span>
            <span class="chip">
                Penguji File
            </span>
        </div>
        <h1>
            Cek tautan sebelum kamu klik.
        </h1>
        <p class="mut" style="max-width:620px;margin:0 auto 22px;font-size:18px">
            Jeda membaca pola alamat dan nama file untuk menunjukkan tanda bahayanya. Ini bukan vonis aman atau tidak.
        </p>
        <div class="row" style="justify-content:center;flex-wrap:nowrap;max-width:720px;margin:0 auto">
            <input aria-label="URL" class="jeda-field" style="flex:1;min-width:0;border-radius:999px;padding:14px 20px" value="https://bankcontoh.com.login-verif.co.id/verifikasi"/>
            <button class="btn" style="border-radius:999px;white-space:nowrap">
                Periksa tautan
            </button>
        </div>
        <p class="small" style="margin-top:12px">
            Tautan tidak dibuka dan tidak dikirim ke mana pun. Contoh ini fiktif.
        </p>
    </section>
    <div class="grid2">
        <div class="card stack">
            <div class="row" style="justify-content:space-between;align-items:baseline">
                <h2 style="margin:0">
                    Hasil pemeriksaan tautan
                </h2>
                <span class="small">
                    2 tanda bahaya
                </span>
            </div>
            <div class="q" style="font-size:14px;word-break:break-all;margin:0">
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
            <div class="flag">
                <span class="tag t-rose">
                    Menyamar sebagai merek
                </span>
                <p class="mut" style="margin:8px 0 0">
                    Nama "bankcontoh" hanya subdomain. Domain utamanya login-verif.co.id.
                </p>
            </div>
            <div class="flag">
                <span class="tag t-amber">
                    Kata pancingan
                </span>
                <p class="mut" style="margin:8px 0 0">
                    Ada kata "login" dan "verifikasi" di alamat.
                </p>
            </div>
            <div class="q">
                <b>
                    Langkah aman:
                </b>
                Buka situs resminya sendiri, jangan lewat tautan ini.
            </div>
            <a class="btn ghost block" href="{{ route('jam-emas') }}">
                Sudah terlanjur klik? Lihat langkahnya
            </a>
        </div>
        <div class="card stack">
            <div class="row" style="justify-content:space-between;align-items:baseline">
                <h2 style="margin:0">
                    Penguji File
                </h2>
                <span class="small">
                    Hanya nama file yang dibaca
                </span>
            </div>
            <div style="border:2px dashed var(--line);border-radius:16px;padding:22px;text-align:center;background:#f7f9fc">
                <b>
                    Tulis atau tempel nama file
                </b>
                <p class="small" style="margin:4px 0 0">
                    Isi file tidak dibuka dan tidak diunggah.
                </p>
            </div>
            <div class="q" style="margin:0">
                <b>
                    Undangan_Pernikahan.pdf.apk
                </b>
            </div>
            <div class="flag">
                <span class="tag t-rose">
                    Ekstensi ganda
                </span>
                <p class="mut" style="margin:8px 0 0">
                    Kelihatan seperti PDF, tapi jenis aslinya APK.
                </p>
            </div>
            <div class="flag">
                <span class="tag t-amber">
                    File yang memasang aplikasi
                </span>
                <p class="mut" style="margin:8px 0 0">
                    APK bisa memasang aplikasi dan meminta akses ke ponselmu.
                </p>
            </div>
            <div class="q">
                <b>
                    Langkah aman:
                </b>
                Jangan buka. Tanya pengirimnya lewat telepon langsung.
            </div>
        </div>
    </div>
</main>

@endsection
