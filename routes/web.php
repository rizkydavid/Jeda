<?php

use App\Http\Controllers\BedahController;
use App\Http\Controllers\FileCheckController;
use App\Http\Controllers\FileScanController;
use App\Http\Controllers\LinkCheckController;
use App\Http\Controllers\LinkExplanationController;
use App\Http\Controllers\LatihanController;
use App\Http\Controllers\ProgresController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecoveryController;

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

/*
|--------------------------------------------------------------------------
| API internal (simpan hasil & progres)
|--------------------------------------------------------------------------
*/
Route::prefix('jeda/api')->name('jeda.api.')->group(function () {
    Route::controller(BedahController::class)->group(function () {
        Route::post('analisis-pesan', 'analyzeMessage')->middleware('throttle:30,1')->name('analyze-message');
        Route::post('bedah-ai', 'analyzeMessageWithAi')->middleware('throttle:10,1')->name('bedah-ai');
    });

    // Cek tautan membuka tautan dan memakai API berkuota, jadi dibatasi per IP.
    Route::post('cek-tautan', [LinkCheckController::class, 'check'])->middleware('throttle:jeda-cek')->name('check-link');
    // Penjelasan AI bersifat terpisah dari hasil utama: kalau Gemini gagal, hasil pemeriksaan tidak terpengaruh.
    Route::post('cek-tautan/penjelasan', [LinkExplanationController::class, 'explain'])->middleware('throttle:jeda-ai')->name('explain-link');

    // Cek file: di jalur ini isi file tidak diunggah. Hanya ringkasan dan SHA-256 yang dikirim; berbagi kuota VirusTotal dengan cek tautan.
    Route::post('cek-file', [FileCheckController::class, 'check'])->middleware('throttle:jeda-cek')->name('check-file');
    // Pemindaian antivirus opsional: SATU-SATUNYA jalur yang mengirim isi file, hanya setelah pengguna mencentang izin.
    Route::post('cek-file/pindai', [FileScanController::class, 'upload'])->middleware('throttle:jeda-upload')->name('scan-file');
    Route::get('cek-file/pindai/status', [FileScanController::class, 'status'])->middleware('throttle:jeda-upload-status')->name('scan-file-status');

    Route::controller(LatihanController::class)->group(function () {
        Route::post('simulasi', 'saveSimulation')->middleware('throttle:30,1')->name('simulation');
        Route::get('quiz-progress', 'quizProgress')->middleware('throttle:60,1')->name('quiz-progress');
        Route::post('quiz-attempt', 'saveQuizAttempt')->middleware('throttle:30,1')->name('quiz-attempt');
    });

    // Kode pemulihan progres Latihan (pengganti akun). Dibatasi ketat agar kode tidak bisa ditebak.
    Route::controller(RecoveryController::class)->prefix('quiz-recovery')->middleware('throttle:jeda-recovery')->group(function () {
        Route::post('create', 'create')->name('quiz-recovery-create');
        Route::post('redeem', 'redeem')->name('quiz-recovery-redeem');
    });

    // Dipakai lintas modul (checklist Jam Emas/Keluarga/Kartu, aksi bantuan, ringkasan).
    Route::controller(ProgresController::class)->group(function () {
        Route::post('checklist', 'saveChecklist')->middleware('throttle:60,1')->name('checklist');
        Route::post('aksi-bantuan', 'supportAction')->middleware('throttle:30,1')->name('support-action');
        Route::get('progres', 'progress')->middleware('throttle:60,1')->name('progress');
    });
});