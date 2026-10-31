@extends('layouts.app')

@section('title', 'Latihan Simulasi')

@section('content')

<main class="wrap">
    <div class="grid2">
        <div class="stack">
            <div class="notice">
                <b>
                    Simulasi aman.
                </b>
                Semua tokoh, nomor, dan nominal di sini fiktif.
            </div>
            <div class="bubble">
                <b>
                    Rekruter (simulasi)
                </b>
                Halo, kamu lolos seleksi awal. Besok pagi bayar Rp150.000 ya, biar slot kamu aman.
            </div>
            <div class="bubble me">
                Boleh lihat kontraknya dulu?
            </div>
            <div class="bubble">
                <b>
                    Rekruter (simulasi)
                </b>
                Slot tinggal 2 lagi. Jangan bilang teman-temanmu ya, nanti mereka ikut rebutan.
            </div>
        </div>
        <div class="stack">
            <div class="card">
                <div class="lvl">
                    <span>
                        Tekanan dari penipu simulasi
                    </span>
                    <em>
                        Sedang
                    </em>
                </div>
                <div class="meter">
                    <span class="f">
                    </span>
                    <span class="f">
                    </span>
                    <span class="f">
                    </span>
                    <span>
                    </span>
                    <span>
                    </span>
                </div>
                <p class="small" style="margin-top:10px">
                    Naik saat kamu ragu, turun saat kamu memverifikasi.
                </p>
            </div>
            <h2>
                Kamu mau balas apa?
            </h2>
            <a class="opt">
                A. Transfer sekarang biar tidak kehilangan slot
            </a>
            <a class="opt sel">
                B. Minta bukti legalitas perusahaan, lalu tanya teman dulu
                <br/>
                <span class="small" style="color:var(--blue)">
                    Pilihanmu
                </span>
            </a>
            <a class="opt">
                C. Abaikan pesannya dan blokir
            </a>
            <div class="card">
                <h3>
                    Umpan balik: pilihan yang kuat
                </h3>
                <p class="mut">
                    Kamu memverifikasi dan membuka pembicaraan ke orang lain, tepat melawan taktik kerahasiaan dan kelangkaan.
                </p>
                <div class="row">
                    <a class="btn" href="{{ route('simulasi') }}">
                        Skenario berikutnya
                    </a>
                    <a class="btn ghost" href="{{ route('kartu') }}">
                        Lihat Kartu Kerentanan
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

@endsection
