<?php
// ---------------------------------------------------------------
// EXAMPLE CONFIG — copy this to config.local.php (local dev)
//                  or config.prod.php (production)
//
// Secrets are READ, not written: every value below comes from a real
// environment variable, or from config/.env when the host does not provide
// one. That keeps passwords out of this file even on shared hosting where
// they would otherwise sit in plain PHP source.
//
// Copy config/.env.example to config/.env and fill that in instead.
// ---------------------------------------------------------------
require_once __DIR__ . '/env.php';

return [
    'app' => [
        'name'      => env('APP_NAME', 'tujjar.store'),
        // '' if the site is at a domain root, '/your-subfolder' otherwise.
        'base_url'  => (string)env('APP_BASE_URL', ''),
        'env'       => env('APP_ENV', 'production'),   // 'development' shows errors
        'timezone'  => env('APP_TIMEZONE', 'Africa/Casablanca'),
    ],
    'db' => [
        'host'    => env('DB_HOST', '127.0.0.1'),
        'port'    => env_int('DB_PORT', 3306),
        'name'    => env_required('DB_NAME'),
        'user'    => env_required('DB_USER'),
        // env_required, not env: connecting with an empty password because a
        // variable was misspelled fails much later and much less clearly.
        'pass'    => env_required('DB_PASSWORD'),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],
    'security' => [
        'session_name'  => env('SESSION_NAME', 'LPTIFAW_SESS'),
        'cookie_secure' => env_bool('COOKIE_SECURE', true),  // false only over plain HTTP
    ],
];
