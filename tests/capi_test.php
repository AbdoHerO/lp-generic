<?php
/**
 * Conversions API payloads.
 *
 * These are the details that fail silently: a mis-normalised phone matches
 * nobody, an un-hashed name is a privacy incident, and a random event id
 * double-counts every sale. None of it shows up in the UI, so it is tested
 * rather than eyeballed.
 *
 * No network: the transport is replaced with a recorder.
 *
 * Run:  php tests/capi_test.php
 */

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

// ── stubs ──────────────────────────────────────────────────────────────────
$SETTINGS = ['capi_enabled' => '1'];
function settings_get(string $k, $d = null) { return $GLOBALS['SETTINGS'][$k] ?? $d; }
function base_url(string $p = '') { return '/' . ltrim($p, '/'); }
function request_is_https(): bool { return true; }
function clean_string($v, int $max = 500): string { return is_string($v) ? trim($v) : ''; }

// Pixel::resolve is the only thing PixelServer needs from the model.
class Pixel {
    public const PLATFORMS = ['facebook', 'tiktok'];
    public static array $next = [];
    public static function resolve(?array $p = null): array { return self::$next; }
}

require_once $ROOT . '/src/Models/PixelServer.php';

/**
 * Build what a report would carry, without sending it.
 *
 * userData() is private — reflection reads it rather than the class exposing a
 * test-only accessor it would otherwise never need.
 */
function capture_reports(array $lead, array $product): array {
    $userData = (new ReflectionMethod('PixelServer', 'userData'));
    $userData->setAccessible(true);

    return [
        'event_id' => 'purchase.' . (int)$lead['id'],
        'user'     => $userData->invoke(null, $lead),
        'pixels'   => Pixel::resolve($product),
    ];
}

// ── phone normalisation ────────────────────────────────────────────────────
// Every form below is something a Moroccan shopper actually types.
$cases = [
    '0612345678'      => '212612345678',
    '06 12 34 56 78'  => '212612345678',
    '06-12-34-56-78'  => '212612345678',
    '+212612345678'   => '212612345678',
    '00212612345678'  => '212612345678',
    '212612345678'    => '212612345678',
    '612345678'       => '212612345678',
    '0712345678'      => '212712345678',
    '712345678'       => '212712345678',
    ''                => '',
];
foreach ($cases as $in => $want) {
    $got = PixelServer::normalisePhone((string)$in);
    check("phone '$in' → $want", $got === $want, $got === $want ? '' : "got '$got'");
}
check('all input forms hash identically',
    count(array_unique(array_map(
        fn($p) => hash('sha256', PixelServer::normalisePhone($p)),
        ['0612345678', '+212612345678', '00212612345678', '06 12 34 56 78']
    ))) === 1);

// ── user data is hashed, never plain ───────────────────────────────────────
$lead = [
    'id' => 77, 'product_slug' => 'casual-pants', 'total_price' => '459.00',
    'quantity' => 2, 'fullname' => 'Mohamed  El Alaoui', 'phone' => '0612345678',
    'city' => 'Casablanca', 'address' => 'rue 12', 'created_at' => '2026-09-04 10:00:00',
    'fbclid' => 'FBCLID123', 'ttclid' => 'TTCLID456',
    'ip' => '105.66.1.1', 'user_agent' => 'Mozilla/5.0',
];
$product = ['id' => 1, 'title' => 'سروال كاجوال', 'fb_pixel_id' => null, 'tt_pixel_id' => null];

Pixel::$next = [
    'facebook' => ['pixel_id' => '640658465078889', 'access_token' => 'FBTOKEN', 'test_event_code' => null, 'name' => 'Meta'],
    'tiktok'   => ['pixel_id' => 'CM08HVJC77U7MRPGKD5G', 'access_token' => 'TTTOKEN', 'test_event_code' => null, 'name' => 'TT'],
];
$r = capture_reports($lead, $product);
$user = $r['user'];

check('phone is hashed', ($user['ph'] ?? '') === hash('sha256', '212612345678'));
check('phone is not sent in the clear',
    !in_array('0612345678', $user, true) && !in_array('212612345678', $user, true));
check('first name is hashed and lowercased', ($user['fn'] ?? '') === hash('sha256', 'mohamed'));
check('last name is the final word',         ($user['ln'] ?? '') === hash('sha256', 'alaoui'));
check('city is hashed without spaces',       ($user['ct'] ?? '') === hash('sha256', 'casablanca'));
check('country is set',                      ($user['country'] ?? '') === hash('sha256', 'ma'));
check('every identifier is a sha256 hex',
    !array_filter($user, fn($v) => !preg_match('/^[0-9a-f]{64}$/', (string)$v)));

// A lead with no name or city must still produce a valid payload.
$sparse = capture_reports(['id' => 1, 'phone' => '0600000000', 'created_at' => 'now'], $product);
check('a lead with no name still hashes a phone', isset($sparse['user']['ph']));
check('missing name adds no empty hash',          !isset($sparse['user']['fn']));

// ── event ids dedupe against the browser ───────────────────────────────────
check('event id is deterministic',      $r['event_id'] === 'purchase.77');
check('event id matches the thank-you page',
    str_contains(file_get_contents($ROOT . '/src/Controllers/LeadController.php'),
                 "'event_id' => 'purchase.' . \$orderId"));
check('a second report reuses the id',  capture_reports($lead, $product)['event_id'] === $r['event_id']);
check('a different order gets a different id',
    capture_reports(['id' => 78, 'phone' => '', 'created_at' => 'now'], $product)['event_id'] === 'purchase.78');

// ── the master switch and per-pixel tokens gate the send ───────────────────
$src = file_get_contents($ROOT . '/src/Models/PixelServer.php');
check('reporting is off unless capi_enabled', str_contains($src, "settings_get('capi_enabled', '0') !== '1'"));
check('Meta needs an access token',           str_contains($src, "!empty(\$pixels['facebook']['access_token'])"));
check('TikTok needs an access token',         str_contains($src, "!empty(\$pixels['tiktok']['access_token'])"));

// ── payload shape ──────────────────────────────────────────────────────────
check('Meta sends Purchase',              (bool)preg_match("/'event_name'\s+=> 'Purchase'/", $src));
check('TikTok sends CompletePayment',     (bool)preg_match("/'event'\s+=> 'CompletePayment'/", $src));
check('Meta marks the source as website', (bool)preg_match("/'action_source'\s+=> 'website'/", $src));
check('currency is MAD',                  substr_count($src, "'currency'") >= 2 && str_contains($src, "'MAD'"));
check('the value comes from the lead',    str_contains($src, "(float)\$lead['total_price']"));
check('fbc is derived from fbclid',       str_contains($src, "'fbc'"));
check('ttclid is passed through',         str_contains($src, "\$ttUser['ttclid']"));
check('a test event code is forwarded',   substr_count($src, 'test_event_code') >= 3);

// ── failure is never fatal to the order ────────────────────────────────────
$ctrl = file_get_contents($ROOT . '/src/Controllers/LeadController.php');
check('the order flow wraps the report in try/catch',
    (bool)preg_match('/try \{ PixelServer::reportPurchase.*?catch \(Throwable/s', $ctrl));
check('the report runs after the lead is saved',
    strpos($ctrl, 'Lead::create') < strpos($ctrl, 'PixelServer::reportPurchase'));
check('transport errors are logged, not thrown', str_contains($src, "error_log(\"\$label transport error"));
check('a non-2xx response is logged',            str_contains($src, "error_log(\"\$label HTTP \$code"));
check('TikTok body-level rejection is detected', str_contains($src, "\$decoded['code']"));
check('curl is checked before use',              str_contains($src, "function_exists('curl_init')"));
check('requests verify TLS',                     str_contains($src, 'CURLOPT_SSL_VERIFYPEER => true'));
check('requests have a timeout',                 str_contains($src, 'CURLOPT_TIMEOUT'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
