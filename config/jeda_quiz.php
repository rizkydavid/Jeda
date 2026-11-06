<?php

/**
 * Pengaturan sistem level dan EXP untuk halaman Latihan (kuis).
 *
 * Satu ronde = 10 soal. Level tidak naik otomatis setelah satu ronde:
 * setiap jawaban benar memberi EXP, EXP dikumpulkan lintas ronde, dan
 * level naik ketika total EXP mencapai batas `xp_required` level berikutnya.
 * Level yang sudah terbuka boleh dimainkan ulang kapan saja.
 *
 * EXP per jawaban benar = xp_per_correct_base + (level - 1) * xp_per_correct_step
 * Ronde sempurna (10/10) mendapat tambahan perfect_bonus.
 */
return [
    'questions_per_round' => 10,
    'xp_per_correct_base' => 6,
    'xp_per_correct_step' => 2,
    'perfect_bonus' => 10,

    // xp_required = total EXP kumulatif yang dibutuhkan untuk mencapai level tersebut.
    'levels' => [
        1  => ['name' => 'Pemula', 'icon' => '🌱', 'xp_required' => 0, 'description' => 'Kenali tanda dasar pesan mencurigakan.'],
        2  => ['name' => 'Menengah', 'icon' => '🧭', 'xp_required' => 100, 'description' => 'Nilai konteks, identitas, dan cara verifikasi.'],
        3  => ['name' => 'Mahir', 'icon' => '🛡️', 'xp_required' => 250, 'description' => 'Hadapi modus yang lebih rumit dan manipulatif.'],
        4  => ['name' => 'Ahli', 'icon' => '⚡', 'xp_required' => 450, 'description' => 'Analisis logika social engineering tingkat lanjut.'],
        5  => ['name' => 'Spesialis', 'icon' => '🎯', 'xp_required' => 700, 'description' => 'Deteksi phishing domain dan file berbahaya.'],
        6  => ['name' => 'Pakar Keamanan', 'icon' => '🔑', 'xp_required' => 1000, 'description' => 'Uji ketahanan insting dari jebakan psikologis.'],
        7  => ['name' => 'Pengawas', 'icon' => '👁️', 'xp_required' => 1350, 'description' => 'Verifikasi skema penipuan finansial kompleks.'],
        8  => ['name' => 'Penjaga Jeda', 'icon' => '⏳', 'xp_required' => 1750, 'description' => 'Hadapi simulasi ancaman dan tekanan tinggi.'],
        9  => ['name' => 'Master Siber', 'icon' => '💎', 'xp_required' => 2200, 'description' => 'Selesaikan kasus investigasi modus penipuan.'],
        10 => ['name' => 'Puncak Kedamaian', 'icon' => '👑', 'xp_required' => 2700, 'description' => 'Level tertinggi pertahanan keamanan digital.'],
    ],
];
