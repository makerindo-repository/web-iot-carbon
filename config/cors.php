<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Daftar origin diambil dari env CORS_ALLOWED_ORIGINS (comma-separated).
| Empty value = tolak semua cross-origin (fail-secure).
|
| Production wajib isi di .env, contoh:
|   CORS_ALLOWED_ORIGINS=https://agrisense.web.id,https://www.agrisense.web.id
|
| Local dev contoh:
|   CORS_ALLOWED_ORIGINS=http://localhost:5173
|
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'X-Requested-With',
        'X-Cron-Token',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
