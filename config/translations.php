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

];
