<?php
/**
 * Deployment invariants.
 *
 * A deploy pulls one git commit and builds an image from it — nothing reaches
 * production any other way. That only holds if every file the running app needs
 * is committed, is not excluded from the image, and is not reachable over HTTP
 * when it should not be. This test checks all three, plus the assumptions the
 * SQL importer makes about the project's .sql files.
 *
 * Run:  php tests/deployment_test.php
 */

$ROOT = dirname(__DIR__);
chdir($ROOT);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

// ── 1. Everything the running application needs is committed ───────────────
// git ls-files is the exact set `checkout scm` produces on the build agent.
exec('git ls-files 2>&1', $trackedOut, $rc);
$tracked = $rc === 0 ? array_flip(array_map('trim', $trackedOut)) : [];
check('git ls-files works', $rc === 0, $rc === 0 ? count($tracked) . ' tracked files' : 'not a git repo?');

$required = [
    // Entry points and routing
    'index.php', '.htaccess',
    // Config and schema management
    'config/config.php', 'config/config.example.php', 'config/database.php',
    'config/helpers.php', 'config/migrations.php', 'config/env.php',
    'config/tokens.php', 'config/.env.example', 'config/autoload.php',
    // The SQL the first boot and the deploy modes import
    'sql/schema.sql', 'sql/seed.sql',
    // Deployment tooling
    'bin/db.php', 'bin/maintenance.php', 'Dockerfile', 'docker-compose.yml', 'Jenkinsfile',
    'docker/entrypoint.sh', 'docker/apache-site.conf', 'docker/php.ini', 'docker/health.php',
    '.dockerignore', '.gitattributes', '.env.example',
    // Application code
    'src/Router.php', 'src/Models/Product.php', 'src/Models/Lead.php',
    'src/Models/Pixel.php', 'src/Models/Settings.php',
    'src/Models/Throttle.php', 'src/Models/SecurityAudit.php',
    'src/Models/Sections.php', 'src/Models/Report.php', 'src/Models/Activity.php',
    'src/Models/Admin.php', 'src/Models/Draft.php', 'src/Models/Experiment.php',
    'src/Models/Image.php', 'src/Models/Log.php', 'src/Models/PixelServer.php',
    'src/Models/PageTemplate.php', 'src/Controllers/SeoController.php',
    'src/Views/partials/structured-data.php',
    'admin/views/partials/section-editor.php', 'admin/views/partials/page-options.php',
    'admin/reports.php', 'admin/users.php', 'admin/activity.php', 'admin/drafts.php',
    'admin/categories.php',
    'admin/templates/apparel.json', 'admin/templates/electronics.json',
    'admin/templates/cosmetics.json', 'admin/templates/home.json',
    'storage/logs/.gitignore', 'storage/backups/.gitignore',
    'src/Views/layouts/public.php', 'src/Views/partials/pixels-head.php',
    'admin/_bootstrap.php', 'admin/pixels.php', 'admin/views/pixels.php',
    // Brand assets referenced by the default settings rows
    'public/assets/img/logo.svg', 'public/assets/img/logo-light.svg',
    'public/assets/img/favicon.svg',
    'public/assets/css/theme.css', 'public/assets/css/admin.css',
    'public/assets/js/product.js',
];
$untracked = array_values(array_filter($required, fn($f) => !isset($tracked[$f])));
check('every deploy-critical file is tracked by git', !$untracked, $untracked ? implode(', ', $untracked) : '');

$onDisk = array_values(array_filter($required, fn($f) => !is_file($ROOT . '/' . $f)));
check('every deploy-critical file exists on disk', !$onDisk, $onDisk ? implode(', ', $onDisk) : '');

// Tracked is not the same as deployed: the build agent checks out HEAD, so a
// staged-but-uncommitted change is on this machine only. A warning rather than
// a failure — mid-development this is the normal state.
exec('git status --porcelain -- ' . implode(' ', array_map('escapeshellarg', $required)) . ' 2>&1', $dirty);
$dirty = array_values(array_filter(array_map('trim', $dirty)));
if ($dirty) {
    echo "\n⚠  Deploy-critical files differ from HEAD — the build agent would NOT get these changes:\n";
    foreach ($dirty as $d) echo "     $d\n";
    echo "   Commit before deploying.\n\n";
}

// Credentials must be the opposite: present locally, never committed.
foreach (['config/config.local.php', 'config/config.prod.php', '.env',
          'config/.env', 'config/.env.production'] as $secret) {
    check("secret not committed: $secret", !isset($tracked[$secret]));
}

// ── 2. The image keeps what it needs and drops what it must not ────────────
$dockerignore = file_get_contents($ROOT . '/.dockerignore');
$ignoreLines  = array_values(array_filter(array_map('trim', explode("\n", $dockerignore)),
    fn($l) => $l !== '' && !str_starts_with($l, '#')));

// sql/ and bin/ ship: database.php imports the former on first boot and the
// pipeline calls the latter for every deploy mode.
foreach (['sql', 'sql/', 'bin', 'bin/'] as $needed) {
    check("image keeps $needed", !in_array($needed, $ignoreLines, true));
}
foreach (['config/config.prod.php', 'config/config.local.php', '.env', 'tests/', '*.md'] as $excluded) {
    check("image excludes $excluded", in_array($excluded, $ignoreLines, true));
}

// ── 3. Nothing sensitive is reachable over HTTP ────────────────────────────
// .htaccess is the front door on XAMPP and Hostinger; the vhost is the second
// lock in Docker. Both must cover the same paths.
$htaccess = file_get_contents($ROOT . '/.htaccess');
$vhost    = file_get_contents($ROOT . '/docker/apache-site.conf');

foreach (['config', 'src', 'sql', 'bin', 'tests', 'storage'] as $dir) {
    check(".htaccess blocks /$dir/", (bool)preg_match('#\^\([^)]*\b' . $dir . '\b[^)]*\)/#', $htaccess));
    check("vhost blocks /$dir/",     (bool)preg_match('#\([^)]*\b' . $dir . '\b[^)]*\)\(/\|\$\)#', $vhost));
}
check('.htaccess blocks .md files', str_contains($htaccess, 'md|'));
check('vhost blocks .md files',     str_contains($vhost, 'md|'));

// bin/db.php can drop the database, so it also refuses to run outside the CLI
// regardless of what any web-server rule says.
$dbTool = file_get_contents($ROOT . '/bin/db.php');
check('bin/db.php refuses non-CLI', str_contains($dbTool, "PHP_SAPI !== 'cli'"));
check('bin/db.php gates fresh behind --force', str_contains($dbTool, 'Refusing to drop the database without --force'));

$maint = file_get_contents($ROOT . '/bin/maintenance.php');
check('bin/maintenance.php refuses non-CLI', str_contains($maint, "PHP_SAPI !== 'cli'"));
check('backups are chmod 600',               str_contains($maint, 'chmod($file, 0600)'));
check('backups are pruned to a fixed count', str_contains($maint, 'array_slice($all, 14)'));

// Log files and database dumps must never be committed.
$storageIgnores = file_get_contents($ROOT . '/storage/logs/.gitignore')
                . file_get_contents($ROOT . '/storage/backups/.gitignore');
check('log files are gitignored',  str_contains($storageIgnores, '*.log'));
check('dumps are gitignored',      str_contains($storageIgnores, '*.sql'));
$leaked = array_filter(array_keys($tracked), fn($f) =>
    str_starts_with($f, 'storage/') && !str_ends_with($f, '.gitignore'));
check('no log or dump is committed', !$leaked, implode(', ', $leaked));

// ── 4. The SQL importer's assumptions about the .sql files ─────────────────
// _exec_sql_file() and run_sql_file() both split on ';'. That is only safe
// while no string literal contains one.
foreach (['sql/schema.sql', 'sql/seed.sql', 'sql/upgrade-2026-09-pixels-and-rebrand.sql'] as $file) {
    $sql = file_get_contents($ROOT . '/' . $file);
    $sql = preg_replace('/^--[^\n]*/m', '', $sql) ?? '';
    $inString = false; $bad = 0;
    for ($i = 0, $n = strlen($sql); $i < $n; $i++) {
        $c = $sql[$i];
        if ($c === "'") {
            if ($inString && ($sql[$i + 1] ?? '') === "'") { $i++; continue; }  // escaped ''
            $inString = !$inString;
        } elseif ($c === ';' && $inString) {
            $bad++;
        }
    }
    check("$file is safe to split on ';'", $bad === 0, $bad ? "$bad semicolon(s) inside string literals" : '');
    check("$file has balanced quotes", !$inString);
}

// ── 5. A fresh install and a migrated install end up identical ─────────────
// schema.sql builds new databases; migrations.php upgrades existing ones. If
// they drift, a new deployment gets a different schema from an old one.
$schema = file_get_contents($ROOT . '/sql/schema.sql');
require_once $ROOT . '/config/migrations.php';

check('schema.sql creates pixels',            str_contains($schema, 'CREATE TABLE pixels'));
check('schema.sql creates schema_migrations', str_contains($schema, 'CREATE TABLE schema_migrations'));
foreach (['fb_pixel_id', 'tt_pixel_id'] as $col) {
    check("schema.sql has products.$col", (bool)preg_match('/^\s*' . $col . '\s+INT/mi', $schema));
}
// schema.sql DROPs before creating, so a re-import must also clear the ledger —
// otherwise migrations are recorded as applied against tables that just vanished.
foreach (['pixels', 'schema_migrations'] as $t) {
    check("schema.sql drops $t before creating it", str_contains($schema, "DROP TABLE IF EXISTS $t;"));
}

$seed     = file_get_contents($ROOT . '/sql/seed.sql');
$missing  = array_values(array_filter(array_keys(migrations_list()), fn($v) => !str_contains($seed, $v)));
check('seed.sql marks every migration applied', !$missing,
    $missing ? 'fresh installs would re-run: ' . implode(', ', $missing) : '');

// Every migration in the list must actually be callable.
$broken = array_values(array_filter(migrations_list(), fn($fn) => !function_exists($fn)));
check('every migration function exists', !$broken, $broken ? implode(', ', $broken) : '');

// ── 6. Line endings that would break the container ─────────────────────────
// A CRLF entrypoint fails as "exec /usr/local/bin/lp-entrypoint: no such file
// or directory", which is one of the least obvious errors in this stack.
$attrs = file_get_contents($ROOT . '/.gitattributes');
check('.gitattributes pins *.sh to LF', str_contains($attrs, '*.sh        text eol=lf'));
check('.gitattributes pins bin/ to LF', str_contains($attrs, 'bin/*       text eol=lf'));
check('entrypoint.sh has no CRLF', !str_contains(file_get_contents($ROOT . '/docker/entrypoint.sh'), "\r\n"));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
