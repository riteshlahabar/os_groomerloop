<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | D-006 splits the product into a Laravel API and a React SPA, and D-010 authenticates
    | that SPA with a session cookie. Both facts constrain this file:
    |
    | Laravel's default allowed_origins of ['*'] must not survive. A wildcard origin is
    | rejected outright by browsers once credentials are involved, so cookie auth would
    | simply fail — and on any endpoint that did work it would invite every site on the
    | internet to call this API with the visitor's cookies attached.
    |
    | So the allowed origin is exactly the configured frontend, and credentials are enabled.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL'),
        env('APP_URL'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
        'X-CSRF-TOKEN',
    ],

    // Let the SPA read its own rate-limit budget so it can back off rather than hammer.
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    'max_age' => 600,

    'supports_credentials' => true,

];
