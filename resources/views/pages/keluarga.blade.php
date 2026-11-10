@extends('layouts.app')

@section('title', 'Lindungi Keluarga')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/keluarga.css') }}?v={{ @filemtime(public_path('css/keluarga.css')) }}">
@endpush

@section('content')
<div class="wrap kel" id="keluarga"
     data-checklist-url="{{ route('jeda.api.checklist') }}"
     data-aksi-url="{{ route('jeda.api.support-action') }}"
     data-bagikan-url="{{ route('bagikan') }}">

    <section class="hero">
        <h1>Lindungi keluarga.</h1>
        <p class="mut" style="max-width:640px;font-size:18px">
            Kamu guardian di rumahmu. Pilih siapa yang mau dilindungi, lalu Jeda menyiapkan langkah, pesan,
            dan alat yang tetap bekerja saat kamu tidak ada di sebelah mereka.
        </p>
    </section>

    <h2>Siapa yang mau kamu lindungi?</h2>
    <div class="row kel-orang" id="orang" role="group" aria-label="Pilih orang yang mau dilindungi">
        <button type="button" class="chip on" data-orang="ortu" aria-pressed="true">Orang tua</button>
        <button type="button" class="chip" data-orang="adik" aria-pressed="false">Adik</button>
        <button type="button" class="chip" data-orang="kakek" aria-pressed="false">Kakek dan nenek</button>
        <button type="button" class="chip" data-orang="teman" aria-pressed="false">Teman</button>
    </div>
    <p class="small" id="ringkasProgres" aria-live="polite" style="margin:10px 0 24px"></p>

    {{-- ===== Bagian 1: khusus orang yang dipilih ===== --}}
    <div class="kel-grid">
        <section class="card" aria-labelledby="judulChecklist">
            <h2 id="judulChecklist" style="margin-top:0">Checklist perlindungan</h2>
            <p class="small" id="progresTeks" style="margin-bottom:8px"></p>
            <div class="kel-bar" id="progresWrap" role="progressbar" aria-labelledby="judulChecklist"
                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="progresBar"></span></div>
            <ul class="kel-list" id="daftar"></ul>
        </section>

        <section class="card" aria-labelledby="judulPesan">
            <h2 id="judulPesan" style="margin-top:0">Pesan peringatan siap kirim</h2>
            <p class="small" style="margin-bottom:8px">Pilih modus yang mau kamu ingatkan:</p>
            <div class="row kel-modus" id="modus" role="group" aria-label="Pilih modus penipuan"></div>

            <label class="small kel-label" for="sapaan">Panggil dia dengan</label>
            <input class="kel-input" id="sapaan" type="text" maxlength="20" autocomplete="off">

            <p class="small" style="margin:16px 0 8px">Pratinjau pesan WhatsApp</p>
            <div class="bubble me kel-bubble"><b>Dari kamu</b><span id="isiPesan"></span></div>

            <div class="kel-bawah">
                <button type="button" class="btn block" id="bagikanPesan">Bagikan ke WhatsApp</button>
                <button type="button" class="btn ghost block" id="salinTeks">Salin teks</button>
            </div>
        </section>

        <section class="card" aria-labelledby="judulSandi">
            <h2 id="judulSandi" style="margin-top:0">Kata sandi keluarga</h2>
            <p class="mut" style="margin-top:0">
                Satu kata rahasia yang hanya diketahui keluarga. Kalau ada yang menelepon atau chat sambil
                mengaku keluarga dan minta uang, minta kata sandinya dulu. Kalau tidak tahu, berhenti dan
                telepon nomor lamanya.
            </p>
            <div class="row" id="modeSandi" role="group" aria-label="Cara membuat kata sandi" style="margin-bottom:12px">
                <button type="button" class="chip on" data-mode="acak" aria-pressed="true">Acak 3 kata</button>
                <button type="button" class="chip" data-mode="sendiri" aria-pressed="false">Tulis sendiri</button>
            </div>
            <div class="bubble me kel-sandi"><b id="kataSandi" aria-live="polite"></b></div>
            <div class="kel-sandi-edit" id="sandiSendiriWrap" hidden>
                <label class="small kel-label" for="sandiSendiri" style="margin-top:0">Kata sandi buatanmu</label>
                <input class="kel-input" id="sandiSendiri" type="text" maxlength="40" autocomplete="off" spellcheck="false">
            </div>
            <div class="kel-bawah">
                <button type="button" class="btn ghost block" id="acakLagi">Acak lagi</button>
                <p class="small kel-catatan">
                    Sepakati langsung saat bertemu atau telepon, jangan lewat chat. Jeda tidak menyimpan kata ini:
                    kata hilang kalau halaman dimuat ulang, jadi tulis di kertas dan simpan di rumah.
                </p>
            </div>
        </section>

        <section class="card" aria-labelledby="judulTerlanjur">
            <h2 id="judulTerlanjur" style="margin-top:0">Kalau sudah terlanjur</h2>
            <p class="mut" style="margin-top:0">Tanpa menghakimi. Yang penting sekarang bergerak cepat, bukan mencari siapa yang salah.</p>
            <ol class="kel-steps">
                <li><b>Tenang dulu.</b> Ini bukan salahnya. Penipu memang dirancang untuk mengecoh orang yang cerdas sekalipun.</li>
                <li><b>Hubungi bank atau e-wallet lewat kontak resmi</b> di aplikasi atau di belakang kartu, minta transaksi ditahan atau diblokir. Makin cepat, makin besar peluangnya.</li>
                <li><b>Simpan bukti:</b> tangkapan layar percakapan, bukti transfer, nomor dan nama rekening tujuan.</li>
                <li><b>Amankan akun:</b> ganti kata sandi dari perangkat yang aman, keluar dari sesi lain, dan aktifkan verifikasi dua langkah.</li>
                <li>
                    <b>Laporkan.</b> Untuk penipuan transaksi keuangan, telepon <a href="tel:157">157</a>
                    (Kontak OJK, layanan Indonesia Anti-Scam Centre) atau WhatsApp
                    <a href="https://wa.me/6281157157157" target="_blank" rel="noopener">081 157 157 157</a>.
                    Untuk memeriksa atau melaporkan rekening penipu, pakai
                    <a href="https://cekrekening.id" target="_blank" rel="noopener">cekrekening.id</a> (Komdigi).
                    Pakai kontak resmi ini saja: ada situs palsu yang mengatasnamakan layanan anti-scam.
                </li>
            </ol>
            <p class="small kel-catatan">Kontak diperiksa dari situs dan siaran pers resmi serta pemberitaan, per November 2026. Kalau ragu, cek ulang lewat situs resmi OJK.</p>
            <div class="kel-bawah">
                <a class="btn block" href="{{ route('jam-emas') }}">Buka Rencana Jam Emas</a>
            </div>
        </section>
    </div>

    {{-- ===== Bagian 2: perlindungan saat kamu tidak ada ===== --}}
    <h2 class="kel-bagian">Lindungi saat kamu tidak ada di sebelah mereka</h2>
    <div class="kel-grid">
        <section class="card" aria-labelledby="judulPasang">
            <h2 id="judulPasang" style="margin-top:0">Jeda di HP orang tuamu</h2>
            <p class="mut" style="margin-top:0">
                Pesan peringatan mudah tenggelam. Dengan Jeda terpasang di HP Android mereka, saat ragu mereka
                tinggal membagikan pesannya ke Jeda, dapat jawaban yang sederhana, lalu mengirimnya ke kamu.
            </p>

            <label class="small kel-label" for="gNama">Nama kamu di HP mereka</label>
            <input class="kel-input" id="gNama" type="text" maxlength="24" autocomplete="off" placeholder="Contoh: Bima">

            <label class="small kel-label" for="gWa">Nomor WhatsApp kamu</label>
            <input class="kel-input" id="gWa" type="tel" inputmode="tel" maxlength="20" autocomplete="off" placeholder="Contoh: 08123456789">
            <p class="small kel-error" id="gError" role="alert" hidden></p>

            <button type="button" class="btn block" id="buatLink" style="margin-top:14px">Buat link untuk ortu</button>

            <div id="hasilLink" hidden style="margin-top:14px">
                <label class="small kel-label" for="linkOrtu" style="margin-top:0">Link untuk dibuka di HP mereka</label>
                <input class="kel-input" id="linkOrtu" type="text" readonly>
                <div style="margin-top:12px">
                    <button type="button" class="btn block" id="bagikanLink">Bagikan link ke ortu</button>
                    <button type="button" class="btn ghost block" id="salinLink">Salin link</button>
                </div>
            </div>

            <ol class="kel-langkah small">
                <li>Buka link itu di <b>Chrome</b> di HP mereka. Kalau terbuka di dalam WhatsApp, pilih menu untuk membukanya di browser.</li>
                <li>Ketuk <b>Pasang Jeda</b> (atau titik tiga lalu <b>Pasang aplikasi</b>), jadi Jeda muncul di layar utama.</li>
                <li>Coba bersama: di WhatsApp, tahan satu pesan, pilih <b>Bagikan</b>, lalu <b>Jeda</b>.</li>
            </ol>
            <p class="small kel-catatan">
                Menu Bagikan hanya ada di Android. Di iPhone, mereka tetap bisa membuka Jeda dan menempel pesannya.
                Nama dan nomormu ditaruh di bagian # pada link, jadi tidak dikirim ke server Jeda, dan hanya disimpan di HP mereka.
            </p>
            <div class="kel-bawah">
                <a class="btn ghost block" href="{{ route('bagikan') }}">Coba halamannya dulu</a>
            </div>
        </section>

        <section class="card" aria-labelledby="judulKartu">
            <h2 id="judulKartu" style="margin-top:0">Kartu tempel di dekat telepon</h2>
            <p class="mut" style="margin-top:0">
                Buat yang jarang membuka WhatsApp, kartu di kulkas atau di samping telepon lebih mudah diingat.
                Nama dan nomor diambil dari isian di sebelah.
            </p>
            <label class="small kel-label" for="gBank" style="margin-top:0">Nomor resmi bank mereka (opsional, maksimal 3 baris)</label>
            <textarea class="kel-input" id="gBank" rows="3" maxlength="240"
                      placeholder="Contoh: Nama bank: nomor layanan&#10;(ambil dari belakang kartu ATM atau aplikasi bank)"></textarea>

            <div class="kel-kartu" id="kartu" aria-label="Pratinjau kartu tempel">
                <p class="kel-kartu__judul">BERHENTI DULU.</p>
                <p class="kel-kartu__sub">Ada yang minta uang, kode OTP, atau PIN?</p>
                <ol>
                    <li>Telepon <b id="kartuNama">(nama)</b> dulu: <b id="kartuWa">(nomor)</b></li>
                    <li>Jangan pernah memberi kode OTP atau PIN ke siapa pun.</li>
                    <li>Jangan buka link atau file yang tidak dikenal, terutama file berakhiran .apk.</li>
                </ol>
                <div id="kartuBankWrap" hidden>
                    <p class="kel-kartu__h">Nomor resmi bank:</p>
                    <ul id="kartuBank"></ul>
                </div>
                <p class="kel-kartu__kaki">Sudah terlanjur? Telepon bank sekarang, lalu hubungi Kontak OJK 157.</p>
            </div>

            <div class="kel-bawah">
                <button type="button" class="btn block" id="cetakKartu">Cetak kartu</button>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/keluarga.js') }}?v={{ @filemtime(public_path('js/keluarga.js')) }}" defer></script>
@endpush