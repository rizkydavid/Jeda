<?php

use App\Http\Controllers\BedahController;
use App\Http\Controllers\CekController;
use App\Http\Controllers\LatihanController;
use App\Http\Controllers\ProgresController;
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

    Route::controller(CekController::class)->group(function () {
        Route::post('cek-tautan', 'checkLink')->name('check-link');
        Route::post('cek-file', 'checkFile')->name('check-file');
    });

    Route::controller(LatihanController::class)->group(function () {
        Route::post('simulasi', 'saveSimulation')->name('simulation');
        Route::get('quiz-progress', 'quizProgress')->name('quiz-progress');
        Route::post('quiz-attempt', 'saveQuizAttempt')->name('quiz-attempt');
    });

    // Dipakai lintas modul (checklist Jam Emas/Keluarga/Kartu, aksi bantuan, ringkasan).
    Route::controller(ProgresController::class)->group(function () {
        Route::post('checklist', 'saveChecklist')->name('checklist');
        Route::post('aksi-bantuan', 'supportAction')->name('support-action');
        Route::get('progres', 'progress')->name('progress');
    });
});