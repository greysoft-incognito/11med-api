<?php

// Resend's Laravel mailer (`MAIL_MAILER=resend`). Its webhook routes stay off: nothing here reads delivery events.
return [

    'api_key' => env('RESEND_API_KEY'),

    'routes' => env('RESEND_ROUTES', false),

    'domain' => env('RESEND_DOMAIN'),

    'path' => env('RESEND_PATH', 'resend'),

    'webhook' => [
        'secret' => env('RESEND_WEBHOOK_SECRET'),
        'tolerance' => env('RESEND_WEBHOOK_TOLERANCE', 300),
    ],

    'middleware' => [],

];
