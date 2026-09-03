<?php
/**
 * ProductChecklist — the "is this ready to publish" logic.
 *
 * This is the part someone unfamiliar with the app will trust, so a wrong
 * answer is worse than no answer: saying a page is ready when it has no priced
 * offer means the first visitor cannot order.
 *
 * Run:  php tests/checklist_test.php
 */

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

$PDO = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
function db(): PDO { return $GLOBALS['PDO']; }
function settings_get(string $k, $d = null) { return $GLOBALS['SETTINGS'][$k] ?? $d; }
function clean_string($v, int $max = 500): string { return is_string($v) ? trim($v) : ''; }
$SETTINGS = [];

$PDO->exec("CREATE TABLE pixels (id INTEGER PRIMARY KEY AUTOINCREMENT, platform TEXT, name TEXT,
            pixel_id TEXT, access_token TEXT, test_event_code TEXT, is_default INT DEFAULT 0,
            status INT DEFAULT 1, notes TEXT)");

require_once $ROOT . '/src/Models/Pixel.php';
require_once $ROOT . '/src/Models/Sections.php';
require_once $ROOT . '/src/Models/ProductChecklist.php';

/** Find one item by its label. */
function item(array $cl, string $needle): ?array {
    foreach (array_merge($cl['required'], $cl['recommended']) as $i) {
        if (str_contains($i['label'], $needle)) return $i;
    }
    return null;
}

$complete = [
    'id' => 1, 'title' => 'سروال', 'slug' => 'pants', 'cover_image' => 'uploads/c.png',
    'seo_title' => 'عنوان', 'seo_description' => 'وصف',
    'fb_pixel_id' => null, 'tt_pixel_id' => null,
];
$offers = [
    ['total_price' => '249.00', 'is_default' => 1],
    ['total_price' => '459.00', 'is_default' => 0],
];
$groups = [['label' => 'اللون', 'values' => [['value' => 'أسود']]]];
$media  = [['kind' => 'slider'], ['kind' => 'slider'], ['kind' => 'gallery']];
$sections = Sections::decode('{"hero":{"headline":"عنوان"},'
    . '"features":[{"title":"a"},{"title":"b"},{"title":"c"}],'
    . '"testimonials":[{"name":"x","text":"y"},{"name":"z","text":"w"}],'
    . '"faqs":[{"q":"1","a":"1"},{"q":"2","a":"2"},{"q":"3","a":"3"}]}');

// ── a fully finished page ──────────────────────────────────────────────────
$PDO->exec("INSERT INTO pixels (platform,name,pixel_id,is_default,status) VALUES ('facebook','M','1',1,1)");
$cl = ProductChecklist::build($complete, $offers, $groups, $media, $sections);

check('a finished page is ready',        $cl['ready'] === true);
check('a finished page is 100%',         $cl['percent'] === 100, $cl['percent'] . '%');
check('nothing is outstanding',          ProductChecklist::tabIssues($cl) === []);
check('done equals total',               $cl['done'] === $cl['total']);

// ── each required item fails on its own ────────────────────────────────────
$cases = [
    ['no title',        ['title' => ''],        'عنوان المنتج',      'basics'],
    ['no slug',         ['slug' => ''],         'رابط الصفحة',       'basics'],
    ['no cover image',  ['cover_image' => ''],  'صورة الغلاف',       'basics'],
];
foreach ($cases as [$label, $override, $needle, $tab]) {
    $cl = ProductChecklist::build(array_merge($complete, $override), $offers, $groups, $media, $sections);
    check("$label → not ready",       $cl['ready'] === false);
    check("$label → names the item",  item($cl, $needle)['ok'] === false);
    check("$label → points at $tab",  item($cl, $needle)['tab'] === $tab);
}

// An offer that exists but has no price is the trap: the page looks finished
// and the first order fails.
$cl = ProductChecklist::build($complete, [['total_price' => '0.00', 'is_default' => 1]], $groups, $media, $sections);
check('a zero-price offer is not enough', $cl['ready'] === false);
check('it says a priced offer is missing', item($cl, 'عرض واحد بسعر')['ok'] === false);

$cl = ProductChecklist::build($complete, [], $groups, $media, $sections);
check('no offers at all → not ready',      $cl['ready'] === false);
check('no offers → no default either',     item($cl, 'عرض افتراضي')['ok'] === false);

// Two defaults is as broken as none.
$cl = ProductChecklist::build($complete,
    [['total_price' => '1', 'is_default' => 1], ['total_price' => '2', 'is_default' => 1]],
    $groups, $media, $sections);
check('two default offers → not ready',    $cl['ready'] === false);

// An option group with no values renders a dropdown nobody can satisfy.
$cl = ProductChecklist::build($complete, $offers, [['label' => 'المقاس', 'values' => []]], $media, $sections);
check('an empty option group → not ready', $cl['ready'] === false);
check('the empty group is named',
    str_contains(item($cl, 'مجموعات الخيارات')['hint'], 'المقاس'),
    item($cl, 'مجموعات الخيارات')['hint']);

check('no option groups at all is fine',
    ProductChecklist::build($complete, $offers, [], $media, $sections)['ready'] === true);

// ── recommended items never block publishing ───────────────────────────────
$thin = Sections::decode('{}');
$cl = ProductChecklist::build($complete, $offers, $groups, [['kind' => 'slider']], $thin);
check('a thin page is still publishable', $cl['ready'] === true);
check('but it is not 100%',               $cl['percent'] < 100, $cl['percent'] . '%');
check('missing features are flagged',     item($cl, 'ثلاث مميزات')['ok'] === false);
check('missing FAQs are flagged',         item($cl, 'ثلاثة أسئلة')['ok'] === false);
check('missing testimonials are flagged', item($cl, 'رأيان')['ok'] === false);
check('one slider image is flagged',      item($cl, 'صورتان')['ok'] === false);
check('the hint counts what exists',      str_contains(item($cl, 'ثلاث مميزات')['hint'], '0'));

// ── the pixel check reflects real resolution ───────────────────────────────
$cl = ProductChecklist::build($complete, $offers, $groups, $media, $sections);
check('inheriting the default pixel counts', item($cl, 'بكسل تتبع')['ok'] === true);

// Explicitly switched off on this page → flagged.
$off = array_merge($complete, ['fb_pixel_id' => 0, 'tt_pixel_id' => 0]);
$cl = ProductChecklist::build($off, $offers, $groups, $media, $sections);
check('a page with tracking off is flagged', item($cl, 'بكسل تتبع')['ok'] === false);
check('but it is still publishable',         $cl['ready'] === true);

// ── tab badges ─────────────────────────────────────────────────────────────
$cl = ProductChecklist::build($complete, $offers, $groups, [['kind' => 'slider']], $thin);
$issues = ProductChecklist::tabIssues($cl);
check('issues are grouped by tab',   isset($issues['content']) && isset($issues['media']));
// hero headline + features + FAQs + testimonials
check('content counts its four',     $issues['content'] === 4, json_encode($issues));
check('media counts its one',        $issues['media'] === 1);
check('a clean tab has no badge',    !isset($issues['offers']));

// ── a brand-new product does not crash ─────────────────────────────────────
$cl = ProductChecklist::build(null, [], [], [], Sections::blank());
check('a null product is handled',   is_array($cl['required']));
check('a null product is not ready', $cl['ready'] === false);
check('no pixel check without a product', item($cl, 'بكسل تتبع') === null);
check('percent stays in range',      $cl['percent'] >= 0 && $cl['percent'] <= 100);

// ── every item points at a real tab ────────────────────────────────────────
$valid = ['basics', 'content', 'offers', 'media', 'campaign'];
$cl = ProductChecklist::build($complete, $offers, $groups, $media, $sections);
$bad = array_filter(array_merge($cl['required'], $cl['recommended']),
    fn($i) => !in_array($i['tab'], $valid, true));
check('every item targets a real tab', !$bad, implode(', ', array_column($bad, 'tab')));

$tabsView = file_get_contents($ROOT . '/admin/views/partials/product-tabs.php');
foreach ($valid as $t) {
    check("the tab bar defines '$t'", str_contains($tabsView, "'$t'" . str_repeat(' ', max(0, 9 - strlen($t))) . '=>')
        || str_contains($tabsView, "'$t' =>") || str_contains($tabsView, "'$t'"));
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
