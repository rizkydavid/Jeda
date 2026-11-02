@extends('layouts.app')

@section('title', 'Latihan Kenali Penipuan')

@section('content')
<style>
    .quiz-page{--q-ink:#13233f;--q-muted:#687792;--q-line:#e1e8f2;--q-blue:#3768e8;max-width:1160px;margin:0 auto;padding:32px 20px 68px;color:var(--text,#17233c)}
    .quiz-hero{position:relative;isolation:isolate;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.25fr) minmax(220px,.75fr);align-items:center;gap:24px;min-height:255px;border-radius:30px;padding:34px 38px;margin-bottom:24px;color:#fff;background:radial-gradient(circle at 85% 20%,rgba(75,205,205,.35),transparent 28%),linear-gradient(120deg,#10294c 0%,#194f78 52%,#3768c9 100%);box-shadow:0 22px 55px rgba(25,62,115,.2)}
    .quiz-hero:before,.quiz-hero:after{content:"";position:absolute;z-index:-1;border:1px solid rgba(255,255,255,.13);border-radius:50%;width:300px;height:300px;right:-70px;top:-110px}.quiz-hero:after{width:210px;height:210px;right:-25px;top:-65px}
    .quiz-hero-copy{position:relative;z-index:1}.quiz-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:.75rem;font-weight:850;letter-spacing:.12em;text-transform:uppercase;color:#8ff0e0;margin-bottom:13px}
    .quiz-hero h1{font-size:clamp(1.85rem,3.8vw,3rem);line-height:1.08;letter-spacing:-.045em;margin:0 0 13px;max-width:650px;color:#fff}.quiz-hero p{max-width:650px;color:rgba(255,255,255,.78);line-height:1.7;margin:0;font-size:.98rem}
    .quiz-pills{display:flex;flex-wrap:wrap;gap:8px;margin-top:21px}.quiz-pill{display:inline-flex;align-items:center;gap:7px;border:1px solid rgba(255,255,255,.19);border-radius:999px;padding:8px 12px;font-size:.8rem;color:#fff;background:rgba(255,255,255,.09);backdrop-filter:blur(8px)}
    .quiz-hero-art{min-height:175px;display:grid;place-items:center;position:relative}.quiz-shield{width:150px;height:165px;display:grid;place-items:center;position:relative;clip-path:polygon(50% 0,92% 16%,86% 68%,50% 100%,14% 68%,8% 16%);background:linear-gradient(145deg,#a7f3e5,#58b7d6);filter:drop-shadow(0 18px 18px rgba(0,0,0,.18));color:#123e66;font-size:4.4rem}.quiz-orbit{position:absolute;width:210px;height:210px;border:1px dashed rgba(255,255,255,.32);border-radius:50%;animation:quiz-orbit 28s linear infinite}.quiz-orbit:before,.quiz-orbit:after{content:"✦";position:absolute;color:#ffe7a2;font-size:1.25rem}.quiz-orbit:before{top:20px;left:30px}.quiz-orbit:after{right:12px;bottom:35px}@keyframes quiz-orbit{to{transform:rotate(360deg)}}
    .quiz-layout{display:grid;grid-template-columns:minmax(0,1fr) 285px;gap:22px;align-items:start}.quiz-panel,.quiz-aside{border:1px solid var(--q-line);border-radius:25px;background:var(--card,#fff);padding:27px;box-shadow:0 14px 38px rgba(20,40,70,.055)}
    .quiz-topline{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:13px;font-size:.88rem;color:var(--q-muted)}#quizCounter{font-weight:800;color:var(--q-ink)}#quizScoreLive{border-radius:999px;padding:7px 12px;background:#eef3ff;color:#3659bf;font-size:.82rem}
    .quiz-progress{height:9px;background:#edf1f7;border-radius:99px;overflow:hidden;margin-bottom:25px}.quiz-progress span{height:100%;display:block;width:0;background:linear-gradient(90deg,#39c6b3,#4776ed);border-radius:99px;transition:width .3s ease}
    .quiz-topic{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border-radius:9px;background:#edf8f7;color:#167e79;font-size:.78rem;font-weight:850;margin-bottom:12px}.quiz-topic:before{content:"●";font-size:.55rem}
    .quiz-question{font-size:clamp(1.25rem,2.4vw,1.7rem);line-height:1.35;margin:0 0 17px;letter-spacing:-.025em;color:var(--q-ink)}
    .quiz-context{position:relative;padding:19px 19px 19px 58px;border:1px solid #dce8f7;border-radius:17px;background:linear-gradient(120deg,#f5f9ff,#f8fbff);line-height:1.7;margin-bottom:21px;color:#3e506d;font-size:.94rem}.quiz-context:before{content:"“";position:absolute;left:17px;top:13px;width:28px;height:28px;display:grid;place-items:center;border-radius:9px;background:#dfeaff;color:#3568d4;font-size:1.7rem;font-weight:900;line-height:1}
    .quiz-choices{display:grid;grid-template-columns:1fr 1fr;gap:11px}.quiz-choice{min-height:80px;width:100%;text-align:left;display:flex;gap:12px;align-items:flex-start;border:1px solid var(--q-line);border-radius:16px;padding:15px;background:#fff;color:#34445f;cursor:pointer;font:inherit;font-size:.91rem;line-height:1.5;transition:border-color .18s,background .18s,transform .18s,box-shadow .18s}.quiz-choice:hover:not(:disabled){border-color:#8caaf7;transform:translateY(-2px);box-shadow:0 8px 20px rgba(50,94,180,.08);background:#f9fbff}.quiz-choice:focus-visible,.quiz-btn:focus-visible{outline:3px solid rgba(60,130,210,.35);outline-offset:2px}
    .quiz-letter{flex:0 0 31px;height:31px;border:1px solid #dce5f2;border-radius:10px;display:grid;place-items:center;font-weight:900;font-size:.82rem;color:#53709c;background:#f7f9fc}.quiz-choice.correct{border-color:#25a77a;background:#ecfaf4;color:#145d47;box-shadow:inset 0 0 0 1px #25a77a}.quiz-choice.correct .quiz-letter{background:#d2f5e7;border-color:#a5e5ce;color:#137957}.quiz-choice.wrong{border-color:#e17b7b;background:#fff2f1;color:#853c3c}.quiz-choice.wrong .quiz-letter{background:#ffe0dd;border-color:#f2c2bd;color:#a34242}.quiz-choice:disabled{cursor:default}
    .quiz-feedback{margin-top:17px;border-radius:15px;padding:16px 18px;line-height:1.65;display:none;font-size:.92rem}.quiz-feedback.show{display:block}.quiz-feedback.good{background:#ecfaf4;border:1px solid #bcebd8;color:#175e48}.quiz-feedback.review{background:#fff8e9;border:1px solid #f2dfad;color:#73551d}.quiz-feedback strong{display:block;margin-bottom:5px;font-size:.96rem}
    .quiz-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:21px;flex-wrap:wrap}.quiz-btn{border:1px solid var(--q-line);border-radius:12px;padding:12px 17px;font:inherit;font-size:.9rem;font-weight:850;cursor:pointer;background:#fff;color:#41516b;transition:transform .15s,box-shadow .15s}.quiz-btn:hover:not(:disabled){transform:translateY(-1px)}.quiz-btn.primary{background:linear-gradient(120deg,#386be5,#4b7dec);color:#fff;border-color:transparent;box-shadow:0 8px 18px rgba(57,104,221,.2)}.quiz-btn:disabled{opacity:.45;cursor:not-allowed;box-shadow:none}
    .quiz-aside{padding:23px;position:relative;overflow:hidden}.quiz-aside h3{margin:0 0 9px;font-size:1.04rem;color:var(--q-ink)}.quiz-aside .aside-intro{font-size:.88rem;line-height:1.65;color:var(--q-muted);margin:0 0 18px}.quiz-mini-stat{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin:16px 0 22px}.quiz-stat{padding:13px;border:1px solid var(--q-line);border-radius:15px;background:#fbfcff}.quiz-stat b{font-size:1.45rem;display:block;letter-spacing:-.03em;color:var(--q-ink)}.quiz-stat span{font-size:.76rem;color:var(--q-muted)}
    .quiz-principles{display:grid;gap:10px;margin-top:14px}.quiz-principle{display:flex;align-items:center;gap:11px;padding:10px;border-radius:13px;background:#f7f9fd;color:#52627c;font-size:.82rem;line-height:1.45}.quiz-principle-icon{width:34px;height:34px;flex:0 0 34px;display:grid;place-items:center;border-radius:10px;background:#e5edff;color:#3d68d4;font-size:1rem}.quiz-principle strong{display:block;color:#263b5a;font-size:.83rem;margin-bottom:2px}
    .quiz-result{text-align:center;padding:15px 0 4px}.quiz-result-score{font-size:clamp(3.4rem,10vw,5.4rem);font-weight:950;letter-spacing:-.07em;line-height:1;margin:15px 0 8px;color:#3569df}.quiz-result h2{margin:0 0 10px;font-size:1.7rem;color:var(--q-ink)}.quiz-result p{max-width:620px;margin:0 auto 18px;line-height:1.7;color:var(--q-muted)}.quiz-result-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:22px 0;text-align:left}.quiz-result-grid .quiz-stat{background:#f7f9fd}.quiz-review{margin-top:18px;text-align:left;border-top:1px solid var(--q-line);padding-top:18px}.quiz-review h3{color:var(--q-ink)}.quiz-review-item{padding:13px 0;border-bottom:1px solid var(--q-line);line-height:1.6;color:#354863}.quiz-review-item small{display:block;color:var(--q-muted);margin-top:5px;line-height:1.65}.quiz-hidden{display:none!important}
    @media(max-width:850px){.quiz-layout{grid-template-columns:1fr}.quiz-aside{order:2}.quiz-hero{grid-template-columns:1fr minmax(150px,.45fr);padding:27px}.quiz-hero-art{min-height:140px}.quiz-shield{width:112px;height:128px;font-size:3.3rem}.quiz-orbit{width:160px;height:160px}}
    @media(max-width:600px){.quiz-page{padding:20px 13px 45px}.quiz-hero{grid-template-columns:1fr;padding:25px 22px;border-radius:23px}.quiz-hero-art{display:none}.quiz-hero h1{font-size:2rem}.quiz-pills{gap:7px}.quiz-pill{font-size:.74rem;padding:7px 9px}.quiz-panel{padding:18px;border-radius:20px}.quiz-choices{grid-template-columns:1fr}.quiz-choice{min-height:0}.quiz-context{padding:15px 15px 15px 49px}.quiz-aside{padding:19px}.quiz-result-grid{grid-template-columns:1fr 1fr}.quiz-result-grid .quiz-stat:last-child{grid-column:1/-1}}
    @media(prefers-reduced-motion:reduce){.quiz-choice,.quiz-progress span,.quiz-orbit{transition:none;animation:none}}
</style>

<main class="quiz-page">
    <section class="quiz-hero">
        <div class="quiz-hero-copy">
            <div class="quiz-eyebrow"><span aria-hidden="true">✦</span> JEDA DIGITAL SAFETY</div>
            <h1>Jangan asal klik.<br>Belajar kenali tanda.</h1>
            <p>Uji insting digitalmu lewat 10 situasi sehari-hari. Pilih tindakan terbaik, lalu pelajari alasannya—agar makin siap menghadapi pesan yang mencurigakan.</p>
            <div class="quiz-pills"><span class="quiz-pill">◈ 10 kasus nyata</span><span class="quiz-pill">◷ ± 5 menit</span><span class="quiz-pill">✓ Ada pembahasan</span></div>
        </div>
        <div class="quiz-hero-art" aria-hidden="true"><div class="quiz-orbit"></div><div class="quiz-shield">✓</div></div>
    </section>

    <div class="quiz-layout">
        <section class="quiz-panel" aria-live="polite">
            <div id="quizPlay">
                <div class="quiz-topline"><span id="quizCounter">Pertanyaan 1 dari 10</span><strong id="quizScoreLive">Skor: 0</strong></div>
                <div class="quiz-progress" aria-label="Progres latihan"><span id="quizProgressBar"></span></div>
                <span class="quiz-topic" id="quizTopic">Pesan mencurigakan</span>
                <h2 class="quiz-question" id="quizQuestion"></h2>
                <div class="quiz-context" id="quizContext"></div>
                <div class="quiz-choices" id="quizChoices"></div>
                <div class="quiz-feedback" id="quizFeedback" role="status"></div>
                <div class="quiz-actions"><button class="quiz-btn primary" id="quizNext" type="button" disabled>Jawab dulu</button></div>
            </div>
            <div id="quizResult" class="quiz-hidden"></div>
        </section>

        <aside class="quiz-aside">
            <h3>Progress kamu</h3>
            <p class="aside-intro">Latihan singkat untuk membangun kebiasaan aman saat menerima pesan digital.</p>
            <div class="quiz-mini-stat"><div class="quiz-stat"><b id="quizCorrectCount">0</b><span>Jawaban benar</span></div><div class="quiz-stat"><b id="quizAnsweredCount">0/10</b><span>Soal dijawab</span></div></div>
            <h3>Ingat 3 hal ini</h3>
            <div class="quiz-principles">
                <div class="quiz-principle"><span class="quiz-principle-icon">⏸</span><span><strong>Jeda dulu</strong>Jangan terburu-buru karena ancaman atau hadiah.</span></div>
                <div class="quiz-principle"><span class="quiz-principle-icon">♙</span><span><strong>Jaga rahasia</strong>OTP, PIN, dan kata sandi tidak untuk dibagikan.</span></div>
                <div class="quiz-principle"><span class="quiz-principle-icon">✓</span><span><strong>Verifikasi</strong>Cek melalui aplikasi atau kontak resmi.</span></div>
            </div>
        </aside>
    </div>
</main>

<script>
(() => {
    const questions = [
        {topic:'Permintaan OTP', label:'Berisiko tinggi', context:'Kamu menerima chat yang mengaku dari layanan dompet digital: “Akunmu akan dibekukan 10 menit lagi. Kirim kode OTP yang baru masuk agar akun aktif kembali.”', question:'Apa tindakan paling tepat?', choices:['Kirim OTP karena pesannya mendesak.','Jangan bagikan OTP dan periksa akun melalui aplikasi atau kanal resmi.','Minta pengirim mengirim OTP baru.','Teruskan pesan ke teman agar mereka menilai.'], answer:1, explanation:'Permintaan OTP disertai ancaman dan tekanan waktu adalah tanda kuat rekayasa sosial. OTP tidak boleh dibagikan. Buka aplikasi atau hubungi layanan resmi melalui kontak yang kamu cari sendiri.'},
        {topic:'Pesan yang wajar', label:'Wajar, tetap verifikasi', context:'Temanmu mengirim pesan: “Besok tugas kelompok dikumpulkan jam 10. Ini file materi dari guru yang tadi dibagikan di grup kelas.” Kamu memang sedang mengerjakan tugas itu.', question:'Apakah pesan ini otomatis penipuan?', choices:['Ya, karena ada file.','Ya, karena pesan datang lewat chat.','Tidak otomatis. Konteksnya masuk akal, tetapi pastikan pengirim dan file sesuai.','Aman 100% karena pengirimnya teman.'], answer:2, explanation:'Pesan yang sesuai konteks dan tidak meminta tindakan berisiko bisa jadi wajar. Namun, jangan menganggapnya aman 100% hanya karena pengirim terlihat dikenal—akun teman juga bisa disalahgunakan.'},
        {topic:'Hadiah palsu', label:'Mencurigakan', context:'Pesan dari nomor tak dikenal berbunyi: “Selamat! Kamu menang hadiah Rp5.000.000. Klik tautan ini dan masukkan nomor rekening serta PIN untuk mencairkan.”', question:'Apa tanda yang paling perlu diwaspadai?', choices:['Ada nominal hadiah besar dan diminta memasukkan PIN melalui tautan.','Pesannya singkat.','Pesan menggunakan bahasa Indonesia.','Nomor pengirim belum disimpan saja sudah membuktikan penipuan.'], answer:0, explanation:'Hadiah tak terduga yang meminta PIN atau data keuangan lewat tautan merupakan indikator kuat penipuan. Nomor tak dikenal saja tidak membuktikan penipuan, tetapi permintaan rahasia keuangan sangat berisiko.'},
        {topic:'Verifikasi keluarga', label:'Perlu verifikasi', context:'Kamu mendapat pesan dari nomor baru yang memakai foto profil kakakmu: “Aku ganti nomor. Tolong transfer uang sekarang, nanti aku jelaskan.”', question:'Langkah paling aman adalah…', choices:['Transfer sedikit dulu untuk memastikan.','Percaya karena foto profilnya cocok.','Balas dengan meminta nomor rekening lain.','Hubungi kakak lewat nomor lama atau cara lain yang sudah kamu kenal untuk memastikan.'], answer:3, explanation:'Foto dan nama profil bisa ditiru. Pastikan identitas melalui saluran lain yang sudah kamu percaya sebelum mengirim uang.'},
        {topic:'Tautan login', label:'Mencurigakan', context:'Email mengaku dari layanan belanja yang kamu gunakan. Tautannya memakai alamat yang mirip nama resmi, lalu meminta kamu login untuk “membatalkan transaksi”.', question:'Apa yang sebaiknya kamu lakukan?', choices:['Login karena layanan itu memang kamu gunakan.','Jangan gunakan tautan di email; buka aplikasi atau ketik alamat resmi sendiri dan cek notifikasi akun.','Masukkan kata sandi yang salah untuk mengetes.','Teruskan email ke orang lain agar mencoba tautannya.'], answer:1, explanation:'Tautan yang menyerupai alamat resmi dan meminta login melalui ancaman transaksi perlu dicurigai. Periksa akun lewat aplikasi atau alamat resmi yang kamu buka sendiri.'},
        {topic:'Biaya lowongan kerja', label:'Mencurigakan', context:'Sebuah akun menawarkan pekerjaan dengan gaji tinggi tanpa wawancara. Kamu diminta membayar “biaya administrasi dan seragam” hari ini agar posisi tidak diberikan ke orang lain.', question:'Bagaimana menilai tawaran ini?', choices:['Pasti benar karena gajinya tinggi.','Wajar karena semua pekerjaan meminta biaya di awal.','Sangat mencurigakan; jangan membayar dulu dan verifikasi perusahaan serta lowongan melalui kanal resmi.','Bayar jika pengirim mengirim logo perusahaan.'], answer:2, explanation:'Janji berlebihan, seleksi yang tidak jelas, biaya di muka, dan tekanan waktu merupakan kombinasi indikator risiko. Logo atau dokumen yang tampak resmi bisa dipalsukan.'},
        {topic:'Informasi pengiriman', label:'Cenderung wajar', context:'Kamu memang sedang menunggu paket. Di aplikasi belanja resmi, status pesanan berubah menjadi “sedang dikirim”, dan nomor resi cocok dengan pesananmu.', question:'Apa respons yang paling tepat?', choices:['Anggap ini pasti penipuan karena ada status pengiriman.','Klik tautan acak apa pun yang mengaku terkait paket.','Kirim kode OTP ke kurir jika diminta.','Periksa status langsung di aplikasi resmi; jangan berikan OTP atau data rahasia kepada siapa pun.'], answer:3, explanation:'Informasi yang cocok dengan pesanan bisa jadi wajar. Tetap gunakan aplikasi resmi dan jangan membagikan OTP atau data sensitif. Jika ada biaya tambahan atau tautan tak biasa, verifikasi dahulu.'},
        {topic:'Ancaman akun', label:'Mencurigakan', context:'Pesan mengaku dari bank menyebut rekeningmu akan ditutup hari ini. Pengirim menyuruhmu mengisi formulir melalui tautan pendek dan merahasiakannya dari petugas lain.', question:'Indikator risiko mana yang muncul?', choices:['Ancaman, tekanan waktu, tautan yang perlu diperiksa, dan permintaan merahasiakan proses.','Hanya karena pesan memakai huruf kapital.','Tidak ada indikator karena menyebut bank.','Pasti aman karena menggunakan kata “rekening”.'], answer:0, explanation:'Ancaman penutupan akun, desakan waktu, tautan pendek, dan instruksi merahasiakan proses adalah tanda yang patut diwaspadai. Hubungi bank melalui aplikasi atau nomor resmi yang tertera di kanal resminya.'},
        {topic:'Permintaan data pribadi', label:'Perlu diperiksa', context:'Seseorang yang mengaku panitia acara sekolah meminta nama lengkap dan alamat email untuk mengirim sertifikat. Kamu memang ikut acara itu, tetapi belum mengenal pengirimnya.', question:'Apa langkah terbaik?', choices:['Kirim semua data termasuk NIK dan kata sandi email.','Pastikan identitas panitia dan kebutuhan datanya melalui guru atau kanal acara resmi; berikan hanya data yang diperlukan.','Langsung menuduh pengirim penipu.','Abaikan semua permintaan data, termasuk yang resmi.'], answer:1, explanation:'Permintaan nama dan email bisa memiliki alasan yang wajar, tetapi identitas dan kebutuhan data tetap perlu diverifikasi. Jangan pernah memberikan kata sandi atau data sensitif yang tidak diperlukan.'},
        {topic:'Permintaan transfer yang masuk akal', label:'Perlu verifikasi', context:'Teman sekelas mengirim pesan dari akun biasanya: “Bisa patungan Rp20.000 untuk konsumsi rapat?” Kamu tahu rapat kelompok memang dijadwalkan hari ini.', question:'Apa keputusan paling bijak?', choices:['Transfer langsung karena pesannya masuk akal.','Anggap penipuan dan laporkan tanpa memeriksa.','Konfirmasi jumlah dan tujuan lewat grup atau secara langsung sebelum transfer.','Minta kode OTP sebagai bukti identitas.'], answer:2, explanation:'Permintaan ini bisa saja wajar karena sesuai konteks, tetapi tetap konfirmasi jumlah dan tujuan lewat saluran yang dapat dipercaya. Kebiasaan verifikasi berlaku untuk pesan yang tampak normal juga.'}
    ];

    const $ = id => document.getElementById(id);
    let index = 0, score = 0, answered = false;
    const responses = [];
    const letters = ['A','B','C','D'];

    function renderQuestion() {
        answered = false;
        const q = questions[index];
        $('quizCounter').textContent = `Pertanyaan ${index + 1} dari ${questions.length}`;
        $('quizScoreLive').textContent = `Skor: ${score}`;
        $('quizProgressBar').style.width = `${(index / questions.length) * 100}%`;
        $('quizTopic').textContent = q.topic;
        $('quizQuestion').textContent = q.question;
        $('quizContext').textContent = q.context;
        $('quizChoices').innerHTML = '';
        $('quizFeedback').className = 'quiz-feedback';
        $('quizFeedback').textContent = '';
        $('quizNext').disabled = true;
        $('quizNext').textContent = 'Pilih jawaban';
        q.choices.forEach((choice, choiceIndex) => {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'quiz-choice';
            const letter = document.createElement('span'); letter.className = 'quiz-letter'; letter.textContent = letters[choiceIndex];
            const copy = document.createElement('span'); copy.textContent = choice;
            button.append(letter, copy);
            button.addEventListener('click', () => chooseAnswer(choiceIndex));
            $('quizChoices').appendChild(button);
        });
        $('quizAnsweredCount').textContent = `${index}/10`;
        $('quizCorrectCount').textContent = score;
    }

    function chooseAnswer(choiceIndex) {
        if (answered) return;
        answered = true;
        const q = questions[index];
        const isCorrect = choiceIndex === q.answer;
        if (isCorrect) score++;
        responses.push({question:q, selected:choiceIndex, correct:isCorrect});
        [...$('quizChoices').children].forEach((button, i) => {
            button.disabled = true;
            if (i === q.answer) button.classList.add('correct');
            else if (i === choiceIndex) button.classList.add('wrong');
        });
        const feedback = $('quizFeedback');
        feedback.className = `quiz-feedback show ${isCorrect ? 'good' : 'review'}`;
        const title = document.createElement('strong');
        title.textContent = isCorrect ? '✓ Tepat! Kamu membaca situasinya dengan baik.' : '↻ Belum tepat—ini kesempatan untuk belajar.';
        const explanation = document.createElement('div'); explanation.textContent = q.explanation;
        feedback.append(title, explanation);
        $('quizScoreLive').textContent = `Skor: ${score}`;
        $('quizCorrectCount').textContent = score;
        $('quizAnsweredCount').textContent = `${index + 1}/10`;
        $('quizProgressBar').style.width = `${((index + 1) / questions.length) * 100}%`;
        $('quizNext').disabled = false;
        $('quizNext').textContent = index === questions.length - 1 ? 'Lihat hasil' : 'Soal berikutnya →';
    }

    function finishQuiz() {
        $('quizPlay').classList.add('quiz-hidden');
        $('quizResult').classList.remove('quiz-hidden');
        const percent = Math.round((score / questions.length) * 100);
        let heading, message;
        if (score >= 9) { heading = 'Kamu teliti membaca tanda!'; message = 'Kamu sudah cukup baik membedakan pesan mencurigakan dan pesan yang mungkin wajar. Pertahankan kebiasaan memeriksa konteks dan memverifikasi sebelum bertindak.'; }
        else if (score >= 6) { heading = 'Kamu mulai mengenali polanya.'; message = 'Dasarmu sudah terbentuk. Coba baca lagi pembahasan soal yang keliru, terutama tentang verifikasi identitas, tautan, dan permintaan data.'; }
        else { heading = 'Ayo latih kewaspadaan pelan-pelan.'; message = 'Tidak apa-apa—tujuan latihan ini memang belajar. Ingat: jangan terburu-buru, jangan bagikan kode rahasia, dan verifikasi lewat saluran resmi.'; }
        const result = $('quizResult');
        result.innerHTML = '';
        const wrap = document.createElement('div'); wrap.className = 'quiz-result';
        const badge = document.createElement('div'); badge.className = 'quiz-eyebrow'; badge.textContent = '✦ Latihan selesai';
        const title = document.createElement('h2'); title.textContent = heading;
        const scoreEl = document.createElement('div'); scoreEl.className = 'quiz-result-score'; scoreEl.textContent = `${score}/10`;
        const desc = document.createElement('p'); desc.textContent = message;
        const grid = document.createElement('div'); grid.className = 'quiz-result-grid';
        [[`${score}`, 'Jawaban benar'], [`${10-score}`, 'Perlu dipelajari'], [`${percent}%`, 'Nilai latihan']].forEach(([number,label]) => { const stat = document.createElement('div'); stat.className = 'quiz-stat'; const b = document.createElement('b'); b.textContent = number; const s = document.createElement('span'); s.textContent = label; stat.append(b,s); grid.appendChild(stat); });
        const review = document.createElement('div'); review.className = 'quiz-review';
        const reviewTitle = document.createElement('h3'); reviewTitle.textContent = 'Pembahasan semua soal'; review.appendChild(reviewTitle);
        responses.forEach((r, i) => { const item = document.createElement('div'); item.className = 'quiz-review-item'; const b = document.createElement('b'); b.textContent = `${i+1}. ${r.correct ? '✓ Benar' : '↻ Pelajari lagi'} — ${r.question.topic}`; const small = document.createElement('small'); small.textContent = `Jawaban yang tepat: ${letters[r.question.answer]}. ${r.question.choices[r.question.answer]} ${r.question.explanation}`; item.append(b,small); review.appendChild(item); });
        const actions = document.createElement('div'); actions.className = 'quiz-actions';
        const retry = document.createElement('button'); retry.type='button'; retry.className='quiz-btn primary'; retry.textContent='Ulangi latihan'; retry.addEventListener('click', resetQuiz);
        const home = document.createElement('a'); home.className='quiz-btn'; home.href='{{ route('home') }}'; home.textContent='Kembali ke Beranda'; home.style.textDecoration='none';
        actions.append(retry,home);
        wrap.append(badge,title,scoreEl,desc,grid,review,actions); result.appendChild(wrap);
        $('quizProgressBar').style.width = '100%';
    }

    function resetQuiz() { index=0; score=0; answered=false; responses.length=0; $('quizPlay').classList.remove('quiz-hidden'); $('quizResult').classList.add('quiz-hidden'); renderQuestion(); }
    $('quizNext').addEventListener('click', () => { if (!answered) return; if (index < questions.length - 1) { index++; renderQuestion(); } else finishQuiz(); });
    renderQuestion();
})();
</script>
@endsection
