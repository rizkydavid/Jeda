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
        selected: null,    // level yang sedang dipilih di peta
        recovery: { has_code: false, created_at: null }, // status kode pemulihan (kodenya sendiri tidak disimpan)
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
        if (data.recovery) state.recovery = data.recovery;
    }

    /* ------------------------------------------------------------ tampilan */
    function renderHome() {
        renderPlayer();
        renderMap();
        renderDetail();
        renderAside();
        renderRecovery();
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

    /* ------------------------------------------------------ peta perjalanan */
    const SVG_NS = 'http://www.w3.org/2000/svg';
    const mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 700px)') : { matches: false };
    const MAP_W = 1400, MAP_H = 613;

    const ICONS = {
        check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.2 4.2L19 7" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        play: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg>',
        lock: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5.5" y="10.5" width="13" height="9.5" rx="2.2" fill="currentColor"/><path d="M8.5 10.5V8a3.5 3.5 0 017 0v2.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
        flag: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 21V4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" fill="none"/><path d="M6 5h11l-2.4 3.6L17 12H6z" fill="currentColor"/></svg>',
        goal: '<svg viewBox="0 0 64 74" aria-hidden="true"><path d="M8 66l6-12 6 12zM46 66l6-14 6 14z" fill="#2c8a5f"/><rect x="14" y="36" width="36" height="28" rx="4" fill="#d6e5ff"/><rect x="20" y="42" width="7" height="9" rx="1.5" fill="#ffd76a"/><rect x="37" y="42" width="7" height="9" rx="1.5" fill="#ffd76a"/><rect x="28" y="52" width="8" height="12" rx="2" fill="#3a63c8"/><circle cx="32" cy="22" r="17" fill="#3d7bff"/><path d="M32 12l9 3.2v6.4c0 5-3.6 8.4-9 10.4-5.4-2-9-5.4-9-10.4v-6.4z" fill="#fff"/><path d="M28 21.5l3 3 5-5.5" fill="none" stroke="#3d7bff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    };

    // Titik level pada peta lebar (koordinat gambar 1400 x 613), dan jalur vertikal untuk HP.
    const DESKTOP_POINTS = [[126, 268], [303, 215], [493, 217], [651, 125], [834, 179], [980, 89], [1140, 189], [1219, 337], [991, 430], [735, 430]];
    // Pulau dekoratif: [x, y, lebar] dalam koordinat gambar.
    const DESKTOP_ISLANDS = [[205, 352, 400], [575, 330, 270], [770, 238, 210], [1010, 160, 240], [1255, 305, 360], [855, 505, 430], [1253, 120, 190]];
    const DESKTOP_CLOUDS = [[60, 580, 360, 130], [1340, 560, 340, 140], [700, 20, 520, 90]];

    function mapLayout(vertical) {
        if (!vertical) {
            return {
                width: MAP_W, height: MAP_H,
                points: DESKTOP_POINTS.map(([x, y]) => ({ left: `${(x / MAP_W) * 100}%`, top: `${(y / MAP_H) * 100}%`, px: x, py: y })),
                islands: DESKTOP_ISLANDS.map(([x, y, w]) => ({ left: `${(x / MAP_W) * 100}%`, top: `${(y / MAP_H) * 100}%`, size: `${(w / MAP_W) * 100}cqw` })),
                clouds: DESKTOP_CLOUDS.map(([x, y, w, h]) => ({ left: `${((x - w / 2) / MAP_W) * 100}%`, top: `${((y - h / 2) / MAP_H) * 100}%`, w: `${(w / MAP_W) * 100}%`, h: `${(h / MAP_H) * 100}%` })),
                goal: { left: `${(1253 / MAP_W) * 100}%`, top: `${(70 / MAP_H) * 100}%` },
            };
        }
        const step = 104, top = 60, height = top + (MAX_LEVEL - 1) * step + 190;
        const points = LEVELS.map((_, i) => ({ left: `${i % 2 === 0 ? 28 : 72}%`, top: `${top + i * step}px`, px: i % 2 === 0 ? 28 : 72, py: top + i * step }));
        const islands = [0, 1, 2, 3, 4].map((k) => ({ left: `${k % 2 === 0 ? 32 : 70}%`, top: `${top + k * step * 2 + 28}px`, size: '150px' }));
        const clouds = [{ left: '-10%', top: `${height - 150}px`, w: '60%', h: '110px' }, { left: '55%', top: '30px', w: '60%', h: '90px' }];
        return { width: 100, height, points, islands, clouds, goal: { left: '50%', top: `${top + (MAX_LEVEL - 1) * step + 120}px` } };
    }

    // Kurva halus (Catmull-Rom) melalui semua titik.
    function smoothPath(pts, upto = pts.length - 1) {
        if (pts.length < 2 || upto < 1) return '';
        let d = `M ${pts[0].px} ${pts[0].py}`;
        for (let i = 0; i < upto; i++) {
            const p0 = pts[i - 1] || pts[i], p1 = pts[i], p2 = pts[i + 1], p3 = pts[i + 2] || p2;
            const c1x = p1.px + (p2.px - p0.px) / 6, c1y = p1.py + (p2.py - p0.py) / 6;
            const c2x = p2.px - (p3.px - p1.px) / 6, c2y = p2.py - (p3.py - p1.py) / 6;
            d += ` C ${c1x} ${c1y} ${c2x} ${c2y} ${p2.px} ${p2.py}`;
        }
        return d;
    }

    function svgEl(tag, attrs) {
        const node = document.createElementNS(SVG_NS, tag);
        Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, v));
        return node;
    }

    function pathLayer(className, layout, currentIndex, baseClass, doneClass) {
        const svg = svgEl('svg', { class: `map-path ${className}`, viewBox: `0 0 ${layout.width} ${layout.height}`, preserveAspectRatio: 'none', 'aria-hidden': 'true' });
        svg.appendChild(svgEl('path', { class: baseClass, d: smoothPath(layout.points) }));
        if (currentIndex > 0) svg.appendChild(svgEl('path', { class: doneClass, d: smoothPath(layout.points, currentIndex) }));
        return svg;
    }

    function selectedLevel() { return state.selected || state.xp.level; }

    function renderMap() {
        const map = $('quizMap');
        if (!map) return;
        const vertical = mobileQuery.matches;
        const layout = mapLayout(vertical);
        const currentIndex = state.xp.level - 1;

        map.innerHTML = '';
        map.classList.toggle('is-vertical', vertical);

        const head = el('div', 'map-head');
        const headIcon = el('span', 'map-head-icon');
        headIcon.innerHTML = ICONS.flag;
        const headText = el('div');
        headText.append(el('h3', '', 'Peta Perjalanan'), el('p', '', 'Selesaikan setiap level untuk menjadi detektif digital yang lebih waspada!'));
        head.append(headIcon, headText);

        const scene = el('div', 'map-scene');
        if (vertical) scene.style.height = `${layout.height}px`;

        layout.clouds.forEach((c) => {
            const cloud = el('span', 'map-cloud');
            Object.assign(cloud.style, { left: c.left, top: c.top, width: c.w, height: c.h });
            scene.appendChild(cloud);
        });
        layout.islands.forEach((isl) => {
            const island = el('span', 'map-island');
            island.style.setProperty('--w', isl.size);
            Object.assign(island.style, { left: isl.left, top: isl.top });
            island.append(document.createElement('i'), document.createElement('i'));
            scene.appendChild(island);
        });

        scene.append(
            pathLayer('map-path--glow', layout, currentIndex, 'glow-base', 'glow-done'),
            pathLayer('map-path--line', layout, currentIndex, 'line-base', 'line-done'),
        );

        const goal = el('span', 'map-goal');
        goal.innerHTML = ICONS.goal;
        Object.assign(goal.style, { left: layout.goal.left, top: layout.goal.top });
        scene.appendChild(goal);

        LEVELS.forEach((item, i) => {
            const unlocked = item.id <= state.xp.level;
            const status = !unlocked ? 'is-locked' : item.id < state.xp.level ? 'is-done' : 'is-current';
            const icon = !unlocked ? ICONS.lock : item.id < state.xp.level ? ICONS.check : ICONS.play;
            const side = i % 2 === 0 ? 'side-left' : 'side-right';

            const node = el('button', `map-node ${status} ${side}${item.id === selectedLevel() ? ' is-selected' : ''}`);
            node.type = 'button';
            node.style.left = layout.points[i].left;
            node.style.top = layout.points[i].top;
            node.setAttribute('aria-label', `Level ${item.id}, ${item.name}${unlocked ? '' : ', terkunci'}`);
            node.setAttribute('aria-pressed', String(item.id === selectedLevel()));

            const dot = el('span', 'map-dot');
            dot.innerHTML = icon;
            const label = el('span', 'map-label');
            label.append(el('b', '', `Level ${item.id}`), el('small', '', item.name));
            node.append(dot, label);
            node.addEventListener('click', () => { state.selected = item.id; renderMap(); renderDetail(); });
            scene.appendChild(node);
        });

        const tip = el('div', 'map-tip');
        const tipIcon = el('span', 'map-tip-icon', '💡');
        tipIcon.setAttribute('aria-hidden', 'true');
        tip.append(tipIcon, el('span', '', 'Kumpulkan EXP dari jawaban benar untuk membuka level berikutnya!'));

        if (vertical) map.append(head, scene, tip);
        else { scene.append(head, tip); map.appendChild(scene); }
    }

    function renderDetail() {
        const box = $('quizLevelDetail');
        if (!box) return;
        box.innerHTML = '';
        const item = levelInfo(selectedLevel());
        const unlocked = item.id <= state.xp.level;
        const stats = state.levels[item.id];

        const main = el('div', 'level-detail-main');
        const chips = el('div', 'level-detail-chips');
        chips.append(
            el('span', '', `+${xpPerCorrect(item.id)} EXP / jawaban benar`),
            el('span', '', `Bonus 10/10: +${CONFIG.perfectBonus}`),
        );
        if (!unlocked) chips.appendChild(el('span', 'is-locked', `🔒 Butuh ${fmt(item.xp_required)} EXP (kurang ${fmt(item.xp_required - state.xp.xp)})`));
        else if (stats) chips.appendChild(el('span', '', `Terbaik ${stats.best_score}% · ${stats.attempts}x main`));
        else chips.appendChild(el('span', '', 'Belum dimainkan'));
        main.append(el('h4', '', `Level ${item.id} · ${item.name}`), el('p', '', item.description), chips);

        const start = el('button', 'quiz-btn primary', unlocked ? `Mulai Level ${item.id}` : 'Terkunci');
        start.type = 'button';
        start.disabled = !unlocked;
        start.addEventListener('click', () => startLevel(item.id));

        box.append(el('div', 'level-detail-icon', item.icon), main, start);
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

    /* -------------------------------------------------- kode pemulihan progres */
    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ ...payload, date: todayKey() }),
        });
        let data = {};
        try { data = await response.json(); } catch (error) { /* respons bukan JSON */ }
        return { ok: response.ok, status: response.status, data };
    }

    function recoveryMessage(text, isError = false) {
        const box = $('recoveryMessage');
        box.textContent = text;
        box.classList.toggle('is-error', isError);
    }

    function failureText(result, fallback) {
        if (result.status === 429) return 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.';
        return result.data?.message || fallback;
    }

    function renderRecovery() {
        const status = $('recoveryStatus');
        if (!status) return;
        const { has_code: hasCode, created_at: createdAt } = state.recovery;
        const codeVisible = !$('recoveryCodeBox').classList.contains('quiz-hidden');
        if (codeVisible) {
            status.textContent = 'Ini kode aktifmu. Kode sebelumnya (jika ada) sudah tidak berlaku.';
        } else if (hasCode) {
            const when = createdAt ? new Date(createdAt.replace(' ', 'T')) : null;
            const date = when && !Number.isNaN(when.getTime()) ? when.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '';
            status.textContent = `Kode sudah dibuat${date ? ` pada ${date}` : ''}. Demi keamanan kode tidak bisa ditampilkan lagi. Lupa? Buat kode baru (kode lama tidak berlaku).`;
        } else {
            status.textContent = 'Belum ada kode. Buat satu untuk mencadangkan level dan EXP-mu.';
        }
        $('recoveryCreate').textContent = hasCode ? 'Buat kode baru' : 'Buat kode pemulihan';
    }

    async function createRecoveryCode() {
        if (state.recovery.has_code && !window.confirm('Kode lama tidak akan berlaku lagi. Buat kode baru?')) return;
        const button = $('recoveryCreate');
        button.disabled = true;
        recoveryMessage('Membuat kode…');
        try {
            const result = await postJson(CONFIG.routes.recoveryCreate, {});
            if (!result.ok) { recoveryMessage(failureText(result, 'Kode belum bisa dibuat.'), true); return; }
            applyProgress(result.data);
            $('recoveryCode').textContent = result.data.code;
            show('recoveryCodeBox'); show('recoveryWarn');
            recoveryMessage(result.data.message || 'Kode pemulihan dibuat.');
            renderRecovery();
        } catch (error) {
            recoveryMessage('Koneksi bermasalah. Coba lagi.', true);
        } finally {
            button.disabled = false;
        }
    }

    async function copyRecoveryCode() {
        const text = $('recoveryCode').textContent;
        try {
            await navigator.clipboard.writeText(text);
            recoveryMessage('Kode disalin. Simpan di tempat yang aman.');
        } catch (error) {
            const range = document.createRange();
            range.selectNodeContents($('recoveryCode'));
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            recoveryMessage('Kode terpilih. Tekan Ctrl+C (atau tahan lalu salin) untuk menyalin.');
        }
    }

    async function redeemRecoveryCode() {
        const code = $('recoveryInput').value.trim();
        if (!code) { recoveryMessage('Masukkan kode pemulihanmu dulu.', true); return; }
        const button = $('recoveryRedeem');
        button.disabled = true;
        recoveryMessage('Memeriksa kode…');
        try {
            const result = await postJson(CONFIG.routes.recoveryRedeem, { code });
            if (!result.ok) { recoveryMessage(failureText(result, 'Kode belum bisa dipakai.'), true); return; }
            applyProgress(result.data);
            state.selected = null;
            $('recoveryInput').value = '';
            hide('recoveryCodeBox'); hide('recoveryWarn');
            renderHome();
            const { level, xp } = result.data.xp;
            recoveryMessage(result.data.already
                ? 'Progres ini sudah aktif di browser ini.'
                : `Progres dipulihkan: Level ${level} · ${fmt(xp)} EXP.`);
        } catch (error) {
            recoveryMessage('Koneksi bermasalah. Coba lagi.', true);
        } finally {
            button.disabled = false;
        }
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
        state.selected = null;
        renderHome();
        state.saving = false;
    }

    function backToLevels() {
        state.saving = false;
        hide('quizPlay'); hide('quizResult'); show('quizLevelSelect');
        $('quizCorrectCount').textContent = '0';
        $('quizAnsweredCount').textContent = `0/${PER_ROUND}`;
        state.selected = null;
        loadProgress();
    }

    /* ----------------------------------------------------------------- init */
    $('quizNext').addEventListener('click', () => {
        if (!state.answered) return;
        if (state.index < state.questions.length - 1) { state.index++; renderQuestion(); } else finishQuiz();
    });
    $('quizBackToLevels').addEventListener('click', backToLevels);
    $('recoveryCreate').addEventListener('click', createRecoveryCode);
    $('recoveryCopy').addEventListener('click', copyRecoveryCode);
    $('recoveryRedeem').addEventListener('click', redeemRecoveryCode);
    $('recoveryInput').addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); redeemRecoveryCode(); } });

    if (mobileQuery.addEventListener) mobileQuery.addEventListener('change', renderMap);
    renderHome();
    loadProgress();
})();
