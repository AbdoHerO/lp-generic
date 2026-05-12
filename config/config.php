<?php
// ---------------------------------------------------------------
// Environment loader
//   1. config.local.php  → local development  (gitignored)
//   2. config.prod.php   → production server  (gitignored)
//   3. config.example.php → template only, never loaded directly
//
// Copy config.example.php to the right file and fill in your
// credentials. Never commit config.local.php or config.prod.php.
// ---------------------------------------------------------------
$_dir = __DIR__;

if (file_exists($_dir . '/config.local.php')) {
    return require $_dir . '/config.local.php';
}

if (file_exists($_dir . '/config.prod.php')) {
    return require $_dir . '/config.prod.php';
}

// No environment file found — stop with a clear message
http_response_code(503);
echo '<h2>Configuration missing</h2>';
echo '<p>Copy <code>config/config.example.php</code> to ';
echo '<code>config/config.local.php</code> (local) or ';
echo '<code>config/config.prod.php</code> (production) and fill in your credentials.</p>';
exit;
