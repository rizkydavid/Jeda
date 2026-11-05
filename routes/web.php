<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JedaFeatureController;

/* Halaman utama Jeda */
Route::view('/', 'pages.home')->name('home');
Route::view('/cek', 'pages.cek')->name('cek');
Route::view('/latihan', 'pages.simulasi')->name('simulasi');
Route::view('/jam-emas', 'pages.jam-emas')->name('jam-emas');
Route::view('/keluarga', 'pages.keluarga')->name('keluarga');
Route::view('/kartu', 'pages.kartu')->name('kartu');
Route::view('/batasan', 'pages.batasan')->name('batasan');

/* API internal untuk menyimpan hasil dan progres fitur. */
Route::prefix('jeda/api')->name('jeda.api.')->group(function () {
    Route::post('/analisis-pesan', [JedaFeatureController::class, 'analyzeMessage'])->name('analyze-message');
    Route::post('/bedah-ai', [JedaFeatureController::class, 'analyzeMessageWithAi'])->middleware('throttle:10,1')->name('bedah-ai');
    Route::post('/cek-tautan', [JedaFeatureController::class, 'checkLink'])->name('check-link');
    Route::post('/cek-file', [JedaFeatureController::class, 'checkFile'])->name('check-file');
    Route::post('/simulasi', [JedaFeatureController::class, 'saveSimulation'])->name('simulation');
    Route::get('/quiz-progress', [JedaFeatureController::class, 'quizProgress'])->name('quiz-progress');
    Route::post('/quiz-attempt', [JedaFeatureController::class, 'saveQuizAttempt'])->name('quiz-attempt');
    Route::post('/checklist', [JedaFeatureController::class, 'saveChecklist'])->name('checklist');
    Route::post('/aksi-bantuan', [JedaFeatureController::class, 'supportAction'])->name('support-action');
    Route::get('/progres', [JedaFeatureController::class, 'progress'])->name('progress');
});