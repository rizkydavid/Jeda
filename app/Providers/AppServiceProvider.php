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
        // Batas pemeriksaan tautan per IP: server membuka tautan dan memakai API gratis berkuota.
        RateLimiter::for('jeda-cek', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        // Penjelasan AI memakai kuota Gemini, jadi dibatasi lebih ketat.
        RateLimiter::for('jeda-ai', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        if (app()->environment('production')) {
    \Illuminate\Support\Facades\URL::forceScheme('https');
}
    }
}
