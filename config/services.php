<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Anthropic (Claude) — pemeriksaan tugas & kuis otomatis oleh AI.
    | Kosongkan ANTHROPIC_API_KEY untuk menonaktifkan fitur AI (dosen menilai manual).
    */
    'anthropic' => [
        'key'       => env('ANTHROPIC_API_KEY'),
        'model'     => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        'base_url'  => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version'   => env('ANTHROPIC_VERSION', '2023-06-01'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 1500),
        'auto_grade_tugas' => (bool) env('AI_AUTO_GRADE_TUGAS', true),
        'auto_review_kuis' => (bool) env('AI_AUTO_REVIEW_KUIS', true),
    ],

    'ai' => [
        // penyedia aktif untuk koreksi otomatis: 'anthropic' (Claude) atau 'openai' (ChatGPT)
        'provider' => env('AI_PROVIDER', 'anthropic'),
    ],

    'openai' => [
        'key'        => env('OPENAI_API_KEY'),
        'model'      => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url'   => env('OPENAI_BASE_URL', 'https://api.openai.com'),
        // kirim lampiran PDF mahasiswa ke OpenAI (hanya sebagian model mendukung)
        'send_pdf'   => (bool) env('OPENAI_SEND_PDF', false),
    ],

];
