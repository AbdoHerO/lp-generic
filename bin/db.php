<?php
/**
 * Database maintenance CLI.
 *
 * Everything the deployment pipeline needs to do to the database, in one place,
 * using the application's own connection and its own SQL files — so a deploy can
 * never disagree with what the app expects.
 *
 *   php bin/db.php status                 What exists: tables, rows, migrations
 *   php bin/db.php migrate                Apply pending migrations (safe, idempotent)
 *   php bin/db.php seed [--force]         Import sql/seed.sql (refuses on a non-empty catalogue)
 *   php bin/db.php fresh --force          DROP everything, re-import schema + seed
 *   php bin/db.php backup [path]          mysqldump-free logical dump of every table
 *   php bin/db.php wait [seconds]         Block until the database answers
 *   php bin/db.php images [--force]       Build WebP variants for existing uploads
 *
 * Refuses to run over HTTP. It is also blocked by .htaccess and excluded from
 * the Docker image, but a script that can drop a production database should not
 * rely on a web-server rule it cannot see.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("forbidden\n");
}

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config/database.php';

$argv0   = $argv[0] ?? 'bin/db.php';
$command = $argv[1] ?? 'status';
$flags   = array_slice($argv, 2);
$force   = in_array('--force', $flags, true);
$args    = array_values(array_filter($flags, fn($a) => !str_starts_with($a, '--')));

function out(string $msg): void { fwrite(STDOUT, $msg . "\n"); }
function err(string $msg): void { fwrite(STDERR, $msg . "\n"); }

function fail(string $msg, int $code = 1): never {
    err('✗ ' . $msg);
    exit($code);
}

function usage(string $argv0): never {
    err("Usage:");
    err("  php $argv0 status                 Tables, row counts, migrations, pixels");
    err("  php $argv0 migrate                Apply pending migrations (safe, idempotent)");
    err("  php $argv0 seed [--force]         Import sql/seed.sql if the catalogue is empty");
    err("  php $argv0 fresh --force          DROP everything, re-import schema + seed");
    err("  php $argv0 backup [path]          Logical dump of every table");
    err("  php $argv0 wait [seconds]         Block until the database answers");
    err("  php $argv0 images [--force]       Build WebP variants for existing uploads");
    exit(2);
}

// Validated before connecting: a typo should not sit waiting on a database
// handshake, least of all inside a pipeline with a job timeout.
$KNOWN = ['status', 'migrate', 'seed', 'fresh', 'backup', 'wait', 'images'];
if (!in_array($command, $KNOWN, true)) {
    err("Unknown command: $command\n");
    usage($argv0);
}

/**
 * Execute one of the project's .sql files.
 *
 * Unlike the silent importer used on first boot, this reports what failed and
 * how many statements ran — a deploy that half-applies a schema must not look
 * like a success.
 *
 * @return array{ran:int, failed:int}
 */
function run_sql_file(PDO $pdo, string $file, bool $strict = true): array {
    if (!is_file($file)) fail("SQL file not found: $file");

    $sql = (string)file_get_contents($file);
    $sql = preg_replace('/^--[^\n]*/m', '', $sql) ?? '';

    // The project's SQL files carry no semicolons inside string literals, which
    // is what makes this split safe. tests/ guards the invariant.
    $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => strlen($s) > 3);

    $ran = 0; $failed = 0;
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
            $ran++;
        } catch (Throwable $e) {
            $failed++;
            $first = strtok(trim($stmt), "\n");
            err('  ! ' . substr((string)$first, 0, 90) . ' — ' . $e->getMessage());
            if ($strict) fail('Aborted: ' . basename($file) . " failed after $ran statement(s).");
        }
    }
    return ['ran' => $ran, 'failed' => $failed];
}

function table_exists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
}

function count_rows(PDO $pdo, string $table): ?int {
    if (!table_exists($pdo, $table)) return null;
    return (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
}

// ── wait ───────────────────────────────────────────────────────────────────
// Runs before anything else because it must not require a working schema.
if ($command === 'wait') {
    $limit = (int)($args[0] ?? 60);
    $cfg   = require $ROOT . '/config/config.php';
    $d     = $cfg['db'];
    $start = time();
    while (true) {
        try {
            new PDO("mysql:host={$d['host']};port={$d['port']}", $d['user'], $d['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
            out("✓ Database reachable after " . (time() - $start) . 's.');
            exit(0);
        } catch (Throwable $e) {
            if (time() - $start >= $limit) fail("Database unreachable after {$limit}s: " . $e->getMessage());
            sleep(2);
        }
    }
}

// db() opens the connection and already applies pending migrations.
try {
    $pdo = db();
} catch (Throwable $e) {
    fail('Cannot connect: ' . $e->getMessage());
}

switch ($command) {

    // ── status ─────────────────────────────────────────────────────────────
    case 'status': {
        $cfg = require $ROOT . '/config/config.php';
        out('Database : ' . $cfg['db']['name'] . ' @ ' . $cfg['db']['host'] . ':' . $cfg['db']['port']);
        out('Env      : ' . $cfg['app']['env'] . '   base_url: "' . $cfg['app']['base_url'] . '"');
        out('');

        out('Tables');
        foreach (['admins','categories','products','product_media','product_offers',
                  'product_option_groups','product_option_values','pixels',
                  'leads','lead_items','lead_status_logs','lead_drafts',
                  'activity_log','throttle_hits','settings','schema_migrations'] as $t) {
            $n = count_rows($pdo, $t);
            printf("  %-24s %s\n", $t, $n === null ? '— missing —' : $n . ' row(s)');
        }

        out('');
        out('Applied migrations');
        if (table_exists($pdo, 'schema_migrations')) {
            $rows = $pdo->query("SELECT version, applied_at FROM schema_migrations ORDER BY version")->fetchAll();
            foreach ($rows as $r) printf("  %-32s %s\n", $r['version'], $r['applied_at']);
            if (!$rows) out('  (none)');

            require_once $ROOT . '/config/migrations.php';
            $applied = array_flip(array_column($rows, 'version'));
            $pending = array_diff_key(migrations_list(), $applied);
            out('');
            out('Pending migrations: ' . ($pending ? implode(', ', array_keys($pending)) : 'none'));
        } else {
            out('  (table missing — the app has never booted against this database)');
        }

        // Retired products are invisible in the admin list but still on disk.
        if (table_exists($pdo, 'products')) {
            $trashed = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NOT NULL")->fetchColumn();
            if ($trashed) out("
Retired products (in the trash): $trashed");
        }

        if (table_exists($pdo, 'pixels')) {
            out('');
            out('Pixels');
            foreach ($pdo->query("SELECT platform, name, pixel_id, is_default, status FROM pixels ORDER BY platform, name") as $p) {
                printf("  %-9s %-26s %-24s %s%s\n", $p['platform'], $p['name'], $p['pixel_id'],
                    $p['is_default'] ? '[default] ' : '', $p['status'] ? '' : '[disabled]');
            }
        }
        break;
    }

    // ── migrate ────────────────────────────────────────────────────────────
    // db() already ran them; this reports the result and fails loudly if any
    // are still pending, which is what a pipeline gate needs.
    case 'migrate': {
        require_once $ROOT . '/config/migrations.php';
        run_migrations($pdo);

        $applied = $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
        $pending = array_diff(array_keys(migrations_list()), $applied);
        if ($pending) fail('Still pending after running: ' . implode(', ', $pending) . ' (see the error log)');

        out('✓ Schema up to date (' . count($applied) . ' migration(s) applied in total).');
        break;
    }

    // ── seed ───────────────────────────────────────────────────────────────
    case 'seed': {
        $products = count_rows($pdo, 'products');
        if ($products === null) fail('No schema yet. Run: php bin/db.php fresh --force');

        // "Make sure the seed data is present", not "import it again". A live
        // store must never be re-seeded: seed.sql uses plain INSERTs, so a
        // second run would duplicate rows and collide on the unique slug. A
        // populated catalogue therefore satisfies the request and this exits
        // clean, which is also what a first deploy needs — the app already
        // imports schema + seed itself when the admins table is missing.
        if ($products > 0 && !$force) {
            out("• Catalogue already has $products product(s) — seed data is present, nothing to do.");
            out('  Use --force to import sql/seed.sql again anyway (expect duplicate-key errors).');
            break;
        }

        // Non-strict: seed.sql is written for an empty database, so on a --force
        // run the unique-key collisions are expected and reported, not fatal.
        $r = run_sql_file($pdo, $ROOT . '/sql/seed.sql', !$force);
        out("✓ Seed imported: {$r['ran']} statement(s) ran, {$r['failed']} skipped.");
        break;
    }

    // ── fresh ──────────────────────────────────────────────────────────────
    case 'fresh': {
        if (!$force) {
            fail("Refusing to drop the database without --force.\n"
               . "  This DELETES every product, order and setting permanently.\n"
               . "  Back up first:  php bin/db.php backup ./backup.sql\n"
               . "  Then run:       php bin/db.php fresh --force");
        }

        $leads = count_rows($pdo, 'leads');
        if ($leads) err("⚠  Dropping $leads existing order(s).");

        out('Importing sql/schema.sql …');
        $s = run_sql_file($pdo, $ROOT . '/sql/schema.sql');
        out("  {$s['ran']} statement(s).");

        out('Importing sql/seed.sql …');
        $d = run_sql_file($pdo, $ROOT . '/sql/seed.sql');
        out("  {$d['ran']} statement(s).");

        // schema.sql seeds schema_migrations through seed.sql; make sure any
        // migration added after that file was last touched still applies.
        require_once $ROOT . '/config/migrations.php';
        run_migrations($pdo);

        out('✓ Database rebuilt from scratch.');
        break;
    }

    // ── images ─────────────────────────────────────────────────────────────
    // Backfill. Images uploaded before the resize pipeline existed have no
    // variants, so those pages keep serving full-size originals to phones.
    case 'images': {
        require_once $ROOT . '/src/Models/Image.php';
        require_once $ROOT . '/config/helpers.php';

        if (!Image::available()) {
            fail('ext-gd with WebP support is not available in this PHP build.');
        }

        // Every image the database references, from all three places.
        $paths = [];
        foreach (['cover_image', 'og_image'] as $col) {
            foreach ($pdo->query("SELECT DISTINCT `$col` FROM products WHERE `$col` <> ''") as $r) {
                $paths[] = $r[$col];
            }
        }
        foreach ($pdo->query("SELECT DISTINCT url FROM product_media WHERE url <> ''") as $r) {
            $paths[] = $r['url'];
        }
        foreach (['store_logo', 'store_logo_light', 'store_favicon'] as $k) {
            $v = trim((string)_mig_setting($pdo, $k));
            if ($v !== '') $paths[] = $v;
        }

        $paths = array_values(array_unique($paths));
        out(count($paths) . ' image reference(s) found.');

        $done = $skipped = $external = $failed = 0;
        foreach ($paths as $path) {
            if (preg_match('#^https?://#i', $path)) { $external++; continue; }

            // Already built, unless asked to redo them.
            if (!$force && Image::srcset($path) !== null) { $skipped++; continue; }
            if ($force) Image::purge($path);

            $widths = Image::generate($path);
            if ($widths) {
                $done++;
                out('  ✓ ' . $path . '  →  ' . implode('px, ', $widths) . 'px');
            } else {
                $failed++;
                err('  ! ' . $path . ' — could not be processed (missing or not an image)');
            }
        }

        out('');
        out("✓ Built $done, skipped $skipped already done, $external external URL(s), $failed failed.");
        if ($skipped && !$force) out('  Re-run with --force to rebuild the ones that were skipped.');
        break;
    }

    // ── backup ─────────────────────────────────────────────────────────────
    // Plain PHP, so it works on hosts with no mysqldump binary (the app image
    // has none) and needs no second set of credentials.
    case 'backup': {
        $path = $args[0] ?? ($ROOT . '/backup-' . date('Ymd-His') . '.sql');
        $dir  = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) fail("Cannot create directory: $dir");

        $fh = @fopen($path, 'w');
        if (!$fh) fail("Cannot write: $path");

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        fwrite($fh, "-- tujjar.store logical backup — " . date('c') . "\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $total = 0;
        foreach ($tables as $t) {
            $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
            fwrite($fh, "DROP TABLE IF EXISTS `$t`;\n$create;\n\n");

            $rows = $pdo->query("SELECT * FROM `$t`");
            foreach ($rows as $row) {
                $cols = implode(',', array_map(fn($c) => "`$c`", array_keys($row)));
                $vals = implode(',', array_map(
                    fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v),
                    array_values($row)
                ));
                fwrite($fh, "INSERT INTO `$t` ($cols) VALUES ($vals);\n");
                $total++;
            }
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fh);
        @chmod($path, 0600);   // contains customer names, phones and addresses

        out('✓ Backup written: ' . $path);
        out('  ' . count($tables) . ' table(s), ' . $total . ' row(s), '
            . number_format(filesize($path) / 1024, 1) . ' KB');
        out('  Contains customer PII — move it off this host and keep it out of any artifact store.');
        break;
    }

    default:
        // Unreachable: the command was checked against $KNOWN before the
        // connection was opened. Kept so adding a command to $KNOWN without a
        // case here fails loudly instead of silently succeeding.
        usage($argv0);
}
