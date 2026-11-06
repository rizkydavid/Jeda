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
            Jeda menggabungkan pola alamat, umur domain, daftar situs berbahaya, dan tujuan akhir tautan untuk menunjukkan tanda bahayanya. Ini bukan vonis aman atau tidak.
        </p>
        <div class="row" style="justify-content:center;flex-wrap:nowrap;max-width:720px;margin:0 auto">
            <input id="linkInput" aria-label="URL" class="jeda-field" style="flex:1;min-width:0;border-radius:999px;padding:14px 20px" placeholder="Tempel tautan yang ingin diperiksa..."/>
            <button id="linkButton" type="button" class="btn" style="border-radius:999px;white-space:nowrap">
                Periksa tautan
            </button>
        </div>
        <label class="small" style="display:flex;gap:8px;align-items:flex-start;justify-content:center;margin:12px auto 0;max-width:620px;text-align:left">
            <input id="vtSubmit" type="checkbox" style="margin-top:3px"/>
            <span>Kirim alamat ini ke VirusTotal untuk dianalisis jika belum pernah diperiksa. Alamat bisa terlihat oleh komunitas keamanan VirusTotal.</span>
        </label>
        <p class="small" style="margin:12px auto 0;max-width:680px">
            Yang terjadi saat kamu menekan "Periksa tautan": server Jeda membuka tautan untuk melihat tujuan akhirnya (tanpa mengisi atau mengirim form apa pun), mengirim alamat tanpa parameter (bagian setelah tanda ?) ke Google Safe Browsing, mengirim nama domain ke layanan RDAP, dan mencari hash alamat di VirusTotal. Alamat lengkap tidak disimpan. Penjelasan AI hanya dibuat jika kamu menekan tombolnya, dan hanya menerima ringkasan tanda (tanpa alamat tautan).
        </p>
    </section>
    <div class="grid2">
        <div class="card stack">
            <div class="row" style="justify-content:space-between;align-items:baseline">
                <h2 style="margin:0">
                    Hasil pemeriksaan tautan
                </h2>
            </div>
            <div id="linkResult" aria-live="polite">
                <p class="mut" style="margin:0">
                    Belum ada tautan yang diperiksa. Tempel tautan di kolom atas, lalu tekan "Periksa tautan".
                </p>
            </div>
            <a class="btn ghost block" href="{{ route('jam-emas') }}">
                Sudah terlanjur klik? Lihat langkahnya
            </a>
        </div>
        <div class="card stack" id="fileCard">
            <div class="row" style="justify-content:space-between;align-items:baseline">
                <h2 style="margin:0">
                    Penguji File
                </h2>
                <span class="small">
                    File tidak diunggah
                </span>
            </div>
            <label for="fileInput" id="fileDrop" class="cek-drop" tabindex="0">
                <b>Pilih file atau tarik ke sini</b>
                <span class="small">APK, PDF, dokumen, arsip, gambar, apa saja. Maksimal {{ (int) (config('jeda_cek.max_file_bytes') / 1048576) }} MB.</span>
            </label>
            <input id="fileInput" type="file" class="cek-visually-hidden"/>
            <div class="row" style="flex-wrap:nowrap;gap:8px">
                <input id="fileNameInput" aria-label="Nama file" class="jeda-field" style="flex:1;min-width:0" maxlength="255" placeholder="atau tulis nama file saja, mis. undangan.apk"/>
                <button id="fileNameButton" type="button" class="btn ghost" style="white-space:nowrap">Periksa nama file</button>
            </div>
            <p class="small" style="margin:0">
                Saat kamu memilih file, browser membaca isinya di perangkatmu: jenis asli file, tanda berbahaya di dalamnya, dan sidik jari SHA-256. Yang dikirim ke server hanya nama file, ukuran, ringkasan temuan, dan SHA-256 (untuk dicocokkan ke VirusTotal). Isi file tidak pernah dikirim dan nama file tidak disimpan.
            </p>
            <div id="fileResult" aria-live="polite">
                <p class="mut" style="margin:0">
                    Belum ada file yang diperiksa. Pilih file di atas, atau tulis namanya lalu tekan "Periksa nama file".
                </p>
            </div>
        </div>
    </div>
</main>

<script type="application/json" id="cekConfig">@json(['maxBytes' => (int) config('jeda_cek.max_file_bytes'), 'executable' => config('jeda_cek.executable_extensions'), 'decoy' => config('jeda_cek.decoy_extensions')])</script>
<script src="{{ asset('js/file-inspect.js') }}" defer></script>
<script src="{{ asset('js/cek.js') }}" defer></script>

@endsection
