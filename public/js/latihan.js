/**
 * Halaman Latihan Jeda: kuis 10 level dengan sistem EXP.
 *
 * Alur singkat:
 *  1. Muat progres (EXP + level) dari server.
 *  2. Pemain memilih level yang sudah terbuka, menjawab 10 soal.
 *  3. Hasil dikirim ke server; server menghitung EXP dan mengembalikan level terbaru.
 *
 * Catatan: EXP selalu dihitung di server (LatihanController). Angka EXP di sisi
 * browser hanya untuk tampilan sementara selama ronde berlangsung.
 *
 * Data bergantung pada: window.JEDA_QUIZ_CONFIG (dari blade) dan window.JEDA_QUIZ_BANK (latihan-data.js).
 */
(() => {
    'use strict';

    const CONFIG = window.JEDA_QUIZ_CONFIG;
    const BANK = window.JEDA_QUIZ_BANK || [];
    const LEVELS = CONFIG.levels;
    const MAX_LEVEL = LEVELS.length;
    const PER_ROUND = CONFIG.questionsPerRound;
    const LETTERS = ['A', 'B', 'C', 'D'];

    const $ = (id) => document.getElementById(id);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    /* ---------------------------------------------------------------- state */
    const state = {
        level: 1,
        questions: [],
        index: 0,
        score: 0,
        answered: false,
        saving: false,
        responses: [],
        xp: defaultXp(),
        levels: {},        // statistik per level dari server
        loaded: false,
    };

    function defaultXp() {
        return {
            xp: 0, level: 1, max_level: MAX_LEVEL, is_max: false, level_start: 0,
            next_level_xp: LEVELS[1]?.xp_required ?? null, xp_into_level: 0,
            xp_needed: LEVELS[1]?.xp_required ?? 0, percent: 0,
        };
    }

    /* -------------------------------------------------------------- helpers */
    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function levelInfo(id) { return LEVELS[id - 1]; }
    function xpPerCorrect(level) { return CONFIG.xpBase + (level - 1) * CONFIG.xpStep; }
    function fmt(n) { return Number(n).toLocaleString('id-ID'); }

    function todayKey() {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    function seededShuffle(items, seedText) {
        let seed = 2166136261;
        for (let i = 0; i < seedText.length; i++) { seed ^= seedText.charCodeAt(i); seed = Math.imul(seed, 16777619); }
        const random = () => {
            seed += 0x6D2B79F5;
            let t = seed;
            t = Math.imul(t ^ t >>> 15, t | 1);
            t ^= t + Math.imul(t ^ t >>> 7, t | 61);
            return ((t ^ t >>> 14) >>> 0) / 4294967296;
        };
        const out = [...items];
        for (let i = out.length - 1; i > 0; i--) {
            const j = Math.floor(random() * (i + 1));
            [out[i], out[j]] = [out[j], out[i]];
        }
        return out;
    }

    function show(id) { $(id).classList.remove('quiz-hidden'); }
    function hide(id) { $(id).classList.add('quiz-hidden'); }

    /* ---------------------------------------------------------- progres/API */
    async function loadProgress() {
        try {
            const response = await fetch(`${CONFIG.routes.progress}?date=${todayKey()}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Gagal memuat progres');
            applyProgress(await response.json());
            state.loaded = true;
            $('quizProgressStatus').textContent = 'Progres tersimpan otomatis di sesi browser ini. Soal berganti setiap hari dan setiap ronde.';
        } catch (error) {
            $('quizProgressStatus').textContent = 'Progres belum dapat dimuat dari server. Pastikan migrasi database sudah dijalankan (php artisan migrate).';
        }
        renderHome();
    }

    function applyProgress(data) {
        if (data.xp) state.xp = data.xp;
        if (data.levels) state.levels = data.levels;
    }

    /* ------------------------------------------------------------ tampilan */
    function renderHome() {
        renderPlayer();
        renderLevels();
        renderAside();
    }

    function xpLabel(summary) {
        if (summary.is_max) return `${fmt(summary.xp)} EXP · Level maksimal tercapai`;
        const span = summary.next_level_xp - summary.level_start;
        return `${fmt(summary.xp_into_level)} / ${fmt(span)} EXP menuju Level ${summary.level + 1}`;
    }

    function badge(level, small = 'LEVEL') {
        const box = el('div', 'quiz-badge');
        box.append(el('small', '', small), el('b', '', String(level)));
        box.setAttribute('aria-hidden', 'true');
        return box;
    }

    function xpBar(percent) {
        const bar = el('div', 'quiz-bar quiz-bar--xp');
        const fill = el('span');
        fill.style.width = `${percent}%`;
        bar.appendChild(fill);
        return { bar, fill };
    }

    function renderPlayer() {
        const box = $('quizPlayer');
        box.innerHTML = '';
        const s = state.xp;
        const info = levelInfo(s.level);

        const main = el('div', 'quiz-player-main');
        const name = el('div', 'quiz-player-name');
        name.append(el('strong', '', `${info.icon} ${info.name}`), el('span', '', `${fmt(s.xp)} EXP total`));
        const { bar } = xpBar(s.percent);
        main.append(name, bar, el('div', 'quiz-player-xp', xpLabel(s)));

        const play = el('button', 'quiz-btn primary', `Lanjut Level ${s.level}`);
        play.type = 'button';
        play.addEventListener('click', () => startLevel(s.level));

        box.append(badge(s.level), main, play);
    }

    function renderLevels() {
        const container = $('quizLevels');
        container.innerHTML = '';
        LEVELS.forEach((item) => {
            const unlocked = item.id <= state.xp.level;
            const stats = state.levels[item.id];

            const card = el('button', `quiz-level-card${item.id === state.xp.level ? ' is-current' : ''}`);
            card.type = 'button';
            card.disabled = !unlocked;
            card.setAttribute('aria-label', `Level ${item.id}, ${item.name}${unlocked ? '' : ', terkunci'}`);

            const status = el('span', 'level-status');
            if (!unlocked) status.textContent = `🔒 Butuh ${fmt(item.xp_required)} EXP`;
            else if (stats) status.textContent = `✓ Terbaik ${stats.best_score}%`;
            else status.textContent = '▶ Mulai latihan';

            card.append(
                el('span', 'level-num', `Level ${item.id}`),
                el('span', 'level-icon', item.icon),
                el('strong', '', item.name),
                el('span', 'level-desc', item.description),
                status,
            );
            if (item.id === state.xp.level) card.appendChild(el('span', 'level-tag', 'Saat ini'));
            card.addEventListener('click', () => startLevel(item.id));
            container.appendChild(card);
        });
    }

    function renderAside() {
        const box = $('quizAsideLevel');
        box.innerHTML = '';
        const s = state.xp;
        const info = levelInfo(s.level);

        const head = el('div', 'quiz-aside-level');
        const text = el('div');
        text.append(el('strong', '', info.name), el('span', '', `${fmt(s.xp)} EXP total`));
        head.append(badge(s.level), text);

        const { bar } = xpBar(s.percent);
        box.append(head, bar, el('p', 'quiz-aside-xp', xpLabel(s)));
    }

    /* ---------------------------------------------------------------- ronde */
    function buildRound(levelId) {
        const rounds = state.levels[levelId]?.rounds_today || 0;
        const seed = `${todayKey()}-level-${levelId}-r${rounds}`;
        const picked = seededShuffle(BANK.filter((q) => q.level === levelId), seed).slice(0, PER_ROUND);
        // Acak urutan pilihan juga; jawaban benar tetap menempel pada teksnya.
        return picked.map((q) => {
            const options = q.choices.map((text, i) => ({ text, correct: i === q.answer }));
            const shuffled = seededShuffle(options, `${seed}-${q.id}-answers`);
            return { ...q, choices: shuffled.map((o) => o.text), answer: shuffled.findIndex((o) => o.correct) };
        });
    }

    function startLevel(id) {
        if (id > state.xp.level) return;
        Object.assign(state, { level: id, index: 0, score: 0, answered: false, saving: false, responses: [] });
        state.questions = buildRound(id);
        if (state.questions.length < PER_ROUND) {
            $('quizProgressStatus').textContent = 'Bank soal untuk level ini belum lengkap.';
            return;
        }
        hide('quizLevelSelect'); hide('quizResult'); show('quizPlay');
        const info = levelInfo(id);
        $('quizLevelName').textContent = `Level ${id} · ${info.name} · +${xpPerCorrect(id)} EXP/benar`;
        renderQuestion();
    }

    function roundXpPreview() { return state.score * xpPerCorrect(state.level); }

    function renderQuestion() {
        state.answered = false;
        const q = state.questions[state.index];
        const total = state.questions.length;

        $('quizCounter').textContent = `Pertanyaan ${state.index + 1} dari ${total}`;
        $('quizScoreLive').textContent = `Skor: ${state.score}`;
        $('quizXpLive').textContent = `+${roundXpPreview()} EXP`;
        $('quizProgressBar').style.width = `${(state.index / total) * 100}%`;
        $('quizTopic').textContent = q.topic;
        $('quizQuestion').textContent = q.question;
        $('quizContext').textContent = q.context;
        $('quizFeedback').className = 'quiz-feedback';
        $('quizFeedback').textContent = '';
        $('quizNext').disabled = true;
        $('quizNext').textContent = 'Pilih jawaban';
        $('quizAnsweredCount').textContent = `${state.index}/${total}`;
        $('quizCorrectCount').textContent = state.score;

        const choices = $('quizChoices');
        choices.innerHTML = '';
        q.choices.forEach((choice, i) => {
            const button = el('button', 'quiz-choice');
            button.type = 'button';
            button.append(el('span', 'quiz-letter', LETTERS[i]), el('span', '', choice));
            button.addEventListener('click', () => chooseAnswer(i));
            choices.appendChild(button);
        });
    }

    function chooseAnswer(choiceIndex) {
        if (state.answered) return;
        state.answered = true;
        const q = state.questions[state.index];
        const total = state.questions.length;
        const isCorrect = choiceIndex === q.answer;
        if (isCorrect) state.score++;
        state.responses.push({ question_id: q.id, selected: choiceIndex, correct: isCorrect, answer: q.answer, topic: q.topic });

        [...$('quizChoices').children].forEach((button, i) => {
            button.disabled = true;
            if (i === q.answer) button.classList.add('correct');
            else if (i === choiceIndex) button.classList.add('wrong');
        });

        const feedback = $('quizFeedback');
        feedback.className = `quiz-feedback show ${isCorrect ? 'good' : 'review'}`;
        feedback.append(
            el('strong', '', isCorrect
                ? `✓ Tepat! +${xpPerCorrect(state.level)} EXP`
                : '↻ Belum tepat—ini kesempatan untuk belajar.'),
            el('div', '', q.explanation),
        );

        $('quizScoreLive').textContent = `Skor: ${state.score}`;
        $('quizXpLive').textContent = `+${roundXpPreview()} EXP`;
        $('quizCorrectCount').textContent = state.score;
        $('quizAnsweredCount').textContent = `${state.index + 1}/${total}`;
        $('quizProgressBar').style.width = `${((state.index + 1) / total) * 100}%`;
        $('quizNext').disabled = false;
        $('quizNext').textContent = state.index === total - 1 ? 'Simpan & lihat hasil' : 'Soal berikutnya →';
    }

    /* ---------------------------------------------------------------- hasil */
    function resultCopy(score) {
        if (score >= 9) return ['Kamu teliti membaca tanda!', 'Kamu sudah cukup baik membedakan pesan berisiko dan pesan yang mungkin wajar.'];
        if (score >= 7) return ['Hasil yang solid!', 'Kamu sudah memahami banyak pola. Pelajari kembali soal yang keliru agar makin kuat.'];
        if (score >= 5) return ['Kamu mulai mengenali polanya.', 'Terus berlatih mengenali konteks, memeriksa identitas, dan menghindari tekanan waktu.'];
        return ['Ayo latih kewaspadaan pelan-pelan.', 'Tidak apa-apa—tujuan latihan ini memang belajar. Baca pembahasan dan coba lagi.'];
    }

    function stat(value, label) {
        const box = el('div', 'quiz-stat');
        box.append(el('b', '', value), el('span', '', label));
        return box;
    }

    function buildXpBox(data) {
        const box = el('div', 'quiz-xp-box');
        const after = data.xp;
        const before = data.xp_before;
        const b = data.xp_breakdown;

        const head = el('div', 'quiz-xp-head');
        head.append(el('strong', '', `+${fmt(data.xp_earned)} EXP`), el('span', '', `Total ${fmt(after.xp)} EXP`));

        const lines = el('ul', 'quiz-xp-lines');
        const line = (left, right) => { const li = el('li'); li.append(el('span', '', left), el('span', '', right)); lines.appendChild(li); };
        line(`${b.correct} jawaban benar × ${b.per_correct} EXP`, `+${fmt(b.correct_xp)}`);
        if (b.perfect_bonus > 0) line('Bonus ronde sempurna', `+${fmt(b.perfect_bonus)}`);

        // Bar menampilkan posisi EXP di level terbaru; animasi dari posisi sebelum ronde.
        const startPercent = data.leveled_up ? 0 : before.percent;
        const { bar, fill } = xpBar(startPercent);
        requestAnimationFrame(() => requestAnimationFrame(() => { fill.style.width = `${after.percent}%`; }));

        box.append(head, lines, bar, el('p', 'quiz-xp-note', xpLabel(after)));
        return box;
    }

    function buildReview() {
        const review = el('details', 'quiz-review');
        review.appendChild(el('summary', '', 'Pembahasan semua soal'));
        state.responses.forEach((r, i) => {
            const q = state.questions[i];
            const item = el('div', 'quiz-review-item');
            item.appendChild(el('b', '', `${i + 1}. ${r.correct ? '✓ Benar' : '↻ Pelajari lagi'} — ${q.topic}`));
            item.appendChild(el('small', '', `Jawaban yang tepat: ${LETTERS[q.answer]}. ${q.choices[q.answer]} ${q.explanation}`));
            review.appendChild(item);
        });
        return review;
    }

    async function saveAttempt() {
        const response = await fetch(CONFIG.routes.save, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({
                level: state.level, score: state.score, total_questions: state.questions.length,
                correct_count: state.score, quiz_date: todayKey(), answers: state.responses,
            }),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Hasil belum tersimpan.');
        return data;
    }

    async function finishQuiz() {
        if (state.saving) return;
        state.saving = true;
        $('quizNext').disabled = true;
        $('quizNext').textContent = 'Menyimpan hasil…';

        let data = null;
        let saveMessage = '';
        try {
            data = await saveAttempt();
            applyProgress(data);
        } catch (error) {
            saveMessage = `${error.message || 'Hasil belum tersimpan.'} Nilai tetap ditampilkan, tetapi EXP belum tercatat.`;
        }

        hide('quizPlay'); show('quizResult');
        const { score } = state;
        const total = state.questions.length;
        const [heading, message] = resultCopy(score);

        const wrap = el('div', 'quiz-result');
        wrap.append(
            el('div', 'quiz-eyebrow', `✦ Level ${state.level} selesai`),
            el('h2', '', heading),
            el('div', 'quiz-result-score', `${score}/${total}`),
            el('p', '', message),
        );

        if (data?.leveled_up) {
            const info = levelInfo(data.xp.level);
            const banner = el('div', 'quiz-levelup');
            banner.setAttribute('role', 'status');
            banner.append(
                el('b', '', `🎉 Naik ke Level ${data.xp.level} · ${info.name}!`),
                el('span', '', `Level ${data.xp.level} sekarang terbuka. Soalnya lebih menantang, EXP per jawaban benar juga lebih besar (+${xpPerCorrect(data.xp.level)}).`),
            );
            wrap.appendChild(banner);
        }

        if (data) wrap.appendChild(buildXpBox(data));

        const grid = el('div', 'quiz-result-grid');
        grid.append(stat(`${score}`, 'Jawaban benar'), stat(`${total - score}`, 'Perlu dipelajari'), stat(`${Math.round((score / total) * 100)}%`, 'Nilai latihan'));
        wrap.appendChild(grid);

        if (saveMessage) wrap.appendChild(el('p', 'quiz-save-status', saveMessage));
        wrap.appendChild(buildReview());

        const actions = el('div', 'quiz-actions');
        const again = el('button', 'quiz-btn primary', `Main lagi Level ${state.level}`);
        again.type = 'button';
        again.addEventListener('click', () => startLevel(state.level));
        actions.appendChild(again);
        if (data?.leveled_up) {
            const next = el('button', 'quiz-btn', `Coba Level ${data.xp.level}`);
            next.type = 'button';
            next.addEventListener('click', () => startLevel(data.xp.level));
            actions.appendChild(next);
        }
        const back = el('button', 'quiz-btn', 'Pilih level');
        back.type = 'button';
        back.addEventListener('click', backToLevels);
        actions.appendChild(back);
        wrap.appendChild(actions);

        const result = $('quizResult');
        result.innerHTML = '';
        result.appendChild(wrap);
        $('quizProgressBar').style.width = '100%';
        renderHome();
        state.saving = false;
    }

    function backToLevels() {
        state.saving = false;
        hide('quizPlay'); hide('quizResult'); show('quizLevelSelect');
        $('quizCorrectCount').textContent = '0';
        $('quizAnsweredCount').textContent = `0/${PER_ROUND}`;
        loadProgress();
    }

    /* ----------------------------------------------------------------- init */
    $('quizNext').addEventListener('click', () => {
        if (!state.answered) return;
        if (state.index < state.questions.length - 1) { state.index++; renderQuestion(); } else finishQuiz();
    });
    $('quizBackToLevels').addEventListener('click', backToLevels);

    renderHome();
    loadProgress();
})();
