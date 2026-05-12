<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $cfg = require __DIR__ . '/config.php';
    $d = $cfg['db'];

    // Auto-create the database if it doesn't exist yet
    $metaDsn = "mysql:host={$d['host']};port={$d['port']};charset={$d['charset']}";
    $meta = new PDO($metaDsn, $d['user'], $d['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $meta->exec("CREATE DATABASE IF NOT EXISTS `{$d['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    unset($meta);

    $dsn = "mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset={$d['charset']}";
    $pdo = new PDO($dsn, $d['user'], $d['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    _auto_migrate($pdo);
    return $pdo;
}

/**
 * Run schema.sql + seed.sql automatically the first time the app boots.
 * Safe to call on every request — it only executes when tables are absent.
 */
function _auto_migrate(PDO $pdo): void {
    try {
        $tableExists = $pdo->query("SHOW TABLES LIKE 'admins'")->fetchColumn();
        if (!$tableExists) {
            _exec_sql_file($pdo, __DIR__ . '/../sql/schema.sql');
            _exec_sql_file($pdo, __DIR__ . '/../sql/seed.sql');
        }
    } catch (Throwable $e) {
        // DB might be unreachable; let the real query fail with a clear message
    }
}

function _exec_sql_file(PDO $pdo, string $file): void {
    if (!file_exists($file)) return;
    $sql = file_get_contents($file);

    // Strip single-line comments (-- ...) so they don't confuse the splitter
    $sql = preg_replace('/^--[^\n]*/m', '', $sql);

    // Split on semicolons. For our controlled SQL files this is safe.
    $stmts = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($stmts as $stmt) {
        if (strlen($stmt) > 3) {
            try {
                $pdo->exec($stmt);
            } catch (Throwable $e) {
                // Skip errors (e.g. DROP TABLE on non-existent table on first run)
            }
        }
    }
}
