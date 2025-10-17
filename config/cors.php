<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Accept incoming cross-origin requests. Adjust the allowed_origins to
    | match your front-end domains (include port for dev: localhost:3000).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Change to specific origins in production, include port for dev:
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // If you use cookie-based auth (Sanctum), set to true:
    'supports_credentials' => true,

    // Optional:
    'exposed_headers' => [],
    'max_age' => 0,
];
