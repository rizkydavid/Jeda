@extends('layouts.app')

@section('title', 'Latihan Kenali Penipuan')

@section('content')
<main class="quiz-page">
    {{-- Hero --}}
    <section class="quiz-hero">
        <div class="quiz-hero-copy">
            <div class="quiz-eyebrow"><span aria-hidden="true">✦</span> JEDA DIGITAL SAFETY</div>
            <h1>Jangan asal klik.<br>Belajar kenali tanda.</h1>
            <p>Uji insting digitalmu lewat situasi sehari-hari. Pilih tindakan terbaik, kumpulkan EXP, dan naikkan levelmu hingga tingkat 10!</p>
            <div class="quiz-pills"><span class="quiz-pill">◈ 10 Level Tantangan</span><span class="quiz-pill">◷ ± 5 menit</span><span class="quiz-pill">✓ Sistem EXP & Level</span></div>
        </div>
        <div class="quiz-hero-art" aria-hidden="true"><div class="quiz-orbit"></div><div class="quiz-shield">✓</div></div>
    </section>

    <div class="quiz-layout">
        <section class="quiz-panel" aria-live="polite">
            {{-- Pilih level --}}
            <div id="quizLevelSelect">
                <h2 class="quiz-title">Pilih level latihan</h2>
                <p class="quiz-intro">Setiap jawaban benar memberi EXP. Kumpulkan EXP dari ronde ke ronde untuk membuka level berikutnya. Soal dipilih acak dan berganti setiap hari.</p>

                <div class="quiz-player" id="quizPlayer"></div>
                <div class="quiz-map" id="quizMap"></div>
                <div class="quiz-level-detail" id="quizLevelDetail" aria-live="polite"></div>

                <ul class="quiz-rules">
                    <li><b>EXP per jawaban benar</b>Makin tinggi level, makin besar EXP-nya.</li>
                    <li><b>Bonus sempurna</b>Jawab 10 dari 10 benar untuk bonus EXP tambahan.</li>
                    <li><b>Main ulang bebas</b>Level yang sudah terbuka bisa dimainkan kapan saja.</li>
                </ul>
                <section class="quiz-recovery" aria-labelledby="recoveryTitle">
                    <div class="quiz-recovery-head">
                        <span class="quiz-recovery-icon" aria-hidden="true">🔑</span>
                        <div>
                            <h3 id="recoveryTitle">Cadangkan progres</h3>
                            <p>Jeda tanpa akun, jadi progres tersimpan di browser ini. Buat kode pemulihan supaya level dan EXP-mu bisa dikembalikan jika riwayat browser terhapus atau saat pindah perangkat.</p>
                        </div>
                    </div>
                    <div class="quiz-recovery-grid">
                        <div class="quiz-recovery-box">
                            <h4>Kode pemulihanmu</h4>
                            <p class="quiz-recovery-note" id="recoveryStatus"></p>
                            <div class="quiz-code-box quiz-hidden" id="recoveryCodeBox">
                                <code id="recoveryCode" aria-label="Kode pemulihan"></code>
                                <button class="quiz-btn" id="recoveryCopy" type="button">Salin</button>
                            </div>
                            <p class="quiz-recovery-warn quiz-hidden" id="recoveryWarn">Simpan kode ini sekarang (foto layar atau catat). Kode hanya ditampilkan sekali dan tidak bisa dilihat lagi.</p>
                            <button class="quiz-btn primary" id="recoveryCreate" type="button">Buat kode pemulihan</button>
                        </div>
                        <div class="quiz-recovery-box">
                            <h4>Punya kode? Pulihkan progres</h4>
                            <p class="quiz-recovery-note">Masukkan kode yang pernah kamu simpan. Progres dari kode itu dipindahkan ke browser ini dan digabung dengan progres yang sudah ada.</p>
                            <input class="quiz-input" id="recoveryInput" type="text" placeholder="JEDA-XXXX-XXXX-XXXX" aria-label="Masukkan kode pemulihan" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="30">
                            <button class="quiz-btn" id="recoveryRedeem" type="button">Pulihkan progres</button>
                        </div>
                    </div>
                    <p class="quiz-recovery-msg" id="recoveryMessage" role="status"></p>
                    <p class="quiz-recovery-fine">Siapa pun yang memegang kode ini bisa memakai progresmu, jadi jangan dibagikan. Kode yang hilang tidak bisa dipulihkan karena Jeda tidak menyimpan kodenya.</p>
                </section>

                <div class="quiz-save-status" id="quizProgressStatus" role="status">Memuat progres latihan...</div>
            </div>

            {{-- Bermain --}}
            <div id="quizPlay" class="quiz-hidden">
                <div class="quiz-topline">
                    <button type="button" class="quiz-btn" id="quizBackToLevels">← Semua level</button>
                    <span id="quizLevelName"></span>
                </div>
                <div class="quiz-topline">
                    <span id="quizCounter">Pertanyaan 1 dari 10</span>
                    <div class="quiz-score-chips">
                        <strong class="quiz-chip" id="quizScoreLive">Skor: 0</strong>
                        <strong class="quiz-chip quiz-chip--xp" id="quizXpLive">+0 EXP</strong>
                    </div>
                </div>
                <div class="quiz-bar quiz-play-bar" aria-label="Progres ronde"><span id="quizProgressBar"></span></div>
                <span class="quiz-topic" id="quizTopic">Pesan mencurigakan</span>
                <h2 class="quiz-question" id="quizQuestion"></h2>
                <div class="quiz-context" id="quizContext"></div>
                <div class="quiz-choices" id="quizChoices"></div>
                <div class="quiz-feedback" id="quizFeedback" role="status"></div>
                <div class="quiz-actions"><button class="quiz-btn primary" id="quizNext" type="button" disabled>Jawab dulu</button></div>
            </div>

            {{-- Hasil --}}
            <div id="quizResult" class="quiz-hidden"></div>
        </section>

        <aside class="quiz-aside">
            <h3>Progress kamu</h3>
            <p class="quiz-intro">Latihan singkat untuk membangun kebiasaan aman saat menerima pesan digital.</p>
            <div id="quizAsideLevel"></div>
            <div class="quiz-mini-stat">
                <div class="quiz-stat"><b id="quizCorrectCount">0</b><span>Benar ronde ini</span></div>
                <div class="quiz-stat"><b id="quizAnsweredCount">0/10</b><span>Soal dijawab</span></div>
            </div>
            <h3>Ingat 3 hal ini</h3>
            <div class="quiz-principles">
                <div class="quiz-principle"><span class="quiz-principle-icon">⏸</span><span><strong>Jeda dulu</strong>Jangan terburu-buru karena ancaman atau hadiah.</span></div>
                <div class="quiz-principle"><span class="quiz-principle-icon">♙</span><span><strong>Jaga rahasia</strong>OTP, PIN, dan kata sandi tidak untuk dibagikan.</span></div>
                <div class="quiz-principle"><span class="quiz-principle-icon">✓</span><span><strong>Verifikasi</strong>Cek melalui aplikasi atau kontak resmi.</span></div>
            </div>
        </aside>
    </div>
</main>
@endsection

@push('scripts')
    <script>
        window.JEDA_QUIZ_CONFIG = {
            questionsPerRound: @json(config('jeda_quiz.questions_per_round')),
            xpBase: @json(config('jeda_quiz.xp_per_correct_base')),
            xpStep: @json(config('jeda_quiz.xp_per_correct_step')),
            perfectBonus: @json(config('jeda_quiz.perfect_bonus')),
            levels: @json(collect(config('jeda_quiz.levels'))->map(fn ($level, $id) => ['id' => $id] + $level)->values()),
            routes: {
                save: @json(route('jeda.api.quiz-attempt')),
                recoveryCreate: @json(route('jeda.api.quiz-recovery-create')),
                recoveryRedeem: @json(route('jeda.api.quiz-recovery-redeem')),
                progress: @json(route('jeda.api.quiz-progress')),
            },
        };
    </script>
    <script src="{{ asset('js/latihan-data.js') }}"></script>
    <script src="{{ asset('js/latihan.js') }}"></script>
@endpush
