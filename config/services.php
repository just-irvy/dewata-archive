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

    /*
    | Gemini API (FILE 17). Kunci hanya dibaca server dan tidak pernah dikirim ke browser.
    | Nama model dibuat dapat diganti lewat .env karena nama model Gemini berganti seiring waktu.
    | Periksa daftar model dan kebijakan kunci terbaru di https://ai.google.dev/gemini-api/docs
    */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 20),
        // Tingkat "thinking" Gemini 3.x: minimal, low, medium, high. Bawaan model adalah medium, yang
        // lambat untuk chat. Kosongkan untuk memakai bawaan model. Diabaikan untuk model gemini-2.x.
        'thinking_level' => env('GEMINI_THINKING_LEVEL', 'low'),
        // Pada model ber-"thinking" (Gemini 3.x), token berpikir ikut menghabiskan batas ini. Batas yang terlalu
        // kecil membuat jawaban terpotong atau kosong (finishReason MAX_TOKENS), yang tampil sebagai
        // "asisten tidak tersedia". Panjang jawaban tetap dibatasi oleh instruksi gaya di ChatPrompt.
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 2048),
    ],

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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
