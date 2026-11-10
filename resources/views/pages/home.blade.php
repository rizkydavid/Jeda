@extends('layouts.app')

@section('title', 'Bedah Pesan')

@section('content')

<main class="wrap" id="bedahRoot"
      data-ai-url="{{ route('jeda.api.bedah-ai') }}"
      data-log-url="{{ route('jeda.api.analyze-message') }}"
      data-csrf="{{ csrf_token() }}">

    <section class="hero">
        <h1>Ragu? Jeda dulu.</h1>
        <p class="mut" style="max-width:640px;font-size:18px">
            Tempel pesan atau tautan yang bikin ragu. Jeda menunjukkan tanda tekanannya, bukan memberi vonis.
        </p>
    </section>

    <div class="grid2">

        <div class="stack">

            <div class="card">
                <h2>Pesan atau tautan yang bikin ragu</h2>
                <textarea id="bedahInput" aria-label="Pesan" maxlength="12000" style="min-height:180px" placeholder="Tempel pesan WhatsApp, SMS, atau DM yang bikin kamu ragu di sini..."></textarea>

                <div class="notice" style="margin:12px 0">
                    <b>Analisis memakai AI pihak ketiga.</b>
                    Teks dikirim ke layanan AI untuk mendeteksi pola. Nomor HP, rekening, dan email disamarkan otomatis, dan teks tidak kami simpan.
                    Jangan tempel nama atau data pribadi.
                </div>
                <label class="small" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:12px">
                    <input type="checkbox" id="bedahAiToggle" checked style="margin-top:3px">
                    <span>Kirim ke AI untuk analisis. Kalau dimatikan, hanya pemeriksaan dasar di perangkatmu yang jalan dan tidak ada teks yang dikirim.</span>
                </label>

                <div class="row">
                    <button class="btn" id="bedahRun" type="button">Bedah pesan</button>
                </div>
                <p class="small" id="bedahAiOut" aria-live="polite" style="margin:12px 0 0"></p>
            </div>

        </div>

        <div class="stack">

            <div class="card">
                <div class="lvl">
                    <span>Tingkat tekanan</span>
                    <em id="bedahLevel">Belum diperiksa</em>
                </div>
                <div class="meter" id="bedahMeter">
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <p class="small" id="bedahSummary" style="margin:10px 0 0">
                    Tempel pesan lalu tekan "Bedah pesan". Hasilnya muncul di sini, lengkap dengan alasan dan langkah aman.
                </p>
            </div>

            <div class="stack" id="bedahFlags" aria-live="polite"></div>
            <div id="bedahSteps"></div>

            <div class="card dark">
                <h3>Sudah terlanjur transfer atau klik?</h3>
                <p class="mut">Ada langkah cepat yang bisa kamu lakukan sekarang.</p>
                <a class="btn block" href="{{ route('jam-emas') }}">Lihat Rencana Jam Emas</a>
                <a class="btn ghost block" href="{{ route('simulasi') }}" style="margin-top:10px">Latih skenario serupa</a>
            </div>

        </div>
    </div>
</main>

<script src="{{ asset('js/bedah.js') }}?v={{ @filemtime(public_path('js/bedah.js')) }}" defer></script>

@endsection