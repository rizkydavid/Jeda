@extends('layouts.app')

@section('title', 'Cek Tautan dan File')

@section('content')

<style>
    .cek-result { display:grid; gap:12px; }
    .cek-result[hidden] { display:none; }
    .cek-head { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .cek-host { font-weight:700; word-break:break-all; }
    .cek-list { margin:0; padding-left:20px; display:grid; gap:6px; }
    .cek-advice { background:var(--soft); border-radius:12px; padding:12px 14px; }
    .cek-advice ul { margin:6px 0 0; padding-left:18px; }
    .cek-error { border:1px solid var(--rose); background:var(--rose); border-radius:12px; padding:12px 14px; color:var(--ink); }
    .cek-drop { border:2px dashed var(--line); border-radius:16px; padding:18px; text-align:center; background:var(--surface-soft, #f7f9fc); }
    .tag.t-none { background:var(--soft); color:var(--ink); }
    :root[data-theme="dark"] .page-content .tag.t-none { background:#c7d0ee; color:#101522; }
    .cek-sr { position:absolute; width:1px; height:1px; opacity:0; overflow:hidden; }
    .cek-drop:focus-within, .cek-drop.is-over { border-color:var(--blue); background:var(--soft); }
    .btn[disabled] { opacity:.65; cursor:progress; transform:none; }
    @media (max-width:720px) { .cek-bar { flex-wrap:wrap !important; } }
</style>

<main class="wrap">
    <section class="hero" style="text-align:center">
        <div class="row" style="justify-content:center;margin-bottom:18px">
        </div>
        <h1>Cek tautan sebelum kamu klik.</h1>
        <p class="mut" style="max-width:620px;margin:0 auto 22px;font-size:18px">
            Jeda membaca pola alamat dan nama file untuk menunjukkan tanda bahayanya. Ini bukan vonis aman atau tidak.
        </p>
        <form id="linkForm" class="row cek-bar" style="justify-content:center;flex-wrap:nowrap;max-width:720px;margin:0 auto" novalidate>
            <input id="linkInput" name="tautan" type="text" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false"
                   aria-label="Tautan yang ingin diperiksa" class="jeda-field"
                   style="flex:1;min-width:0;border-radius:999px;padding:14px 20px"
                   placeholder="Tempel tautan yang ingin diperiksa...">
            <button type="submit" id="linkButton" class="btn" style="border-radius:999px;white-space:nowrap">Cek tautan</button>
        </form>
        <p class="small" style="margin-top:12px">
            Tautan tidak dibuka. Yang dicatat untuk ringkasan sesimu hanya nama domainnya. Jika pengecekan reputasi aktif, alamat tautan juga dicocokkan ke daftar Google Safe Browsing.
        </p>
    </section>

    <div class="grid2">
        <div class="card stack">
            <div class="row" style="justify-content:space-between;align-items:baseline">
                <h2 style="margin:0">Hasil pemeriksaan tautan</h2>
            </div>
            <p class="mut" id="linkEmpty" style="margin:0">
                Belum ada tautan yang diperiksa. Tempel tautan di kolom atas, lalu tekan "Cek tautan".
            </p>
            <div id="linkResult" class="cek-result" aria-live="polite" hidden></div>
            <a class="btn ghost block" href="{{ route('jam-emas') }}">Sudah terlanjur klik? Lihat langkahnya</a>
        </div>

        <div class="card stack">
            <div class="row" style="justify-content:space-between;align-items:baseline">
                <h2 style="margin:0">Penguji File</h2>
                <span class="small">File tidak diunggah</span>
            </div>
            <form id="fileForm" novalidate>
                <label for="filePicker" id="dropZone" class="cek-drop" style="display:block;cursor:pointer">
                    <b>Pilih file dari komputer</b>
                    <span class="small" style="display:block;margin:4px 0 12px">atau seret file ke sini. Isi file tidak diunggah; browser hanya membaca beberapa byte awal untuk mengenali jenis aslinya.</span>
                    <span class="btn block" style="pointer-events:none">Pilih file</span>
                    <input id="filePicker" type="file" class="cek-sr" aria-label="Pilih file dari komputer">
                </label>
                <p class="small" id="pickedInfo" style="margin:10px 0 0" hidden></p>
                <input id="fileNameCheck" type="hidden" value="">
            </form>
            <p class="mut" id="fileEmpty" style="margin:0">
                Belum ada file yang diperiksa. Pilih file dari komputer atau seret ke kotak di atas.
            </p>
            <div id="fileResult" class="cek-result" aria-live="polite" hidden></div>
        </div>
    </div>
</main>

<script src="{{ asset('js/cek.js') }}?v={{ @filemtime(public_path('js/cek.js')) }}" defer></script>

@endsection