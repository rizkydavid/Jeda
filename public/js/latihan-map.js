/**
 * Peta perjalanan Latihan Jeda.
 * Hanya bertugas menggambar (header EXP + peta 10 level + animasinya); aturan EXP,
 * soal, dan penyimpanan tetap ada di latihan.js. Dipanggil lewat JedaMap.render().
 */
(() => {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';
    const LOCK = '<svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';
    const CHECK = '<svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
    const PLAY = '<svg viewBox="0 0 24 24" style="fill:currentColor;stroke:none"><path d="M9 6.5v11l9-5.5z"/></svg>';

    // Koordinat node dalam viewBox peta (desktop 1400x612, ponsel 400x1190).
    const LAYOUTS = {
        desktop: {
            w: 1400, h: 612, island: 15,
            pts: [[126, 268], [303, 215], [493, 217], [652, 125], [834, 180], [980, 89], [1140, 189], [1219, 337], [991, 430], [735, 430]],
            goal: [1262, 86],
        },
        mobile: {
            w: 400, h: 1190, island: 40,
            pts: [[110, 120], [290, 225], [120, 330], [285, 435], [125, 540], [280, 645], [120, 750], [285, 855], [125, 960], [270, 1065]],
            goal: [96, 1140],
        },
    };

    const mq = window.matchMedia('(max-width: 860px)');
    const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const fmt = (n) => Number(n).toLocaleString('id-ID');
    let last = null;       // argumen render terakhir, dipakai saat ukuran layar berganti
    let travel = 0;        // id requestAnimationFrame penanda yang sedang berjalan

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function svg(tag, attrs = {}) {
        const node = document.createElementNS(NS, tag);
        Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, v));
        return node;
    }

    /** Kurva halus Catmull-Rom yang dipecah jadi satu segmen Bezier per pasangan level. */
    function segments(pts) {
        return pts.slice(0, -1).map((p1, i) => {
            const p0 = pts[i - 1] || p1;
            const p2 = pts[i + 1];
            const p3 = pts[i + 2] || p2;
            const c1 = [p1[0] + (p2[0] - p0[0]) / 6, p1[1] + (p2[1] - p0[1]) / 6];
            const c2 = [p2[0] - (p3[0] - p1[0]) / 6, p2[1] - (p3[1] - p1[1]) / 6];
            return `M${p1[0]} ${p1[1]} C${c1[0]} ${c1[1]} ${c2[0]} ${c2[1]} ${p2[0]} ${p2[1]}`;
        });
    }

    /* ------------------------------------------------------------ header */
    function renderHeader(ctx) {
        const { xp, levels, stats } = ctx;
        const current = levels[xp.level - 1];
        const stat = document.getElementById('jmStats');
        const mission = document.getElementById('jmMission');
        stat.textContent = '';

        const top = el('div', 'jm-xp-top');
        const text = el('div');
        const total = el('strong', '', '0 EXP');
        text.append(total, el('span', '', `Level ${xp.level} • ${current.name}`));
        top.append(el('span', 'jm-star', '⭐'), text);

        const bar = el('div', 'jm-bar');
        const fill = el('span');
        bar.appendChild(fill);
        const span = xp.is_max ? 0 : xp.next_level_xp - xp.level_start;
        stat.append(top, bar, el('small', '', xp.is_max ? 'Level maksimal tercapai' : `${fmt(xp.xp_into_level)} / ${fmt(span)} EXP menuju Level ${xp.level + 1}`));

        // Isi bar dan hitung naik angka EXP.
        requestAnimationFrame(() => requestAnimationFrame(() => { fill.style.width = `${xp.percent}%`; }));
        const started = performance.now();
        const duration = reduced() ? 0 : 1300;
        const tick = (now) => {
            const t = duration ? Math.min((now - started) / duration, 1) : 1;
            total.textContent = `${fmt(Math.round(xp.xp * (1 - Math.pow(1 - t, 3))))} EXP`;
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);

        const rounds = Object.values(stats || {}).reduce((sum, s) => sum + (s?.rounds_today || 0), 0);
        mission.textContent = '';
        const box = el('div');
        box.append(el('strong', '', `${rounds} ronde`), el('span', '', 'Misi Hari Ini'));
        mission.append(el('span', 'jm-cal', '📅'), box);
    }

    /* -------------------------------------------------------------- peta */
    function decorate(stage, layout) {
        const rnd = (a, b) => a + Math.random() * (b - a);
        for (let i = 0; i < 26; i++) {
            const s = el('span', 'jm-spark');
            s.style.cssText = `left:${rnd(2, 98)}%;top:${rnd(2, 60)}%;--s:${rnd(1.5, 3.5).toFixed(1)}px;--t:${rnd(2.4, 5).toFixed(1)}s;--d:${rnd(0, 4).toFixed(1)}s`;
            stage.appendChild(s);
        }
        [[7, 0.2, 58, .5, 0], [10, .15, 70, .42, 18], [5, .22, 52, .55, 34], [8, .12, 84, .38, 8]].forEach(([top, w, t, o, d]) => {
            const c = el('div', 'jm-cloud');
            c.style.cssText = `top:${top + Math.random() * 40}%;--w:${w * (layout.w > 500 ? 100 : 230)}cqw;--t:${t}s;--o:${o};--d:-${d}s`;
            c.appendChild(el('i'));
            stage.appendChild(c);
        });
        for (let i = 0; i < 9; i++) {
            const f = el('span', 'jm-firefly');
            f.style.cssText = `left:${rnd(4, 94)}%;top:${rnd(18, 92)}%;--x:${rnd(-30, 30).toFixed(0)}px;--y:${rnd(-30, 30).toFixed(0)}px;--t:${rnd(3, 6).toFixed(1)}s;--d:-${rnd(0, 5).toFixed(1)}s`;
            stage.appendChild(f);
        }
    }

    function renderMap(ctx) {
        const map = document.getElementById('jmMap');
        cancelAnimationFrame(travel);
        map.textContent = '';
        const isMobile = mq.matches;
        const layout = isMobile ? LAYOUTS.mobile : LAYOUTS.desktop;
        map.classList.toggle('is-mobile', isMobile);

        const stage = el('div', 'jm-stage');
        map.appendChild(stage);
        decorate(stage, layout);

        const pct = (p) => [(p[0] / layout.w) * 100, (p[1] / layout.h) * 100];
        const instant = reduced();
        const STEP = 0.38;                                   // detik per segmen
        const level = ctx.xp.level;

        // Pulau melayang di bawah tiap node + markas tujuan.
        layout.pts.forEach((p, i) => {
            const [x, y] = pct(p);
            const island = el('div', 'jm-island');
            island.style.cssText = `left:${x}%;top:${y + (isMobile ? 4.2 : 9)}%;--w:${layout.island};--t:${6 + (i % 4)}s;--d:-${(i * 1.3).toFixed(1)}s`;
            stage.appendChild(island);
        });
        const [gx, gy] = pct(layout.goal);
        const goal = el('div', 'jm-goal', '🏰');
        goal.style.cssText = `left:${gx}%;top:${gy}%`;
        stage.appendChild(goal);

        // Jalur: dasar putus-putus + bagian yang sudah ditempuh menyala dan mengalir.
        const canvas = svg('svg', { class: 'jm-svg', viewBox: `0 0 ${layout.w} ${layout.h}`, preserveAspectRatio: 'none', 'aria-hidden': 'true' });
        const segs = segments(layout.pts);
        const lit = [];
        segs.forEach((d, i) => {
            canvas.appendChild(svg('path', { d, class: 'jm-seg jm-seg--base' }));
            if (i + 2 <= level) {
                const delay = instant ? 0 : i * STEP;
                const style = `--delay:${delay}s`;
                const flow = svg('path', { d, class: 'jm-seg jm-seg--flow', pathLength: 1, style });
                const glow = svg('path', { d, class: 'jm-seg jm-seg--lit', pathLength: 1, style });
                canvas.append(glow, flow);
                lit.push(glow);
            }
        });
        stage.appendChild(canvas);

        // Node level.
        ctx.levels.forEach((info, i) => {
            const id = i + 1;
            const state = id < level ? 'is-done' : id === level ? 'is-current' : 'is-locked';
            const best = ctx.stats[id]?.best_score;
            const node = el('button', `jm-node ${state}`);
            node.type = 'button';
            node.style.cssText = `left:${pct(layout.pts[i])[0]}%;top:${pct(layout.pts[i])[1]}%;--delay:${instant ? 0 : (0.15 + Math.max(0, id - 1) * STEP * (id <= level ? 1 : 0.25)).toFixed(2)}s`;
            const need = info.xp_required - ctx.xp.xp;
            node.setAttribute('aria-label', `Level ${id}, ${info.name}. ${state === 'is-locked' ? `Terkunci, butuh ${fmt(need)} EXP lagi` : best != null ? `Skor terbaik ${best}%` : 'Siap dimainkan'}`);
            node.title = state === 'is-locked' ? `🔒 Butuh ${fmt(info.xp_required)} EXP` : info.description;
            if (state === 'is-locked') node.setAttribute('aria-disabled', 'true');

            const orb = el('span', 'jm-orb');
            orb.innerHTML = state === 'is-locked' ? LOCK : state === 'is-done' ? CHECK : PLAY;
            const label = el('span', 'jm-label');
            label.append(el('span', 'jm-lv', `Level ${id}`), el('span', '', info.name));
            node.append(orb, label);

            node.addEventListener('click', () => {
                if (state !== 'is-locked') return ctx.onSelect(id);
                node.classList.remove('is-shake');
                void node.offsetWidth;                       // mulai ulang animasi getar
                node.classList.add('is-shake');
                ctx.onLocked(id, need);
            });
            stage.appendChild(node);
        });

        // Gelembung "Mulai di sini" + detektif.
        const cur = pct(layout.pts[level - 1]);
        const callout = el('div', 'jm-callout', level === 1 ? 'Mulai di sini!' : 'Lanjut di sini');
        callout.style.cssText = `left:${cur[0]}%;top:calc(${cur[1]}% - 3.6em);--delay:${instant ? 0 : (0.4 + (level - 1) * STEP).toFixed(2)}s`;
        stage.appendChild(callout);

        const hero = el('div', 'jm-hero', '');
        hero.appendChild(el('span', '', '🕵️'));
        stage.appendChild(hero);
        const place = (p) => { const q = pct(p); hero.style.left = `${q[0]}%`; hero.style.top = `${q[1]}%`; };
        place(layout.pts[0]);
        if (instant || lit.length === 0) { place(layout.pts[level - 1]); hero.classList.add('is-idle'); return; }

        // Detektif berjalan menyusuri jalur menyala sampai level saat ini.
        const lengths = lit.map((p) => p.getTotalLength());
        const total = lit.length * STEP * 1000;
        const t0 = performance.now() + 150;
        const step = (now) => {
            const t = Math.max(0, Math.min((now - t0) / total, 1));
            const f = t * lit.length;
            const idx = Math.min(Math.floor(f), lit.length - 1);
            const pt = lit[idx].getPointAtLength(lengths[idx] * (f - idx));
            place([pt.x, pt.y]);
            if (t < 1) travel = requestAnimationFrame(step); else hero.classList.add('is-idle');
        };
        travel = requestAnimationFrame(step);
    }

    function render(ctx) {
        last = ctx;
        renderHeader(ctx);
        renderMap(ctx);
    }

    // Susun ulang peta saat layar berpindah antara ponsel dan desktop.
    mq.addEventListener?.('change', () => { if (last && document.getElementById('jmMap')?.offsetParent) renderMap(last); });

    window.JedaMap = { render };
})();
