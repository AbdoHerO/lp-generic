<?php
// ---------------------------------------------------------------
// EXAMPLE CONFIG — copy this to config.local.php (local dev)
//                  or config.prod.php (production)
// DO NOT put real credentials in this file.
// ---------------------------------------------------------------
return [
    'app' => [
        'name'      => 'متجر تيفاو',
        'base_url'  => '/your-subfolder',   // '' if site is at domain root
        'env'       => 'development',        // 'production' to hide errors
        'timezone'  => 'Africa/Casablanca',
    ],
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'your_db_name',
        'user'    => 'your_db_user',
        'pass'    => 'your_db_password',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'session_name'  => 'LPTIFAW_SESS',
        'cookie_secure' => false,   // set true if site runs over HTTPS
    ],
];
