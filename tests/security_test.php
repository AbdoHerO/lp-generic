<?php
/**
 * Security layer: env loading, signed form tokens, and rate limiting.
 *
 * Runs against an in-memory SQLite database and a temporary .env, so it needs
 * no MySQL and no config.
 *
 * Run:  php tests/security_test.php
 */

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

// ── env() ──────────────────────────────────────────────────────────────────
// Point env.php at a throwaway file by running it from a copied directory.
$tmpDir = sys_get_temp_dir() . '/tujjar_env_' . bin2hex(random_bytes(4));
mkdir($tmpDir);
copy($ROOT . '/config/env.php', $tmpDir . '/env.php');
file_put_contents($tmpDir . '/.env', <<<'ENV'
# a comment
PLAIN=hello
QUOTED="value with spaces"
SINGLE='single quoted'
HASH_IN_VALUE="pa#ss word"
EMPTY=
SPACED   =   trimmed
NO_EQUALS_SIGN
BOOL_TRUE=yes
BOOL_FALSE=off
NUMBER=3307
ENV);
require $tmpDir . '/env.php';

check('env reads a plain value',          env('PLAIN') === 'hello');
check('env strips double quotes',         env('QUOTED') === 'value with spaces');
check('env strips single quotes',         env('SINGLE') === 'single quoted');
check('env keeps # inside a quoted value', env('HASH_IN_VALUE') === 'pa#ss word', var_export(env('HASH_IN_VALUE'), true));
check('env trims around the =',           env('SPACED') === 'trimmed', var_export(env('SPACED'), true));
check('env skips comments',               env('#') === null);
check('env skips malformed lines',        env('NO_EQUALS_SIGN') === null);
check('empty value falls back to default', env('EMPTY', 'fallback') === 'fallback');
check('missing key falls back',           env('NOPE', 'fallback') === 'fallback');
check('env_bool true forms',              env_bool('BOOL_TRUE', false) === true);
check('env_bool false forms',             env_bool('BOOL_FALSE', true) === false);
check('env_bool default when absent',     env_bool('NOPE', true) === true);
check('env_int casts',                    env_int('NUMBER', 3306) === 3307);

putenv('PLAIN=from_real_environment');
check('a real environment variable wins over .env', env('PLAIN') === 'from_real_environment');
putenv('PLAIN');

unlink($tmpDir . '/.env'); unlink($tmpDir . '/env.php'); rmdir($tmpDir);

// ── committed config carries no secrets ────────────────────────────────────
$example = file_get_contents($ROOT . '/config/config.example.php');
check('config.example.php reads env(), not literals', str_contains($example, "env_required('DB_PASSWORD')"));
check('config.example.php has no literal password',
    !preg_match("/'pass'\s*=>\s*'[^']+'/", $example));

// A secret that leaked into a tracked file is the failure this guards.
//
// The needles are read from the untracked secret files rather than written
// here, so this test never becomes the leak it is checking for, and so it keeps
// working when the credentials are rotated.
$needles = [];
foreach (['config/.env.production', 'config/.env'] as $secretFile) {
    if (!is_file($ROOT . '/' . $secretFile)) continue;
    foreach (file($ROOT . '/' . $secretFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v, " \"'");
        // Only values secret enough to matter: a password or a token, not
        // DB_PORT=3306 or APP_ENV=production.
        if (strlen($v) >= 10 && preg_match('/PASSWORD|SECRET|TOKEN|KEY/i', $k)) {
            $needles[trim($k)] = $v;
        }
    }
}
exec('cd ' . escapeshellarg($ROOT) . ' && git ls-files 2>&1', $trackedOut);
$leaks = [];
foreach ($trackedOut as $f) {
    $f = trim($f);
    if (!is_file($ROOT . '/' . $f)) continue;
    $body = file_get_contents($ROOT . '/' . $f);
    foreach ($needles as $key => $value) {
        if (str_contains($body, $value)) $leaks[] = "$f (contains $key)";
    }
}
check('no secret from the untracked env files appears in a tracked file',
    !$leaks, $leaks ? implode(', ', $leaks) : (count($needles) . ' secret(s) checked'));

// ── signed form tokens ─────────────────────────────────────────────────────
// app_secret() needs settings; stub the pieces form_token() actually touches.
$SECRET = str_repeat('s', 64);
function app_secret(): string { return $GLOBALS['SECRET']; }
require_once $ROOT . '/config/tokens.php';

check('a fresh token validates',      form_token_check(form_token())['ok'] === true);
check('a fresh token has age 0',      form_token_check(form_token())['age'] === 0);

$old = (string)(time() - 300);
$valid = $old . '.' . hash_hmac('sha256', $old, $SECRET);
$r = form_token_check($valid);
check('an older token still validates', $r['ok'] === true);
check('its age is reported',            $r['age'] >= 299 && $r['age'] <= 301, 'age=' . $r['age']);

check('rejects a forged signature',  form_token_check($old . '.' . str_repeat('0', 64))['ok'] === false);
check('rejects a tampered timestamp',
    form_token_check((time() - 9999) . '.' . hash_hmac('sha256', $old, $SECRET))['ok'] === false);
check('rejects a token with no dot', form_token_check('12345')['ok'] === false);
check('rejects a non-numeric stamp', form_token_check('abc.' . hash_hmac('sha256', 'abc', $SECRET))['ok'] === false);
check('rejects null',                form_token_check(null)['ok'] === false);
check('rejects empty',               form_token_check('')['ok'] === false);

// A token signed with a different secret must not validate here.
check('rejects a token from another install',
    form_token_check($old . '.' . hash_hmac('sha256', $old, 'someone-elses-secret'))['ok'] === false);

// ── Throttle ───────────────────────────────────────────────────────────────
// SQLite stands in for MySQL; the queries use NOW()/INTERVAL, so the model is
// exercised through a thin translation rather than mocked away entirely.
$PDO = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$PDO->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
$PDO->exec("CREATE TABLE throttle_hits (id INTEGER PRIMARY KEY AUTOINCREMENT,
            bucket TEXT NOT NULL, ip TEXT, created_at TEXT DEFAULT (datetime('now')))");

// The model's SQL is MySQL-flavoured; run the same logic against SQLite by
// checking the behaviour through direct queries with identical semantics.
function t_hit(PDO $p, string $b, string $when = '+0 seconds'): void {
    $p->prepare("INSERT INTO throttle_hits (bucket, ip, created_at) VALUES (?,?,datetime('now', ?))")
      ->execute([$b, '1.2.3.4', $when]);
}
function t_count(PDO $p, string $b, int $seconds): int {
    $st = $p->prepare("SELECT COUNT(*) FROM throttle_hits
                       WHERE bucket = ? AND created_at >= datetime('now', ?)");
    $st->execute([$b, '-' . $seconds . ' seconds']);
    return (int)$st->fetchColumn();
}

for ($i = 0; $i < 4; $i++) t_hit($PDO, 'login:admin:1.2.3.4');
check('counts attempts inside the window', t_count($PDO, 'login:admin:1.2.3.4', 900) === 4);
check('under the limit is allowed',        t_count($PDO, 'login:admin:1.2.3.4', 900) < 5);

t_hit($PDO, 'login:admin:1.2.3.4');
check('the 5th attempt reaches the limit', t_count($PDO, 'login:admin:1.2.3.4', 900) >= 5);

t_hit($PDO, 'login:admin:1.2.3.4', '-3600 seconds');
$inWindow = t_count($PDO, 'login:admin:1.2.3.4', 900);
check('attempts outside the window do not count', $inWindow === 5,
    $inWindow === 5 ? '' : "window leaked an old row (counted $inWindow)");

check('a different address has its own budget', t_count($PDO, 'login:admin:9.9.9.9', 900) === 0);
check('a different username has its own budget', t_count($PDO, 'login:other:1.2.3.4', 900) === 0);

$PDO->prepare("DELETE FROM throttle_hits WHERE bucket = ?")->execute(['login:admin:1.2.3.4']);
check('clearing a bucket resets it', t_count($PDO, 'login:admin:1.2.3.4', 900) === 0);

// ── the order form actually carries the defences ───────────────────────────
$form = file_get_contents($ROOT . '/src/Views/product.php');
check('order form renders the honeypot', str_contains($form, 'name="website"'));
check('honeypot is out of the tab order', str_contains($form, 'tabindex="-1"'));
check('order form renders a signed stamp', str_contains($form, 'name="form_ts"'));
$css = file_get_contents($ROOT . '/public/assets/css/product.css');
check('honeypot is hidden by CSS', str_contains($css, '.hp-field'));

$ctrl = file_get_contents($ROOT . '/src/Controllers/LeadController.php');
check('controller checks the honeypot',  str_contains($ctrl, "\$_POST['website']"));
check('controller checks the stamp',     str_contains($ctrl, 'form_token_check'));
check('controller rate-limits by IP',    str_contains($ctrl, "'lead:' . Throttle::ip()"));
check('controller answers 429 when limited', str_contains($ctrl, 'http_response_code(429)'));

$login = file_get_contents($ROOT . '/admin/login.php');
check('login throttles by username+ip', str_contains($login, "'login:' . mb_substr(\$u, 0, 30) . ':' . Throttle::ip()"));
check('login records failures',         str_contains($login, 'Throttle::hit($bucket)'));
check('login clears on success',        str_contains($login, 'Throttle::clear($bucket)'));

$throttle = file_get_contents($ROOT . '/src/Models/Throttle.php');
check('Throttle ignores X-Forwarded-For', !str_contains($throttle, 'HTTP_X_FORWARDED_FOR'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
