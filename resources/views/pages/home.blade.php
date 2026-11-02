@extends('layouts.app')

@section('title', 'Bedah Pesan')

@section('content')

<main class="wrap">

    {{-- Hero --}}
    <section class="hero">
        <h1>Ragu? Jeda dulu.</h1>
        <p class="mut" style="max-width:640px;font-size:18px">
            Tempel pesan atau tautan yang bikin ragu. Jeda menunjukkan tanda tekanannya, bukan memberi vonis.
        </p>
    </section>

    <div class="grid2">

        {{-- KOLOM KIRI: input dan analisis AI --}}
        <div class="stack">

            <div class="card">
                <h2>Pesan atau tautan yang bikin ragu</h2>
                <textarea aria-label="Pesan" placeholder="Tempel pesan yang ingin diperiksa..." style="min-height:180px"></textarea>
                <div class="row">
                    <button class="btn block">Bedah pesan</button>
                </div>
                <p class="small" style="margin-top:12px">
                    Pemeriksaan dasar berjalan di perangkatmu. Tidak ada teks yang dikirim ke mana pun kecuali kamu memilih analisis AI.
                </p>
            </div>

            <div class="card" style="border:1.5px dashed var(--blue)">
                <div class="row" style="justify-content:space-between;align-items:center">
                    <h3 style="margin:0">Analisis lebih dalam dengan AI</h3>
                    <span class="tag t-violet">Opsional</span>
                </div>
                <p class="mut" style="margin:10px 0 12px">
                    Menangkap variasi pola yang lolos dari pemeriksaan dasar.
                </p>
                <div class="notice" style="margin-bottom:12px">
                    <b>Teks akan dikirim ke layanan AI pihak ketiga.</b>
                    Jangan tempel nomor, nama, atau data pribadi. Teks tidak kami simpan.
                </div>
                <button class="btn ghost block">Kirim ke AI dan analisis</button>
                <p class="small" style="margin:10px 0 0">
                    Hasil AI tampil terpisah dan berlabel "Dianalisis AI, bisa salah".
                </p>
            </div>

        </div>

        {{-- KOLOM KANAN: hasil dan langkah lanjut --}}
        <div class="stack">

            <div class="card">
                <div class="lvl">
                    <span>Tingkat tekanan</span>
                    <em>Belum diperiksa</em>
                </div>
                <div class="meter">
                    <span></span><span></span><span></span><span></span><span></span>
                </div>
                <p class="small" style="margin:10px 0 0">
                    Hasil pemeriksaan akan muncul setelah kamu menekan tombol Bedah pesan.
                </p>
            </div>

            <div class="card dark">
                <h3>Sudah terlanjur transfer atau klik?</h3>
                <p class="mut">Ada langkah cepat yang bisa kamu lakukan sekarang.</p>
                <a class="btn block" href="{{ route('jam-emas') }}">Lihat Rencana Jam Emas</a>
                <a class="btn ghost block" href="{{ route('simulasi') }}" style="margin-top:10px">Latih skenario serupa</a>
            </div>

        </div>
    </div>
</main>

@endsection