<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Batas pemeriksaan tautan/file per IP: server membuka tautan dan memakai API gratis berkuota.
        // Angkanya dari config (bukan env() langsung) supaya tetap jalan setelah `php artisan config:cache`.
        RateLimiter::for('jeda-cek', fn (Request $request) => [
            Limit::perMinute((int) config('jeda_cek.rate_limits.per_minute', 10))->by($request->ip()),
            Limit::perHour((int) config('jeda_cek.rate_limits.per_hour', 120))->by($request->ip()),
        ]);
        // Pemindaian antivirus mengirim isi file dan memakai kuota VirusTotal, jadi jauh lebih ketat.
        RateLimiter::for('jeda-upload', fn (Request $request) => [
            Limit::perMinute((int) config('jeda_cek.file_upload.per_minute', 3))->by($request->ip()),
            Limit::perHour((int) config('jeda_cek.file_upload.per_hour', 15))->by($request->ip()),
        ]);
        // Status dipanggil berulang selama menunggu hasil (tiap ±20 detik).
        RateLimiter::for('jeda-upload-status', fn (Request $request) => Limit::perMinute(12)->by($request->ip()));
        // Penjelasan AI memakai kuota Gemini, jadi dibatasi lebih ketat.
        RateLimiter::for('jeda-ai', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        if (app()->environment('production')) {
    \Illuminate\Support\Facades\URL::forceScheme('https');
}
    }
}
