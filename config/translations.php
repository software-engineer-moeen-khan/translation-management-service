<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Maximum requests per minute. The API limit is applied per authenticated
    | user (or per IP for anonymous traffic); the login limit is applied per
    | email + IP pair to slow down credential stuffing.
    |
    */

    'rate_limits' => [
        'api' => (int) env('API_RATE_LIMIT', 300),
        'login' => (int) env('LOGIN_RATE_LIMIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Page sizes for list endpoints. The maximum bounds the work a single
    | request can ask the database to do.
    |
    */

    'pagination' => [
        'default' => 25,
        'max' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON Export
    |--------------------------------------------------------------------------
    |
    | "public" drops the token requirement on the export endpoint so that it
    | can be served through a CDN. "cdn_max_age" is how long (in seconds) a
    | shared cache may reuse a response without asking the origin; at 0 the
    | CDN revalidates every request with a cheap conditional GET, so clients
    | always receive the latest translations.
    |
    | "cache_ttl" is how long (in seconds) a built payload stays in the
    | application cache. Entries are keyed by export version, so this only
    | controls clean-up of superseded payloads. Set it to 0 to disable.
    |
    */

    'export' => [
        'public' => (bool) env('TRANSLATIONS_EXPORT_PUBLIC', false),
        'cdn_max_age' => (int) env('TRANSLATIONS_EXPORT_CDN_MAX_AGE', 0),
        'cache_ttl' => (int) env('TRANSLATIONS_EXPORT_CACHE_TTL', 3600),
    ],

];
