@extends('layouts.app')

@section('title', 'Periksa pesan')

@push('styles')
    {{-- Halaman ini sering dibuka lewat menu Bagikan dan harus langsung siap, jadi animasi intro 6,5 detik di layout dilewati. --}}
    <script>try { sessionStorage.setItem('jeda-intro-seen', '1'); } catch (e) {}</script>
    <meta name="theme-color" content="#2944e8">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/bagikan.css') }}?v={{ @filemtime(public_path('css/bagikan.css')) }}">
@endpush

@section('content')
<div class="wrap bg" id="bagikan"
     data-sw-url="{{ asset('sw.js') }}"
     data-cek-url="{{ route('jeda.api.check-link') }}">

    <section class="hero">
        <h1>Ragu sama pesan ini?</h1>
        <p class="mut">
            Tempel pesan atau link yang mencurigakan di bawah. Jeda memeriksanya dulu, sebelum dibalas atau diklik.
        </p>
    </section>

    {{-- Konfirmasi kontak yang datang lewat link pemasangan. Disimpan hanya kalau pemilik HP menyetujui. --}}
    <section class="card bg-konfirmasi" id="bgKonfirmasi" role="alertdialog" aria-labelledby="bgKonfirmasiJudul" hidden>
        <h2 id="bgKonfirmasiJudul" style="margin-top:0;font-size:22px">Simpan sebagai kontak keluarga?</h2>
        <p style="margin:0">Setelah diperiksa, hasil bisa dikirim langsung ke:</p>
        <p class="bg-nomor"><span id="bgKonfirmasiNama"></span> · <span id="bgKonfirmasiWa"></span></p>
        <p class="small" style="margin:0 0 14px">Pastikan ini benar nomor anak atau keluarga sendiri. Kalau tidak kenal, pilih "Bukan".</p>
        <button type="button" class="btn" id="bgKonfirmasiYa">Ya, ini benar</button>
        <button type="button" class="btn ghost" id="bgKonfirmasiTidak">Bukan</button>
    </section>

    {{-- ===== Isian ===== --}}
    <section class="card" id="bgIsian" aria-labelledby="bgIsianJudul">
        <label class="bg-label" id="bgIsianJudul" for="bgInput">Pesan atau link yang mencurigakan</label>
        <textarea class="bg-input" id="bgInput" maxlength="5000" autocomplete="off" spellcheck="false"
                  placeholder="Tempel di sini"></textarea>
        <div class="bg-aksi">
            <button type="button" class="btn" id="bgPeriksa">Periksa sekarang</button>
            <button type="button" class="btn ghost" id="bgTempel">Tempel dari salinan</button>
        </div>
        <p class="bg-catatan">Isi pesan diperiksa di HP ini dan tidak dikirim ke server Jeda.</p>
    </section>

    {{-- ===== Hasil ===== --}}
    <section class="bg-hasil" id="bgHasil" aria-live="polite" hidden>
        <div class="bg-banner is-none" id="bgBanner" tabindex="-1">
            <span class="bg-ikon" id="bgIkon" aria-hidden="true">?</span>
            <div>
                <p class="bg-judul" id="bgJudul"></p>
                <p class="bg-ringkas" id="bgRingkas"></p>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0">Kenapa?</h3>
            <ul id="bgAlasan"></ul>

            <h3>Yang sebaiknya dilakukan</h3>
            <ol id="bgLangkah"></ol>

            <div id="bgTautanWrap" hidden>
                <h3>Ada link di pesan ini</h3>
                <p class="small" style="margin:0 0 8px">
                    Pemeriksaan di atas hanya membaca bentuk link. Untuk pemeriksaan lebih dalam, alamat link
                    (bukan isi pesan) dikirim ke server Jeda untuk dicek.
                </p>
                <div class="bg-tautan-daftar" id="bgTautanDaftar"></div>
                <div class="bg-tautan-hasil" id="bgTautanHasil" aria-live="polite"></div>
            </div>
        </div>

        <div class="card">
            <a class="btn" id="bgKirim" href="https://wa.me/" target="_blank" rel="noopener">Tanya keluarga lewat WA</a>
            <button type="button" class="btn ghost" id="bgUlang">Periksa pesan lain</button>
            <p class="bg-catatan">
                Hasil ini hanya bantuan, bukan jaminan. Pesan yang tidak terdeteksi bukan berarti aman:
                kalau menyangkut uang, kode OTP, atau data pribadi, tetap tanya keluarga dulu.
            </p>
        </div>
    </section>

    {{-- ===== Kontak keluarga ===== --}}
    <details class="bg-pengaturan" id="bgPengaturan">
        <summary id="bgPengaturanJudul">Atur tombol kirim ke keluarga</summary>
        <div class="card" style="margin-top:8px">
            <label class="bg-label" for="bgNama">Nama keluarga yang dihubungi</label>
            <input class="bg-input" id="bgNama" type="text" maxlength="24" autocomplete="off" placeholder="Contoh: Bima">
            <label class="bg-label" for="bgWa">Nomor WhatsApp</label>
            <input class="bg-input" id="bgWa" type="tel" inputmode="tel" maxlength="20" autocomplete="off" placeholder="Contoh: 08123456789">
            <div style="margin-top:14px">
                <button type="button" class="btn" id="bgSimpanKontak">Simpan</button>
                <button type="button" class="btn ghost" id="bgHapusKontak">Hapus kontak</button>
            </div>
            <p class="bg-status" id="bgKontakStatus" role="status"></p>
        </div>
    </details>

    {{-- ===== Pasang di layar utama ===== --}}
    <section class="card bg-pasang" id="bgPasangCard" hidden>
        <h2 style="margin-top:0;font-size:22px">Pasang Jeda di layar utama</h2>
        <p style="margin:0">Supaya Jeda muncul di menu Bagikan dan bisa dibuka dengan sekali ketuk.</p>
        <div style="margin-top:14px">
            <button type="button" class="btn" id="bgPasang" hidden>Pasang Jeda</button>
        </div>
        <ol id="bgPasangManual">
            <li>Buka halaman ini di <b>Chrome</b>.</li>
            <li>Ketuk titik tiga di pojok kanan atas.</li>
            <li>Pilih <b>Pasang aplikasi</b> atau <b>Tambahkan ke layar utama</b>.</li>
        </ol>
        <p class="bg-catatan" id="bgPasangIos" hidden>
            Di iPhone, menu Bagikan ke Jeda belum bisa dipakai. Pesan tetap bisa ditempel di halaman ini.
        </p>
    </section>
</div>
@endsection

@push('scripts')
    {{-- bedah.js hanya memasang window.JedaBedah di halaman ini (tidak ada #bedahInput), tanpa menyentuh tampilan. --}}
    <script src="{{ asset('js/bedah.js') }}?v={{ @filemtime(public_path('js/bedah.js')) }}" defer></script>
    <script src="{{ asset('js/bagikan.js') }}?v={{ @filemtime(public_path('js/bagikan.js')) }}" defer></script>
@endpush