@extends('layouts.app')

@section('title', 'Kartu Kerentanan')

@section('content')

<main class="wrap">
    <h1 style="font-size:40px">
        Kartu Kerentanan
    </h1>
    <p class="mut">
        Gambaran taktik mana yang paling mudah membuatmu goyah. Tersimpan hanya di perangkatmu. Tampilan ini memakai data contoh.
    </p>
    <div class="grid2" style="margin-top:20px">
        <div class="stack">
            <div class="card dark">
                <p class="small" style="color:#b8c2d6;margin:0">
                    Paling mudah membuatmu goyah
                </p>
                <h1 style="font-size:44px;margin:6px 0">
                    Otoritas
                </h1>
                <p class="mut" style="margin:0">
                    Kamu cenderung menurut saat pesan datang dari pihak yang mengaku bank, petugas, atau atasan.
                </p>
            </div>
            <div class="card" style="background:var(--soft);border-color:var(--soft)">
                <h3 style="color:var(--blue)">
                    Kalimat pengingat untukmu
                </h3>
                <p style="margin:0">
                    Orang yang benar-benar resmi tidak keberatan kamu verifikasi lewat nomor di situs resminya.
                </p>
            </div>
        </div>
        <div class="stack">
            <div class="card stack">
                <h2>
                    Seberapa sering kamu goyah
                </h2>
                <div>
                    <div class="lvl">
                        <span>
                            Otoritas
                        </span>
                        <em>
                            Tinggi, 4 dari 5
                        </em>
                    </div>
                    <div class="bar">
                        <span style="width:80%">
                        </span>
                    </div>
                </div>
                <div>
                    <div class="lvl">
                        <span>
                            Urgensi
                        </span>
                        <em>
                            Sedang, 2 dari 5
                        </em>
                    </div>
                    <div class="bar">
                        <span style="width:40%">
                        </span>
                    </div>
                </div>
                <div>
                    <div class="lvl">
                        <span>
                            Kerahasiaan
                        </span>
                        <em>
                            Rendah, 1 dari 5
                        </em>
                    </div>
                    <div class="bar">
                        <span style="width:20%">
                        </span>
                    </div>
                </div>
                <div>
                    <div class="lvl">
                        <span>
                            Iming-iming
                        </span>
                        <em>
                            Rendah, 1 dari 5
                        </em>
                    </div>
                    <div class="bar">
                        <span style="width:20%">
                        </span>
                    </div>
                </div>
                <div>
                    <div class="lvl">
                        <span>
                            Kelangkaan
                        </span>
                        <em>
                            Rendah, 0 dari 5
                        </em>
                    </div>
                    <div class="bar">
                        <span style="width:0%">
                        </span>
                    </div>
                </div>
                <p class="small">
                    Dari 5 latihan terakhir.
                </p>
            </div>
            <a class="btn block" href="{{ route('simulasi') }}">
                Latih taktik otoritas lagi
            </a>
            <a class="btn ghost block" href="javascript:void(0)">
                Kirim tantangan ke teman
            </a>
        </div>
    </div>
</main>

@endsection
