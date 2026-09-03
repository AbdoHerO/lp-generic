<?php
/**
 * Page templates: the shipped JSON files, and what applying one produces.
 *
 * Run:  php tests/templates_test.php
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
function clean_string($v, int $max = 500): string {
    $v = is_string($v) ? trim($v) : '';
    return mb_substr(preg_replace('/\s+/u', ' ', $v) ?? '', 0, $max, 'UTF-8');
}
require_once $ROOT . '/src/Models/Sections.php';
require_once $ROOT . '/src/Models/PageTemplate.php';

$PDO->exec("CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, slug TEXT UNIQUE,
            status INT DEFAULT 1, sections_json TEXT, deleted_at TEXT DEFAULT NULL)");
$PDO->exec("CREATE TABLE product_option_groups (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INT,
            name TEXT, label TEXT, type TEXT, position INT, is_required INT)");
$PDO->exec("CREATE TABLE product_option_values (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INT,
            value TEXT, swatch TEXT, position INT)");
$PDO->exec("CREATE TABLE product_offers (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INT, label TEXT,
            quantity INT, total_price REAL, is_recommended INT, free_shipping INT, is_default INT,
            requires_options INT, position INT)");

// ── the shipped templates are valid ────────────────────────────────────────
$all = PageTemplate::all();
check('templates are discovered', count($all) >= 4, count($all) . ' found');

foreach ($all as $key => $t) {
    check("$key has a label",       !empty($t['label']));
    check("$key has an icon",       !empty($t['icon']));
    check("$key has a description", !empty($t['description']));
    check("$key has hero copy",     !empty($t['sections']['hero']['headline']));
    check("$key has features",      count($t['sections']['features'] ?? []) >= 3, (string)count($t['sections']['features'] ?? []));
    check("$key has FAQs",          count($t['sections']['faqs'] ?? []) >= 3);
    check("$key has offers",        count($t['offers'] ?? []) >= 1);
    check("$key marks one default offer",
        count(array_filter($t['offers'] ?? [], fn($o) => !empty($o['is_default']))) === 1);

    // A template that shipped a price would either be ignored or published.
    check("$key sets no prices",
        !array_filter($t['offers'] ?? [], fn($o) => isset($o['total_price'])));

    // The sections must survive normalisation unchanged in substance.
    $norm = Sections::normalise($t['sections']);
    check("$key normalises cleanly", count($norm['features']) === count($t['sections']['features']));
}

// ── path traversal ─────────────────────────────────────────────────────────
// The key becomes a filename, so it is validated rather than trusted.
check('a traversal key is refused',   PageTemplate::find('../../config/config') === null);
check('an absolute key is refused',   PageTemplate::find('/etc/passwd') === null);
check('an unknown key returns null',  PageTemplate::find('does-not-exist') === null);
check('a valid key resolves',         PageTemplate::find('apparel') !== null);

// ── applying one ───────────────────────────────────────────────────────────
$id = PageTemplate::apply('apparel', 'سروال كاجوال كلاس');
check('a product is created', $id > 0);

$p = $PDO->query("SELECT * FROM products WHERE id = $id")->fetch();
check('the title is used',            $p['title'] === 'سروال كاجوال كلاس');
check('it starts inactive',           (int)$p['status'] === 0);
check('it gets a usable slug',        $p['slug'] !== '' && !str_contains($p['slug'], ' '));

$sections = Sections::decode($p['sections_json']);
check('the headline becomes the title', $sections['hero']['headline'] === 'سروال كاجوال كلاس',
    $sections['hero']['headline']);
check('features are carried over',    count($sections['features']) === 4, (string)count($sections['features']));
check('FAQs are carried over',        count($sections['faqs']) === 4);
check('testimonials are carried over', count($sections['testimonials']) === 3);
check('the CTA text is set',          $sections['cta_text'] !== '');

$groups = $PDO->query("SELECT * FROM product_option_groups WHERE product_id = $id ORDER BY position")->fetchAll();
check('option groups are created',    count($groups) === 2, (string)count($groups));
check('the colour group is a swatch', $groups[0]['type'] === 'swatch');
check('the size group is a select',   $groups[1]['type'] === 'select');

$values = $PDO->query("SELECT * FROM product_option_values ORDER BY group_id, position")->fetchAll();
check('option values are created',    count($values) === 8, (string)count($values));
check('swatch colours are kept',      $values[0]['swatch'] === '#111111', (string)$values[0]['swatch']);
check('values without a swatch are null', $values[3]['swatch'] === null);

$offers = $PDO->query("SELECT * FROM product_offers WHERE product_id = $id ORDER BY position")->fetchAll();
check('offers are created',           count($offers) === 3, (string)count($offers));
check('prices are left at zero',      (float)$offers[0]['total_price'] === 0.0);
check('one offer is the default',     (int)$offers[0]['is_default'] === 1);
check('one offer is recommended',     (int)$offers[1]['is_recommended'] === 1);
check('quantities are carried',       (int)$offers[2]['quantity'] === 3);
check('offers require options when the template has groups',
    (int)$offers[0]['requires_options'] === 1);

// A template with no option groups must not demand options.
$id2 = PageTemplate::apply('cosmetics', 'كريم مرطب');
$offers2 = $PDO->query("SELECT * FROM product_offers WHERE product_id = $id2")->fetchAll();
check('a template with no groups creates none',
    (int)$PDO->query("SELECT COUNT(*) FROM product_option_groups WHERE product_id = $id2")->fetchColumn() === 0);
check('its offers do not require options', (int)$offers2[0]['requires_options'] === 0);

// ── slug collisions ────────────────────────────────────────────────────────
$a = PageTemplate::apply('apparel', 'Same Title');
$b = PageTemplate::apply('apparel', 'Same Title');
$slugs = $PDO->query("SELECT slug FROM products WHERE id IN ($a, $b)")->fetchAll(PDO::FETCH_COLUMN);
check('a duplicate title still gets a unique slug', $slugs[0] !== $slugs[1], implode(' / ', $slugs));

// An Arabic-only title transliterates to nothing, so it must fall back.
$c = PageTemplate::apply('apparel', 'منتج عربي فقط');
$slugC = $PDO->query("SELECT slug FROM products WHERE id = $c")->fetchColumn();
check('an Arabic title still produces a slug', $slugC !== '' && preg_match('/^[a-z0-9-]+$/', $slugC),
    (string)$slugC);

// ── an unknown template is refused, not half-applied ───────────────────────
$before = (int)$PDO->query("SELECT COUNT(*) FROM products")->fetchColumn();
try {
    PageTemplate::apply('nope', 'x');
    check('an unknown template throws', false);
} catch (InvalidArgumentException $e) {
    check('an unknown template throws', true);
}
check('nothing was created for it',
    (int)$PDO->query("SELECT COUNT(*) FROM products")->fetchColumn() === $before);

// ── the picker is only offered when creating ───────────────────────────────
$view = file_get_contents($ROOT . '/admin/views/product-edit.php');
check('the picker is hidden for an existing product', str_contains($view, 'if (!$product && !empty($templates))'));
check('the picker posts from_template', str_contains($view, 'value="from_template"'));
check('the picker requires a title',    str_contains($view, 'اكتب اسم المنتج أولاً'));

$ctrl = file_get_contents($ROOT . '/admin/product-edit.php');
check('the controller handles from_template', str_contains($ctrl, "\$action === 'from_template'"));
check('template creation is CSRF-guarded',
    strpos($ctrl, 'admin_require_csrf()') < strpos($ctrl, "'from_template'"));
check('template creation is logged',    str_contains($ctrl, "'from template: '"));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
