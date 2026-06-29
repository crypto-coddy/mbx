<?php

return [

    'key' => env('TWELVE_DATA_API_KEY'),

    /*
    | Plan tier (basic | grow | pro | ultra). Grow ≈ 377 credits/minute.
    */
    'plan' => env('TWELVE_DATA_PLAN', 'grow'),

    'plans' => [
        'basic' => [
            'credits_per_minute' => 8,
            'daily_credit_cap' => 800,
            'quote_chunk_size' => 8,
            'chunk_delay_seconds' => 8,
        ],
        'grow' => [
            'credits_per_minute' => 377,
            'daily_credit_cap' => null,
            'quote_chunk_size' => 12,
            'chunk_delay_seconds' => 0,
        ],
        'pro' => [
            'credits_per_minute' => 1597,
            'daily_credit_cap' => null,
            'quote_chunk_size' => 12,
            'chunk_delay_seconds' => 0,
        ],
        'ultra' => [
            'credits_per_minute' => 10946,
            'daily_credit_cap' => null,
            'quote_chunk_size' => 12,
            'chunk_delay_seconds' => 0,
        ],
    ],

    /** Mobile HTTP never calls Twelve Data — only reads cache populated by jobs. */
    'mobile_http_cache_only' => env('TWELVE_DATA_MOBILE_CACHE_ONLY', true),

    'quote_cache_ttl_seconds' => (int) env('TWELVE_DATA_QUOTE_CACHE_TTL', 60),
    'mobile_quote_max_age_seconds' => (int) env('TWELVE_DATA_MOBILE_QUOTE_MAX_AGE', 45),
    'mobile_series_max_age_seconds' => (int) env('TWELVE_DATA_MOBILE_SERIES_MAX_AGE', 120),
    'series_cache_ttl_seconds' => (int) env('TWELVE_DATA_SERIES_CACHE_TTL', 300),

    'credit_cost' => [
        'quote' => 1,
        'time_series' => 1,
    ],

    'credit_reserve_per_minute' => (int) env('TWELVE_DATA_CREDIT_RESERVE', 40),

];
