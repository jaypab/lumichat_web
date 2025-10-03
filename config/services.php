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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    
    'crisis' => [
    'emergency_number' => env('CRISIS_EMERGENCY_NUMBER', '911'),
    'hotline_name'     => env('CRISIS_HOTLINE_NAME', '988 Suicide & Crisis Lifeline'),
    'hotline_phone'    => env('CRISIS_HOTLINE_PHONE', '988'),
    'hotline_text'     => env('CRISIS_HOTLINE_TEXT', 'Text HOME to 741741'),
    'hotline_url'      => env('CRISIS_HOTLINE_URL', 'https://988lifeline.org/'),
    ],
    
    'rasa' => [
        'base_url' => env('RASA_BASE_URL', 'http://localhost:5005'),
        'rest_path' => env('RASA_REST_PATH', '/webhooks/rest/webhook'),
    ],

];
