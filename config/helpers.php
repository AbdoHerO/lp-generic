<?php
// Bootstrap config & sessions
$CONFIG = require __DIR__ . '/config.php';
date_default_timezone_set($CONFIG['app']['timezone']);

if ($CONFIG['app']['env'] === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name($CONFIG['security']['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $CONFIG['security']['cookie_secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function base_url(string $path = ''): string {
    global $CONFIG;
    return rtrim($CONFIG['app']['base_url'], '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string {
    $rel  = 'public/assets/' . ltrim($path, '/');
    $file = __DIR__ . '/../' . $rel;
    $v    = file_exists($file) ? filemtime($file) : 1;
    return base_url($rel) . '?v=' . $v;
}

function upload_url(string $path): string {
    // External URL (from another store or CDN) — return as-is
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return base_url(ltrim($path, '/'));
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $t): bool {
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function input(string $key, $default = null) {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function clean_string($v, int $max = 500): string {
    $v = is_string($v) ? trim($v) : '';
    $v = preg_replace('/\s+/u', ' ', $v) ?? '';
    return mb_substr($v, 0, $max, 'UTF-8');
}

function clean_phone($v): string {
    $v = is_string($v) ? trim($v) : '';
    return preg_replace('/[^0-9+]/', '', $v) ?? '';
}

function settings_get(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $rows = db()->query("SELECT k, v FROM settings")->fetchAll();
        foreach ($rows as $r) $cache[$r['k']] = $r['v'];
    }
    return $cache[$key] ?? $default;
}

function render(string $view, array $data = [], ?string $layout = 'public'): void {
    extract($data, EXTR_SKIP);
    $viewPath = __DIR__ . '/../src/Views/' . $view . '.php';
    if (!file_exists($viewPath)) {
        http_response_code(500);
        echo "View not found: $view";
        return;
    }
    ob_start();
    include $viewPath;
    $content = ob_get_clean();
    if ($layout) {
        include __DIR__ . '/../src/Views/layouts/' . $layout . '.php';
    } else {
        echo $content;
    }
}

function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function not_found(): void {
    http_response_code(404);
    render('404', ['title' => 'الصفحة غير موجودة']);
    exit;
}

function detect_source(): ?string {
    $u = ($_SERVER['HTTP_REFERER'] ?? '') . ' ' . ($_GET['utm_source'] ?? '');
    $q = $_GET;
    if (!empty($q['fbclid']))   return 'facebook';
    if (!empty($q['ttclid']))   return 'tiktok';
    if (!empty($q['gclid']))    return 'google';
    if (!empty($q['utm_source'])) return strtolower((string)$q['utm_source']);
    return null;
}
