<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Restricts which browser origins may call the JSON API. Native mobile
    | apps do not send an Origin header and are unaffected by these rules.
    | Set CORS_ALLOWED_ORIGINS in .env (comma-separated, no spaces).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        trim(...),
        explode(',', env(
            'CORS_ALLOWED_ORIGINS',
            'http://localhost:8081,http://127.0.0.1:8081,http://localhost:19006,http://127.0.0.1:19006'
        ))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
    ],

    'exposed_headers' => [],

    'max_age' => (int) env('CORS_MAX_AGE', 86400),

    'supports_credentials' => false,

];
