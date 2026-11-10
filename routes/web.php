<?php

use App\Http\Controllers\BedahController;
use App\Http\Controllers\FileCheckController;
use App\Http\Controllers\LinkCheckController;
use App\Http\Controllers\LinkExplanationController;
use App\Http\Controllers\LatihanController;
use App\Http\Controllers\ProgresController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman
|--------------------------------------------------------------------------
*/
Route::view('/', 'pages.home')->name('home');
Route::view('/cek', 'pages.cek')->name('cek');
Route::view('/latihan', 'pages.simulasi')->name('simulasi');
Route::view('/jam-emas', 'pages.jam-emas')->name('jam-emas');
Route::view('/keluarga', 'pages.keluarga')->name('keluarga');
Route::view('/bagikan', 'pages.bagikan')->name('bagikan');
// Cadangan untuk menu Bagikan Android. Normalnya service worker (public/sw.js) menangkap kiriman
// dan isinya tidak pernah sampai ke server. Kalau service worker belum aktif, kiriman sampai di sini:
// isinya DIABAIKAN (tidak dibaca, tidak disimpan) dan pengguna diarahkan ke halaman Bagikan biasa.
// Sengaja Route::post + RedirectController (bukan Route::redirect): Route::redirect mendaftar untuk SEMUA
// method termasuk GET, dan route dengan URI+method sama akan menimpa route halaman di atas.
Route::post('/bagikan', RedirectController::class)
    ->defaults('destination', '/bagikan')
    ->defaults('status', 303)
    ->withoutMiddleware([ValidateCsrfToken::class]);
Route::view('/batasan', 'pages.batasan')->name('batasan');

/*
|--------------------------------------------------------------------------
| API internal (simpan hasil & progres)
|--------------------------------------------------------------------------
*/
Route::prefix('jeda/api')->name('jeda.api.')->group(function () {
    Route::controller(BedahController::class)->group(function () {
        Route::post('analisis-pesan', 'analyzeMessage')->name('analyze-message');
        Route::post('bedah-ai', 'analyzeMessageWithAi')->middleware('throttle:10,1')->name('bedah-ai');
    });

    // Cek tautan membuka tautan dan memakai API berkuota, jadi dibatasi per IP.
    Route::post('cek-tautan', [LinkCheckController::class, 'check'])->middleware('throttle:jeda-cek')->name('check-link');
    // Penjelasan AI bersifat terpisah dari hasil utama: kalau Gemini gagal, hasil pemeriksaan tidak terpengaruh.
    Route::post('cek-tautan/penjelasan', [LinkExplanationController::class, 'explain'])->middleware('throttle:jeda-ai')->name('explain-link');

    // Cek file: isi file tidak diunggah. Hanya ringkasan dan SHA-256 yang dikirim; berbagi kuota VirusTotal dengan cek tautan.
    Route::post('cek-file', [FileCheckController::class, 'check'])->middleware('throttle:jeda-cek')->name('check-file');

    Route::controller(LatihanController::class)->group(function () {
        Route::post('simulasi', 'saveSimulation')->name('simulation');
        Route::get('quiz-progress', 'quizProgress')->name('quiz-progress');
        Route::post('quiz-attempt', 'saveQuizAttempt')->middleware('throttle:30,1')->name('quiz-attempt');
    });

    // Dipakai lintas modul (checklist Jam Emas/Keluarga/Kartu, aksi bantuan, ringkasan).
    Route::controller(ProgresController::class)->group(function () {
        Route::post('checklist', 'saveChecklist')->name('checklist');
        Route::post('aksi-bantuan', 'supportAction')->name('support-action');
        Route::get('progres', 'progress')->name('progress');
    });
});