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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    'hris' => [
        'url' => env('HRIS_API_URL', 'https://api-center.philrice.gov.ph/api/v2/hris'),
        'token' => env('HRIS_API_TOKEN'),
        'key' => env('HRIS_API_KEY'),
    ],

    'fmis' => [
        'url' => env('FMIS_API_URL', 'https://api-center.philrice.gov.ph/api/v2/fmis'),
        'token' => env('FMIS_API_TOKEN', env('HRIS_API_TOKEN')),
        'key' => env('FMIS_API_KEY', env('HRIS_API_KEY')),
    ],

];
