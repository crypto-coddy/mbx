<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Baseline HTTP security headers applied to all responses. CSP policies
    | differ between JSON API routes and the Filament admin (web) panel.
    |
    */

    'enabled' => env('SECURITY_HEADERS_ENABLED', true),

    'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

    'permissions_policy' => env(
        'SECURITY_PERMISSIONS_POLICY',
        'camera=(), microphone=(), geolocation=(), payment=()'
    ),

    'hsts' => [
        'enabled' => env('SECURITY_HSTS_ENABLED', env('APP_ENV') === 'production'),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => env('SECURITY_HSTS_SUBDOMAINS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    */

    'csp' => [
        'api' => env(
            'SECURITY_CSP_API',
            "default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"
        ),

        'admin' => env(
            'SECURITY_CSP_ADMIN',
            implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
                "object-src 'none'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob: https:",
                "font-src 'self' data:",
                "connect-src 'self' ws: wss:",
            ])
        ),

        // Extra CSP sources appended in local dev (Vite HMR, Reverb, Expo web).
        'admin_local_extras' => env(
            'SECURITY_CSP_ADMIN_LOCAL_EXTRAS',
            'http://localhost:5173 http://127.0.0.1:5173 ws://localhost:8080 ws://127.0.0.1:8080'
        ),
    ],

];
