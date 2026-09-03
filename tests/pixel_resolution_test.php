<?php
/**
 * Pixel::resolve() — every state a landing page's pixel choice can be in.
 *
 * Runs against an in-memory SQLite database so it needs no MySQL and no config:
 *     php tests/pixel_resolution_test.php
 * Exits non-zero on the first failing expectation.
 */
$PDO = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$PDO->exec("CREATE TABLE pixels (id INTEGER PRIMARY KEY AUTOINCREMENT, platform TEXT, name TEXT,
            pixel_id TEXT, access_token TEXT, test_event_code TEXT, is_default INT DEFAULT 0,
            status INT DEFAULT 1, notes TEXT)");
$PDO->exec("CREATE TABLE products (id INTEGER PRIMARY KEY, fb_pixel_id INT, tt_pixel_id INT)");
$PDO->exec("INSERT INTO pixels (platform,name,pixel_id,is_default,status) VALUES
   ('facebook','Meta Default','FB-DEFAULT',1,1),
   ('facebook','Meta Winter','FB-WINTER',0,1),
   ('facebook','Meta Paused','FB-PAUSED',0,0),
   ('tiktok','TT Default','TT-DEFAULT',1,1),
   ('tiktok','TT Second','TT-SECOND',0,1)");

function db(): PDO { return $GLOBALS['PDO']; }
$SETTINGS = ['fb_pixel_id' => 'LEGACY-FB', 'tiktok_pixel_id' => 'LEGACY-TT'];
function settings_get(string $k, $d = null) { return $GLOBALS['SETTINGS'][$k] ?? $d; }
function clean_string($v, int $max = 500): string { return is_string($v) ? trim($v) : ''; }
require __DIR__ . '/../src/Models/Pixel.php';

$ids = [];
foreach ($PDO->query("SELECT id,name FROM pixels") as $r) $ids[$r['name']] = (int)$r['id'];

$pass = 0; $failn = 0;
function check(string $label, $got, $want) {
    global $pass, $failn;
    $ok = $got === $want;
    $ok ? $pass++ : $failn++;
    printf("%-4s %-52s got=%-12s want=%s\n", $ok ? 'ok' : 'FAIL', $label,
        var_export($got, true), var_export($want, true));
}
function pid(?array $row) { return $row === null ? null : $row['pixel_id']; }

// 1. Site-wide page (no product) → platform defaults
$r = Pixel::resolve(null);
check('no product → fb default',  pid($r['facebook']), 'FB-DEFAULT');
check('no product → tt default',  pid($r['tiktok']),   'TT-DEFAULT');

// 2. Landing page left on "inherit" (NULL)
$r = Pixel::resolve(['fb_pixel_id' => null, 'tt_pixel_id' => null]);
check('inherit → fb default', pid($r['facebook']), 'FB-DEFAULT');
check('inherit → tt default', pid($r['tiktok']),   'TT-DEFAULT');

// 3. Landing page pinned to a specific pixel
$r = Pixel::resolve(['fb_pixel_id' => $ids['Meta Winter'], 'tt_pixel_id' => $ids['TT Second']]);
check('pinned → fb winter', pid($r['facebook']), 'FB-WINTER');
check('pinned → tt second', pid($r['tiktok']),   'TT-SECOND');

// 4. Mixed: Meta on account 2, TikTok deliberately off
$r = Pixel::resolve(['fb_pixel_id' => $ids['Meta Winter'], 'tt_pixel_id' => 0]);
check('mixed → fb winter',   pid($r['facebook']), 'FB-WINTER');
check('mixed → tt off',      pid($r['tiktok']),   null);

// 5. String values (what a form POST actually stores/reads back)
$r = Pixel::resolve(['fb_pixel_id' => (string)$ids['Meta Winter'], 'tt_pixel_id' => '0']);
check('string "N" → fb winter', pid($r['facebook']), 'FB-WINTER');
check('string "0" → tt off',    pid($r['tiktok']),   null);

// 6. Pinned to a paused pixel → nothing, never another advertiser's pixel
$r = Pixel::resolve(['fb_pixel_id' => $ids['Meta Paused'], 'tt_pixel_id' => null]);
check('paused pixel → no fb (no silent fallback)', pid($r['facebook']), null);

// 7. Pinned to a deleted pixel id
$r = Pixel::resolve(['fb_pixel_id' => 99999, 'tt_pixel_id' => null]);
check('missing pixel → no fb', pid($r['facebook']), null);

// 8. No default configured → legacy settings value is the last resort
$PDO->exec("UPDATE pixels SET is_default = 0");
$r = Pixel::resolve(null);
check('no default → legacy fb setting', pid($r['facebook']), 'LEGACY-FB');
check('no default → legacy tt setting', pid($r['tiktok']),   'LEGACY-TT');

// 9. Nothing anywhere → no pixel at all
$PDO->exec("DELETE FROM pixels");
$SETTINGS = [];
$r = Pixel::resolve(null);
check('empty install → no fb', pid($r['facebook']), null);
check('empty install → no tt', pid($r['tiktok']),   null);

echo "\n$pass passed, $failn failed\n";
exit($failn ? 1 : 0);
