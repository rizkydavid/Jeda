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
            <input aria-label="URL" class="jeda-field" style="flex:1;min-width:0;border-radius:999px;padding:14px 20px" placeholder="Tempel tautan yang ingin diperiksa..."/>
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
            </div>
            <p class="mut" style="margin:0">
                Belum ada tautan yang diperiksa. Tempel tautan di kolom atas, lalu tekan "Periksa tautan".
            </p>
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
            <p class="mut" style="margin:0">
                Belum ada nama file yang diperiksa. Masukkan nama file di kolom yang tersedia, lalu tekan "Periksa nama file".
            </p>
        </div>
    </div>
</main>

@endsection
