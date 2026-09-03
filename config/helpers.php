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

// Safety net for the hand-written require_once calls in the controllers: a
// model that gains a new dependency should not fail on one page and work on
// another.
require_once __DIR__ . '/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    // A Secure cookie on a plain-HTTP request is never stored by the browser,
    // so every request looks logged-out and the admin login "does nothing" with
    // no error anywhere. It is one of the least diagnosable failures in this
    // stack, so say it out loud in the log rather than leaving it silent.
    if ($CONFIG['security']['cookie_secure'] && !request_is_https()) {
        error_log('tujjar.store: cookie_secure is on but this request is plain HTTP — '
                . 'sessions will not persist. Set COOKIE_SECURE=false for local/HTTP use.');
    }

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

/**
 * Whether the visitor's connection is HTTPS.
 *
 * X-Forwarded-Proto is trusted because in production the only thing that can
 * reach this application is the CloudForge-managed Nginx on loopback — the
 * container publishes no other route in. It is read for diagnostics only, never
 * to decide whether to weaken a cookie flag.
 */
function request_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if (($_SERVER['SERVER_PORT'] ?? '') == 443) return true;
    $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $proto === 'https';
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

/**
 * Brand logo. `$light` returns the variant made for dark backgrounds
 * (footer, admin sidebar); it falls back to the main logo when unset.
 */
function store_logo_url(bool $light = false): ?string {
    $key = $light ? 'store_logo_light' : 'store_logo';
    $v = trim((string)settings_get($key, ''));
    if ($v === '' && $light) $v = trim((string)settings_get('store_logo', ''));
    return $v === '' ? null : upload_url($v);
}

function store_favicon_url(): string {
    $v = trim((string)settings_get('store_favicon', ''));
    return upload_url($v !== '' ? $v : 'public/assets/img/favicon.svg');
}

/**
 * Per-installation secret, generated on first use and stored in settings.
 *
 * Used to sign values that leave the server and must come back unmodified —
 * currently the order form's render timestamp. It is not a session key and not
 * a password: rotating it only invalidates in-flight form tokens.
 */
function app_secret(): string {
    static $secret = null;
    if ($secret !== null) return $secret;

    $secret = (string)settings_get('app_secret', '');
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        try {
            db()->prepare("INSERT INTO settings (k,v) VALUES ('app_secret', :v)
                           ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute([':v' => $secret]);
        } catch (Throwable $e) {
            error_log('app_secret persist failed: ' . $e->getMessage());
        }
    }
    return $secret;
}

// form_token() / form_token_check() — they need only app_secret(), so they
// live apart and stay testable without a database.
require_once __DIR__ . '/tokens.php';

/**
 * A responsive <img> for an uploaded or external image.
 *
 * Emits srcset/sizes when resized copies exist, and always sets width/height so
 * the browser reserves the space — the single biggest cause of layout shift on
 * these pages was images arriving late and pushing the order form down.
 *
 * @param array $attrs alt, class, sizes, loading, fetchpriority
 */
function responsive_img(?string $path, array $attrs = []): string {
    if (!$path) $path = 'public/assets/img/placeholder.svg';

    require_once __DIR__ . '/../src/Models/Image.php';

    $alt     = $attrs['alt']     ?? '';
    $class   = $attrs['class']   ?? '';
    $sizes   = $attrs['sizes']   ?? '(max-width: 700px) 100vw, 700px';
    $loading = $attrs['loading'] ?? 'lazy';

    $out = '<img src="' . e(upload_url($path)) . '"';
    if ($class !== '') $out .= ' class="' . e($class) . '"';
    $out .= ' alt="' . e($alt) . '"';

    if ($srcset = Image::srcset($path)) {
        $out .= ' srcset="' . e($srcset) . '" sizes="' . e($sizes) . '"';
    }
    if ($dim = Image::dimensions($path)) {
        $out .= ' width="' . $dim['w'] . '" height="' . $dim['h'] . '"';
    }

    // The hero image is what the shopper waits for; everything else can wait.
    $out .= ' loading="' . e($loading) . '"';
    if ($loading === 'eager') $out .= ' fetchpriority="high"';
    else                      $out .= ' decoding="async"';

    return $out . '>';
}

/**
 * Moroccan cities, for the order form's datalist.
 *
 * The address was one free-text field and `city` was submitted empty, which is
 * why the SheetDB sync wrote "-" for every ville. A datalist keeps the field
 * free-text — a shopper in a village not on this list can still type it — while
 * making the common cases consistent enough to filter and route by.
 */
function morocco_cities(): array {
    return [
        'الدار البيضاء', 'الرباط', 'سلا', 'فاس', 'مراكش', 'طنجة', 'أكادير', 'مكناس',
        'وجدة', 'القنيطرة', 'تطوان', 'آسفي', 'المحمدية', 'الجديدة', 'بني ملال',
        'تازة', 'الناظور', 'سطات', 'برشيد', 'خريبكة', 'العرائش', 'خنيفرة',
        'الخميسات', 'كلميم', 'العيون', 'الداخلة', 'ورزازات', 'الرشيدية', 'تارودانت',
        'الصويرة', 'الحسيمة', 'أزرو', 'إفران', 'سيدي قاسم', 'سيدي سليمان',
        'الفقيه بن صالح', 'وادي زم', 'أزمور', 'بنسليمان', 'تيفلت', 'الصخيرات',
        'تمارة', 'بوزنيقة', 'مديونة', 'النواصر', 'دار بوعزة', 'زناتة', 'أيت ملول',
        'إنزكان', 'الدروة', 'ابن جرير', 'اليوسفية', 'شيشاوة', 'قلعة السراغنة',
        'الرماني', 'زاكورة', 'تنغير', 'ميدلت', 'بركان', 'تاوريرت', 'جرادة',
        'أصيلة', 'الفنيدق', 'مرتيل', 'شفشاون', 'وزان', 'تاونات', 'صفرو',
        'بوعرفة', 'فكيك', 'طانطان', 'سيدي إفني', 'طاطا', 'بوجدور', 'السمارة',
    ];
}

// ── Pixel context ──────────────────────────────────────────────────────────
// The layout renders whichever pixels the current landing page selected. The
// controller declares the page's product (if any) before render(); everything
// downstream reads it from here rather than guessing from the URL.

function pixel_context_set(?array $product): void {
    $GLOBALS['__PIXEL_PRODUCT'] = $product;
}

function pixel_context_product(): ?array {
    return $GLOBALS['__PIXEL_PRODUCT'] ?? null;
}

/** @return array{facebook: ?array, tiktok: ?array} */
function pixel_context(): array {
    static $resolved = null;
    if ($resolved === null) {
        require_once __DIR__ . '/../src/Models/Pixel.php';
        $resolved = Pixel::resolve(pixel_context_product());
    }
    return $resolved;
}

/** Shared id so the same conversion can be deduplicated against a future CAPI call. */
function pixel_event_id(string $prefix): string {
    return $prefix . '.' . bin2hex(random_bytes(8));
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
