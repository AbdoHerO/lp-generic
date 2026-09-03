<?php
/**
 * Deployment health probe.
 *
 * Deliberately standalone: it does not include helpers.php, so it starts no
 * session and writes no session file every 30 seconds. It also reports nothing
 * about the failure — the pipeline reads `docker compose logs` for that, and
 * this endpoint is reachable through the public domain.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$configFile = __DIR__ . '/config/config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    echo 'no config';
    exit;
}

/** @var array{db: array{host:string, port:int, name:string, user:string, pass:string, charset:string}} $config */
$config = require $configFile;
$db = $config['db'];

try {
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    // Proves the schema exists, not merely that MySQL accepted the connection.
    $pdo->query('SELECT 1 FROM settings LIMIT 1');
} catch (Throwable $e) {
    http_response_code(503);
    echo 'db';
    exit;
}

echo 'ok';
