@extends('layouts.app')

@section('title', 'Cek Tautan dan File')

@section('content')

<main class="wrap">
    <section class="hero" style="text-align:center">
        <nav class="row cek-tabs" aria-label="Pilih jenis pemeriksaan" style="justify-content:center;margin-bottom:18px">
            <a class="chip on" href="#cek-tautan" data-cek-target="cek-tautan" aria-current="true">
                Penguji Tautan
            </a>
            <a class="chip" href="#cek-file" data-cek-target="cek-file">
                Penguji File
            </a>
        </nav>
        <h1>
            Cek tautan sebelum kamu klik.
        </h1>
        <p class="mut" style="max-width:620px;margin:0 auto 22px;font-size:18px">
            Jeda menggabungkan pola alamat, umur domain, daftar situs berbahaya, dan tujuan akhir tautan untuk menunjukkan tanda bahayanya. Ini bukan vonis aman atau tidak.
        </p>
        <div class="row cek-linkrow" id="cek-tautan">
            <input id="linkInput" type="url" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="URL" class="jeda-field" placeholder="Tempel tautan yang ingin diperiksa..."/>
            <button id="linkButton" type="button" class="btn">
                Periksa tautan
            </button>
        </div>
        <label class="small" style="display:flex;gap:8px;align-items:flex-start;justify-content:center;margin:12px auto 0;max-width:620px;text-align:left">
            <input id="vtSubmit" type="checkbox" style="margin-top:3px"/>
            <span>Kirim alamat ini ke VirusTotal untuk dianalisis jika belum pernah diperiksa. Alamat bisa terlihat oleh komunitas keamanan VirusTotal.</span>
        </label>
    </section>
    <div class="grid2">
        <div class="card stack" id="hasil-tautan">
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
        <div class="card stack" id="cek-file">
            <div class="row" style="justify-content:space-between;align-items:baseline">
                <h2 style="margin:0">
                    Penguji File
                </h2>
                <span class="small">
                    File tidak diunggah, kecuali kamu izinkan
                </span>
            </div>
            @php
                $fmtSize = fn (int $b) => $b >= 1073741824
                    ? rtrim(rtrim(number_format($b / 1073741824, 1, ',', ''), '0'), ',').' GB'
                    : (int) ($b / 1048576).' MB';
            @endphp
            <label for="fileInput" id="fileDrop" class="cek-drop" tabindex="0">
                <b>Pilih file atau tarik ke sini</b>
                <span class="small">APK, PDF, dokumen, arsip, gambar, apa saja. Maksimal {{ $fmtSize((int) config('jeda_cek.max_large_file_bytes')) }}. File di atas {{ $fmtSize((int) config('jeda_cek.max_file_bytes')) }} dibaca sebagian.</span>
            </label>
            <input id="fileInput" type="file" class="cek-visually-hidden"/>
            <div id="fileResult" aria-live="polite">
                <p class="mut" style="margin:0">
                    Belum ada file yang diperiksa. Pilih file di atas.
                </p>
            </div>
        </div>
    </div>
</main>

@php
    $cekConfig = [
        'maxBytes' => (int) config('jeda_cek.max_file_bytes'),
        'maxLargeBytes' => (int) config('jeda_cek.max_large_file_bytes'),
        'executable' => config('jeda_cek.executable_extensions'),
        'decoy' => config('jeda_cek.decoy_extensions'),
        'apkKnown' => array_keys(config('jeda_cek.apk_permissions', [])),
        'uploadMaxBytes' => \App\Services\Cek\UploadLimit::bytes(),
        'uploadEnabled' => (bool) config('services.virustotal.key'),
    ];
@endphp

<script type="application/json" id="cekConfig">{!! json_encode($cekConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script><script src="{{ asset('js/file-inspect.js') }}" defer></script>
<script src="{{ asset('js/cek.js') }}" defer></script>

@endsection
