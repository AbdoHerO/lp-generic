<?php
// Edit these values for your XAMPP setup
return [
    'app' => [
        'name'      => 'متجر تيفاو',
        'base_url'  => '/lp_tifaw',     // path prefix when site lives in htdocs/lp_tifaw
        'env'       => 'development',   // 'production' to hide errors
        'timezone'  => 'Africa/Casablanca',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'lp_tifaw',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'session_name' => 'LPTIFAW_SESS',
        'cookie_secure' => false, // set true if HTTPS
    ],
];
