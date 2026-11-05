@extends('layouts.app')

@section('title', 'Lindungi Keluarga')

@section('content')

<main class="wrap">
    <section class="hero">
        <h1>Lindungi keluarga.</h1>
        <p class="mut" style="max-width:620px;font-size:18px">
            Kamu guardian di rumahmu. Pilih siapa yang mau dilindungi, Jeda siapkan skenario yang paling mungkin mereka temui.
        </p>
    </section>

    <h2>Siapa yang mau kamu lindungi?</h2>
    <div class="row" id="orang" style="margin-bottom:24px">
        <span class="chip on" data-orang="ortu" style="cursor:pointer">Orang tua</span>
        <span class="chip" data-orang="adik" style="cursor:pointer">Adik</span>
        <span class="chip" data-orang="kakek" style="cursor:pointer">Kakek dan nenek</span>
        <span class="chip" data-orang="teman" style="cursor:pointer">Teman</span>
    </div>

    <div class="card" style="max-width:640px">
        <h2 style="margin-top:0">Kartu peringatan siap kirim</h2>
        <p class="small">Pratinjau pesan WhatsApp</p>
        <div class="bubble me" style="max-width:100%;border-radius:16px">
            <b style="color:#cfd8ff;display:block;margin-bottom:4px">Dari kamu</b>
            <span id="isiPesan"></span>
        </div>
        <a class="btn block" id="kirimWa" href="#" style="margin-top:14px">Kirim lewat WhatsApp</a>
    </div>
</main>

<script>
    const pesan = {
        ortu: "Ma, ada penipuan yang lagi ramai: ada yang ngaku jadi aku dan minta transfer. Kalau ada pesan begitu, telepon aku dulu ya, jangan langsung transfer.",
        adik: "Dek, lagi ramai penipuan: ada yang ngaku kenalan atau nawarin hadiah dan akun game murah lewat chat, terus minta transfer atau kode OTP. Kalau ada yang begitu, tanya kakak dulu ya, jangan langsung transfer atau klik tautannya.",
        kakek: "Kek, Nek, lagi ramai penipuan: ada yang ngaku jadi cucu atau petugas bank lewat telepon, lalu minta transfer atau kode OTP. Kalau ada yang begitu, telepon aku dulu ya, jangan kasih kode apa pun.",
        teman: "Bro, lagi ramai penipuan: ada yang ngaku jadi temen atau kenalan dari nomor baru, terus minta transfer cepet-cepet. Kalau dapat pesan begitu, cek dulu ke orangnya langsung sebelum transfer ya."
    };

    const chips = document.querySelectorAll('#orang .chip');
    const isiPesan = document.getElementById('isiPesan');
    const kirimWa = document.getElementById('kirimWa');
    const salinPesan = document.getElementById('salinPesan');

    // Satu-satunya sumber teks adalah pratinjau di layar: yang kamu lihat = yang dikirim.
    const teksAktif = () => isiPesan.textContent.trim();

    chips.forEach(c => c.addEventListener('click', () => {
        chips.forEach(x => x.classList.toggle('on', x === c));
        isiPesan.textContent = pesan[c.dataset.orang];
    }));

    kirimWa.addEventListener('click', async (e) => {
        e.preventDefault();
        const teks = teksAktif();

        // Di HP: buka lembar bagikan bawaan, teksnya persis sama dengan pratinjau.
        if (navigator.share && /Android|iPhone|iPad/i.test(navigator.userAgent)) {
            try { await navigator.share({ text: teks }); return; }
            catch (err) { if (err.name === 'AbortError') return; }
        }
        // Di komputer: langsung ke API WhatsApp (tanpa perantara wa.me).
        window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(teks), '_blank', 'noopener');
    });
    isiPesan.textContent = pesan.ortu;
</script>

@endsection