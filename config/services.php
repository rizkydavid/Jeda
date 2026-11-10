<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Pemeriksaan tautan (Tahap 1). Key hanya dibaca di server dari .env.
    'safe_browsing' => [
        'key' => env('SAFE_BROWSING_API_KEY'),
    ],

    'virustotal' => [
        'key' => env('VIRUSTOTAL_API_KEY'),
    ],

    // Analisis AI di halaman Bedah (opsional). Isi salah satu key di .env.
    // JEDA_AI_PROVIDER=gemini|anthropic bersifat opsional; kalau kosong dipakai yang key-nya terisi.
    'jeda_ai' => [
        'provider' => env('JEDA_AI_PROVIDER'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
        'thinking' => env('GEMINI_THINKING_LEVEL'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('JEDA_AI_MODEL', 'claude-haiku-5-5'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];