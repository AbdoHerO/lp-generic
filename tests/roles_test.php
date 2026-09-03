<?php
/**
 * Admin roles, the activity trail and the log writer.
 *
 * The rule being protected: an agent can work the order queue and nothing else.
 * Hiding a nav link is a courtesy — the guard on each page is the control, so
 * every admin-only entry point is checked for it here rather than trusted.
 *
 * Run:  php tests/roles_test.php
 */

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

$_SESSION = [];
$PDO = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$PDO->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
function db(): PDO { return $GLOBALS['PDO']; }

$PDO->exec("CREATE TABLE admins (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE,
            password_hash TEXT, role TEXT DEFAULT 'admin', status INT DEFAULT 1,
            last_login_at TEXT, created_at TEXT DEFAULT (datetime('now')))");
$PDO->exec("CREATE TABLE activity_log (id INTEGER PRIMARY KEY AUTOINCREMENT, admin_id INT,
            admin_name TEXT, action TEXT, entity TEXT, entity_id INT, summary TEXT, ip TEXT,
            created_at TEXT DEFAULT (datetime('now')))");

require_once $ROOT . '/src/Models/Admin.php';
require_once $ROOT . '/src/Models/Activity.php';
require_once $ROOT . '/src/Models/Log.php';

// ── creating accounts ──────────────────────────────────────────────────────
$r = Admin::save(['username' => 'owner', 'password' => 'longenough1', 'role' => 'admin', 'status' => 1]);
check('an admin can be created', $r['ok'], (string)$r['error']);
$ownerId = $r['id'];

$r = Admin::save(['username' => 'Salma.Caller', 'password' => 'password12', 'role' => 'agent', 'status' => 1]);
check('an agent can be created', $r['ok'], (string)$r['error']);
$agentId = $r['id'];
check('the username is normalised', Admin::find($agentId)['username'] === 'salma.caller',
    Admin::find($agentId)['username']);

check('a short username is refused',
    Admin::save(['username' => 'ab', 'password' => 'longenough1'])['ok'] === false);
check('a short password is refused',
    Admin::save(['username' => 'someone', 'password' => 'short'])['ok'] === false);
check('a duplicate username is refused',
    Admin::save(['username' => 'owner', 'password' => 'longenough1'])['error'] === 'اسم المستخدم مستعمل بالفعل');
check('an unknown role falls back to agent',
    Admin::save(['username' => 'weird', 'password' => 'longenough1', 'role' => 'superuser'])['ok']
    && Admin::findByUsername('weird')['role'] === 'agent');
check('the password is hashed, not stored',
    Admin::findByUsername('weird')['password_hash'] !== 'longenough1'
    && password_verify('longenough1', Admin::findByUsername('weird')['password_hash']));

// Editing without a password must keep the existing one.
$before = Admin::find($agentId)['password_hash'];
Admin::save(['username' => 'salma.caller', 'password' => '', 'role' => 'agent', 'status' => 1], $agentId);
check('an empty password field keeps the current password', Admin::find($agentId)['password_hash'] === $before);

// ── the last-admin guard ───────────────────────────────────────────────────
// Locking every operator out of products and settings is unrecoverable through
// the UI, so it is refused rather than warned about.
$PDO->exec("DELETE FROM admins WHERE username IN ('weird')");
check('there is exactly one admin now',
    (int)$PDO->query("SELECT COUNT(*) FROM admins WHERE role='admin' AND status=1")->fetchColumn() === 1);

$r = Admin::save(['username' => 'owner', 'role' => 'agent', 'status' => 1], $ownerId);
check('the last admin cannot be demoted', $r['ok'] === false, (string)$r['error']);
check('demotion says why',                str_contains((string)$r['error'], 'آخر حساب مدير'));

$r = Admin::save(['username' => 'owner', 'role' => 'admin', 'status' => 0], $ownerId);
check('the last admin cannot be disabled', $r['ok'] === false);
check('the last admin is still an admin',  Admin::find($ownerId)['role'] === 'admin');
check('the last admin is still enabled',   (int)Admin::find($ownerId)['status'] === 1);

check('the last admin cannot be deleted',  Admin::delete($ownerId, 999)['ok'] === false);
check('you cannot delete yourself',        Admin::delete($ownerId, $ownerId)['error'] === 'لا يمكنك حذف حسابك الحالي');

// With a second admin present, the first may be demoted.
Admin::save(['username' => 'second', 'password' => 'longenough1', 'role' => 'admin', 'status' => 1]);
$r = Admin::save(['username' => 'owner', 'role' => 'agent', 'status' => 1], $ownerId);
check('demotion is allowed once another admin exists', $r['ok'], (string)$r['error']);
check('the demotion took effect', Admin::find($ownerId)['role'] === 'agent');

// ── what each role may open ────────────────────────────────────────────────
$_SESSION['admin_role'] = 'admin';
check('an admin is an admin',            Admin::isAdmin());
foreach (['dashboard', 'products', 'pixels', 'settings', 'users', 'activity', 'leads'] as $view) {
    check("admin can open $view", Admin::canAccess($view));
}

$_SESSION['admin_role'] = 'agent';
check('an agent is not an admin', !Admin::isAdmin());
foreach (['dashboard', 'leads', 'lead-detail', 'reports'] as $view) {
    check("agent can open $view", Admin::canAccess($view));
}
foreach (['products', 'product-edit', 'pixels', 'settings', 'users', 'activity', 'categories'] as $view) {
    check("agent cannot open $view", !Admin::canAccess($view));
}

$_SESSION['admin_role'] = 'admin';

// ── every admin-only page actually calls the guard ─────────────────────────
// The nav hides these links from an agent; the URL is still typeable.
foreach (['products', 'product-edit', 'product-delete', 'product-clone',
          'pixels', 'settings', 'categories', 'users', 'activity'] as $page) {
    $src = file_get_contents($ROOT . "/admin/$page.php");
    check("admin/$page.php calls admin_require_admin()", str_contains($src, 'admin_require_admin()'));
}
foreach (['leads', 'lead-detail', 'reports', 'index'] as $page) {
    $src = file_get_contents($ROOT . "/admin/$page.php");
    check("admin/$page.php stays open to agents",
        str_contains($src, 'admin_require_auth()') && !str_contains($src, 'admin_require_admin()'));
}

$bootstrap = file_get_contents($ROOT . '/admin/_bootstrap.php');
check('the guard answers 403',      str_contains($bootstrap, 'http_response_code(403)'));
check('the guard explains itself',  str_contains($bootstrap, 'متاحة لحسابات المدير فقط'));

$login = file_get_contents($ROOT . '/admin/login.php');
check('login stores the role',      str_contains($login, "\$_SESSION['admin_role']"));
check('a disabled account cannot sign in', str_contains($login, "(int)(\$admin['status'] ?? 1) === 1"));
check('disabled accounts fail like a wrong password',
    !str_contains($login, 'الحساب معطل'));

// ── the activity trail ─────────────────────────────────────────────────────
$_SESSION['admin_id'] = $ownerId;
$_SESSION['admin_username'] = 'owner';
$_SERVER['REMOTE_ADDR'] = '10.0.0.7';

Activity::log('create', 'product', 42, 'سروال كاجوال');
Activity::log('update', 'settings', null, 'fb_pixel_id, capi_enabled');
Activity::log('delete', 'pixel', 7, 'Meta شتاء');

$res = Activity::paginate();
check('entries are recorded',      $res['total'] === 3, (string)$res['total']);
check('newest first',              $res['rows'][0]['entity'] === 'pixel');
check('the actor is recorded',     $res['rows'][0]['admin_name'] === 'owner');
check('the address is recorded',   $res['rows'][0]['ip'] === '10.0.0.7');
check('the summary is kept',       $res['rows'][1]['summary'] === 'fb_pixel_id, capi_enabled');
check('a null entity id is allowed', $res['rows'][1]['entity_id'] === null);

check('filter by entity',  Activity::paginate(['entity' => 'product'])['total'] === 1);
check('filter by action',  Activity::paginate(['action' => 'delete'])['total'] === 1);
check('filter by admin',   Activity::paginate(['admin_id' => $ownerId])['total'] === 3);
check('an entity history is available', count(Activity::forEntity('product', 42)) === 1);

// A long summary must be truncated rather than throwing on a VARCHAR(255).
Activity::log('update', 'product', 1, str_repeat('ا', 400));
check('a long summary is truncated',
    mb_strlen(Activity::paginate()['rows'][0]['summary']) === 255,
    (string)mb_strlen(Activity::paginate()['rows'][0]['summary']));

// ── the log writer ─────────────────────────────────────────────────────────
Log::error('a test failure', ['lead_id' => 5, 'access_token' => 'SECRET-TOKEN-VALUE', 'password' => 'hunter2']);
$recent = Log::recent(5, Log::WARNING);

check('an error is written',        count($recent) >= 1);
check('the message is kept',        ($recent[0]['message'] ?? '') === 'a test failure');
check('safe context is kept',       ($recent[0]['context']['lead_id'] ?? null) === 5);
check('a token is redacted',        ($recent[0]['context']['access_token'] ?? '') === '[redacted]');
check('a password is redacted',     ($recent[0]['context']['password'] ?? '') === '[redacted]');
check('the level is recorded',      ($recent[0]['level'] ?? '') === 'error');

Log::info('routine', []);
$warnOnly = Log::recent(20, Log::WARNING);
check('info is filtered out of the warning view',
    !array_filter($warnOnly, fn($l) => ($l['message'] ?? '') === 'routine'));
check('info is present at the info level',
    (bool)array_filter(Log::recent(20, Log::INFO), fn($l) => ($l['message'] ?? '') === 'routine'));

$logDir = $ROOT . '/storage/logs';
check('logs are written outside the web root reach',
    str_contains(file_get_contents($ROOT . '/.htaccess'), 'storage'));
check('the log directory is gitignored', is_file($logDir . '/.gitignore'));

// cleanup: remove only what this run wrote
foreach (glob($logDir . '/app-' . date('Y-m-d') . '.log') ?: [] as $f) @unlink($f);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
