<?php
/**
 * Scheduled maintenance.
 *
 * One entry point for everything that should happen on a timer, so the crontab
 * has a single line instead of five and the work stays in the application where
 * it can use the application's own models.
 *
 *   php bin/maintenance.php daily     backup, prune old rows, rebuild images
 *   php bin/maintenance.php backup    database dump + retention
 *   php bin/maintenance.php prune     drop expired throttle, draft, log and audit rows
 *   php bin/maintenance.php health    exit non-zero if the store looks broken
 *
 * Crontab (VPS):
 *   17 3 * * *  cd /path/to/app && php bin/maintenance.php daily  >> storage/logs/cron.log 2>&1
 *   *&#47;15 * * * *  cd /path/to/app && php bin/maintenance.php health >> storage/logs/cron.log 2>&1
 *
 * Docker:
 *   docker compose -p lp-tifaw exec -T app php bin/maintenance.php daily
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("forbidden\n");
}

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config/helpers.php';

$command = $argv[1] ?? 'daily';
$KNOWN   = ['daily', 'backup', 'prune', 'health'];

function say(string $m): void { fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n"); }
function warn(string $m): void { fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n"); }

if (!in_array($command, $KNOWN, true)) {
    warn('Unknown command: ' . $command);
    warn('Usage: php bin/maintenance.php ' . implode('|', $KNOWN));
    exit(2);
}

// ── backup ─────────────────────────────────────────────────────────────────
function task_backup(string $ROOT): int {
    $dir = $ROOT . '/storage/backups';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        warn('Cannot create ' . $dir);
        Log::error('Backup directory is not writable', ['dir' => $dir]);
        return 1;
    }

    $file = $dir . '/db-' . date('Y-m-d-His') . '.sql';

    // Reuses bin/db.php's dumper rather than a second implementation, so the
    // scheduled backup and the manual one can never diverge.
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/bin/db.php')
         . ' backup ' . escapeshellarg($file);
    exec($cmd . ' 2>&1', $out, $rc);

    if ($rc !== 0 || !is_file($file) || filesize($file) === 0) {
        warn('Backup FAILED: ' . implode(' | ', $out));
        Log::error('Scheduled backup failed', ['exit' => $rc, 'output' => implode(' | ', array_slice($out, 0, 3))]);
        @unlink($file);
        return 1;
    }

    // Compress and lock down: these hold customer names, phones and addresses.
    if (function_exists('gzopen')) {
        $gz = gzopen($file . '.gz', 'wb9');
        if ($gz) {
            $in = fopen($file, 'rb');
            while (!feof($in)) gzwrite($gz, (string)fread($in, 262144));
            fclose($in);
            gzclose($gz);
            @unlink($file);
            $file .= '.gz';
        }
    }
    @chmod($file, 0600);
    say('Backup: ' . basename($file) . ' (' . round(filesize($file) / 1024) . ' KB)');

    // Keep 14 daily dumps. Older ones are dead weight holding personal data.
    $all = glob($dir . '/db-*.sql*') ?: [];
    usort($all, fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach (array_slice($all, 14) as $old) {
        @unlink($old);
        say('Pruned old backup: ' . basename($old));
    }

    Log::info('Scheduled backup completed', ['file' => basename($file), 'kept' => min(14, count($all))]);
    return 0;
}

// ── prune ──────────────────────────────────────────────────────────────────
function task_prune(): int {
    $pdo = db();

    $throttle = $pdo->exec("DELETE FROM throttle_hits WHERE created_at < (NOW() - INTERVAL 1 DAY)");
    say('Throttle rows removed: ' . (int)$throttle);

    $drafts = Draft::prune();
    say('Expired drafts removed: ' . $drafts);

    $audit = Activity::prune(180);
    say('Audit rows removed: ' . $audit);

    return 0;
}

// ── health ─────────────────────────────────────────────────────────────────
/**
 * A store can be "up" — Apache answering, MySQL connected — and still be
 * broken: every product deactivated, no pixel firing, or orders that simply
 * stopped arriving mid-campaign. Uptime monitors never catch those.
 */
function task_health(): int {
    $problems = [];

    try {
        $pdo = db();
        $pdo->query('SELECT 1 FROM settings LIMIT 1');
    } catch (Throwable $e) {
        warn('DATABASE UNREACHABLE: ' . $e->getMessage());
        Log::error('Health check: database unreachable', ['error' => $e->getMessage()]);
        return 1;
    }

    $active = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 1 AND deleted_at IS NULL")->fetchColumn();
    if ($active === 0) $problems[] = 'no active landing page';

    // Orders stopping dead is the signal that matters. Only meaningful once the
    // store has a baseline, so it is compared against the previous week.
    $recent = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE created_at >= (NOW() - INTERVAL 24 HOUR)")->fetchColumn();
    $prior  = (int)$pdo->query("SELECT COUNT(*) FROM leads
                                WHERE created_at >= (NOW() - INTERVAL 8 DAY)
                                  AND created_at <  (NOW() - INTERVAL 1 DAY)")->fetchColumn();
    $dailyAverage = $prior / 7;

    if ($dailyAverage >= 3 && $recent === 0) {
        $problems[] = sprintf('no orders in 24h (previous 7-day average: %.1f/day)', $dailyAverage);
    }

    // A live page with no pixel is ad spend reporting nothing.
    $blind = 0;
    foreach ($pdo->query("SELECT id, fb_pixel_id, tt_pixel_id FROM products
                          WHERE status = 1 AND deleted_at IS NULL") as $p) {
        $px = Pixel::resolve($p);
        if (!$px['facebook'] && !$px['tiktok']) $blind++;
    }
    if ($blind > 0) $problems[] = "$blind active page(s) with no pixel at all";

    $errors = count(Log::recent(50, Log::ERROR));
    if ($errors >= 10) $problems[] = "$errors errors logged recently";

    if (!$problems) {
        say("Healthy: $active active page(s), $recent order(s) in the last 24h.");
        return 0;
    }

    foreach ($problems as $p) warn('PROBLEM: ' . $p);
    Log::warning('Health check found problems', ['problems' => $problems]);
    return 1;
}

// ── dispatch ───────────────────────────────────────────────────────────────
$exit = 0;
switch ($command) {
    case 'backup': $exit = task_backup($ROOT); break;
    case 'prune':  $exit = task_prune(); break;
    case 'health': $exit = task_health(); break;

    case 'daily':
        // Backup first: everything after it is destructive in some small way.
        $exit  = task_backup($ROOT);
        $exit |= task_prune();

        if (Image::available()) {
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/bin/db.php') . ' images 2>&1', $o, $rc);
            say('Image backfill exit ' . $rc);
        }

        // Reported, never fatal: a health warning must not make the nightly job
        // look like it failed to back up.
        task_health();
        break;
}

exit($exit === 0 ? 0 : 1);
