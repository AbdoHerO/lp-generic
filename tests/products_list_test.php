<?php
/**
 * The admin products list: search, filter, pagination and the bulk-action view.
 *
 * Run:  php tests/products_list_test.php
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
$PDO->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
function db(): PDO { return $GLOBALS['PDO']; }
function settings_get(string $k, $d = null) { return $d; }
function clean_string($v, int $max = 500): string { return is_string($v) ? trim($v) : ''; }
require_once $ROOT . '/src/Models/Product.php';
require_once $ROOT . '/src/Models/Pixel.php';

$PDO->exec("CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, position INT)");
$PDO->exec("CREATE TABLE pixels (id INTEGER PRIMARY KEY AUTOINCREMENT, platform TEXT, name TEXT,
            pixel_id TEXT, access_token TEXT, test_event_code TEXT, is_default INT DEFAULT 0,
            status INT DEFAULT 1, notes TEXT)");
$PDO->exec("CREATE TABLE products (id INTEGER PRIMARY KEY, category_id INT, title TEXT, slug TEXT,
            short_desc TEXT, cover_image TEXT, base_price REAL, status INT,
            deleted_at TEXT DEFAULT NULL, fb_pixel_id INT, tt_pixel_id INT)");
$PDO->exec("CREATE TABLE leads (id INTEGER PRIMARY KEY, product_id INT)");

$PDO->exec("INSERT INTO categories (id,name,slug,position) VALUES (1,'ملابس','apparel',1)");
$PDO->exec("INSERT INTO pixels (platform,name,pixel_id,is_default,status) VALUES
    ('facebook','Meta الرئيسي','111',1,1), ('facebook','Meta شتاء','222',0,1),
    ('tiktok','TikTok الرئيسي','TT1',1,1)");

// 30 products so paging is actually exercised.
$ins = $PDO->prepare("INSERT INTO products (id,category_id,title,slug,short_desc,cover_image,base_price,status,fb_pixel_id,tt_pixel_id)
                      VALUES (?,?,?,?,?,?,?,?,?,?)");
for ($i = 1; $i <= 30; $i++) {
    $ins->execute([$i, 1, "منتج $i", "product-$i", "وصف $i", '', 100 + $i, $i % 5 === 0 ? 0 : 1,
                   $i <= 3 ? 2 : null, $i === 1 ? 0 : null]);
}
$PDO->exec("INSERT INTO products (id,category_id,title,slug,short_desc,cover_image,base_price,status)
            VALUES (99,1,'سروال كاجوال','casual-pants','سروال أنيق','',249,1)");
$PDO->exec("INSERT INTO leads (product_id) VALUES (99),(99),(99),(1)");

// Retired pages: hidden from the list, still in the table with their orders.
$PDO->exec("UPDATE products SET deleted_at = '2026-09-01 10:00:00' WHERE id IN (11,12)");

// ── pagination ─────────────────────────────────────────────────────────────
$r = Product::paginate([], 1, 10);
check('page 1 returns one page worth', count($r['rows']) === 10, count($r['rows']) . ' rows');
check('retired products are hidden',   $r['total'] === 29, (string)$r['total']);
check('page count reflects that',      $r['pages'] === 3, (string)$r['pages']);
check('newest first',                  (int)$r['rows'][0]['id'] === 99);

$r3 = Product::paginate([], 3, 10);
check('the last page has the remainder', count($r3['rows']) === 9, count($r3['rows']) . ' rows');
check('page beyond the end clamps',      Product::paginate([], 999, 10)['page'] === 3);
check('page 0 clamps to 1',              Product::paginate([], 0, 10)['page'] === 1);
check('per-page is clamped low',         Product::paginate([], 1, 1)['per_page'] === 5);
check('per-page is clamped high',        Product::paginate([], 1, 9999)['per_page'] === 100);
check('rows carry the category name',    ($r['rows'][0]['category_name'] ?? null) === 'ملابس');
check('rows carry an order count',       (int)$r['rows'][0]['lead_count'] === 3, (string)$r['rows'][0]['lead_count']);

// ── search ─────────────────────────────────────────────────────────────────
check('search matches the title',      Product::paginate(['q' => 'سروال'])['total'] === 1);
check('search matches the slug',       Product::paginate(['q' => 'casual-pants'])['total'] === 1);
check('search matches the description', Product::paginate(['q' => 'أنيق'])['total'] === 1);
check('search is a partial match',     Product::paginate(['q' => 'product-1'])['total'] >= 1);
check('search with no hits is empty',  Product::paginate(['q' => 'zzzz'])['total'] === 0);
check('a search wildcard is not injectable',
    Product::paginate(['q' => "' OR 1=1 --"])['total'] === 0);

// ── filters ────────────────────────────────────────────────────────────────
check('status filter: inactive', Product::paginate(['status' => '0'])['total'] === 6, (string)Product::paginate(['status' => '0'])['total']);
// 30 seeded (6 inactive) + 1 extra, minus the 2 retired actives.
check('status filter: active',   Product::paginate(['status' => '1'])['total'] === 23,
    (string)Product::paginate(['status' => '1'])['total']);
check('empty status means all',  Product::paginate(['status' => ''])['total'] === 29);
check('category filter',         Product::paginate(['category_id' => 1])['total'] === 29);

// The question "which pages report to this ad account".
check('pixel filter finds assigned pages', Product::paginate(['pixel_id' => '2'])['total'] === 3,
    (string)Product::paginate(['pixel_id' => '2'])['total']);
check('pixel filter ignores unassigned',   Product::paginate(['pixel_id' => '1'])['total'] === 0);

// Neither retired id is inactive, so the inactive count is untouched by them.
check('filters combine', Product::paginate(['q' => 'منتج', 'status' => '0'])['total'] === 6,
    (string)Product::paginate(['q' => 'منتج', 'status' => '0'])['total']);

// ── the trash ──────────────────────────────────────────────────────────────
$trash = Product::paginate(['trashed' => '1']);
check('the trash lists only retired products', $trash['total'] === 2, (string)$trash['total']);
check('the trash rows are the retired ones',
    array_column($trash['rows'], 'id') === [12, 11], implode(',', array_column($trash['rows'], 'id')));
check('trashCount matches',      Product::trashCount() === 2, (string)Product::trashCount());

// Retiring hides a page without touching its orders.
$leadsBefore = (int)$PDO->query("SELECT COUNT(*) FROM leads WHERE product_id = 99")->fetchColumn();
Product::softDelete(99);
check('retiring removes it from the list',  Product::paginate(['q' => 'سروال'])['total'] === 0);
check('retiring puts it in the trash',      Product::paginate(['trashed' => '1'])['total'] === 3);
check('retiring keeps every order',
    (int)$PDO->query("SELECT COUNT(*) FROM leads WHERE product_id = 99")->fetchColumn() === $leadsBefore);
check('retiring also deactivates it',
    (int)$PDO->query("SELECT status FROM products WHERE id = 99")->fetchColumn() === 0);
check('a retired product is still reachable for restore', Product::findAny(99) !== null);
check('but not through the public finder', Product::find(99) === null);

Product::restore(99);
check('restoring brings it back', Product::paginate(['q' => 'سروال'])['total'] === 1);
check('restore empties it from the trash', Product::trashCount() === 2);

// ── the view renders ───────────────────────────────────────────────────────
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_url($p = '') { return '/lp_tifaw/' . ltrim($p, '/'); }
function upload_url($p) { return base_url($p); }
function csrf_token() { return str_repeat('c', 64); }

$res     = Product::paginate([], 1, 10);
$filters = ['q' => '', 'status' => '', 'pixel_id' => '', 'category_id' => '', 'trashed' => ''];
$cats    = [['id' => 1, 'name' => 'ملابس']];
$trashCount = Product::trashCount();
$pixels  = Pixel::grouped();
$msg = null; $err = null;

ob_start(); include $ROOT . '/admin/views/products.php'; $html = ob_get_clean();

check('list renders',                 strlen($html) > 3000);
check('list has no PHP warning',      !str_contains($html, 'Warning:') && !str_contains($html, 'Notice:'));
check('list has a search box',        str_contains($html, 'name="q"'));
check('list has a status filter',     str_contains($html, 'name="status"'));
check('list has a pixel filter',      str_contains($html, 'name="pixel_id"'));
check('list shows the total',         str_contains($html, '29 منتج'));
check('list has a pager',             str_contains($html, 'class="pager"'));
check('pager marks the current page', str_contains($html, 'class="active"'));
check('list has row checkboxes',      substr_count($html, 'name="ids[]"') === 10);
check('list has a select-all',        str_contains($html, 'id="pCheckAll"'));
check('bulk bar offers activate',     str_contains($html, 'data-bulk="activate"'));
check('bulk bar offers pixel assign', str_contains($html, 'data-bulk="set_fb_pixel"'));
check('bulk delete needs confirmation', str_contains($html, 'confirm_delete'));
check('delete warns about linked orders', str_contains($html, 'data-leads='));
check('clone posts to its own form',  str_contains($html, 'id="cloneForm"'));
check('the clone form is outside the bulk form',
    strpos($html, 'id="cloneForm"') > strpos($html, '</form>'));
check('list shows the pixel per row', str_contains($html, 'px-tag fb'));
check('list shows order counts',      str_contains($html, '<td>3</td>'));

// An empty result set must explain itself.
$res = Product::paginate(['q' => 'zzzz']);
$filters = ['q' => 'zzzz', 'status' => '', 'pixel_id' => '', 'category_id' => '', 'trashed' => ''];
ob_start(); include $ROOT . '/admin/views/products.php'; $emptyHtml = ob_get_clean();
check('no results renders a message', str_contains($emptyHtml, 'لا توجد منتجات مطابقة'));
check('no results still shows the filters', str_contains($emptyHtml, 'value="zzzz"'));
check('no results offers a reset link',     str_contains($emptyHtml, 'مسح'));
check('no results hides the pager',         !str_contains($emptyHtml, 'class="pager"'));

// ── the trash screen ───────────────────────────────────────────────────────
$res     = Product::paginate(['trashed' => '1']);
$filters = ['q' => '', 'status' => '', 'pixel_id' => '', 'category_id' => '', 'trashed' => '1'];
ob_start(); include $ROOT . '/admin/views/products.php'; $trashHtml = ob_get_clean();

check('the trash screen renders',        strlen($trashHtml) > 2000);
check('the trash screen has no warning', !str_contains($trashHtml, 'Warning:') && !str_contains($trashHtml, 'Notice:'));
check('the trash warns about orders',    str_contains($trashHtml, 'يمحو الطلبات المرتبطة'));
check('the trash offers restore',        str_contains($trashHtml, 'data-bulk="restore"'));
check('the trash offers a hard delete',  str_contains($trashHtml, 'data-bulk="purge"'));
check('the trash hides "new product"',   !str_contains($trashHtml, '+ منتج جديد'));
check('the normal list offers the trash', str_contains($html, 'المهملات'));
check('the normal list retires, not deletes', str_contains($html, 'data-bulk="delete"')
                                              && str_contains($html, 'نقل للمهملات'));

// ── the controller's bulk handler ──────────────────────────────────────────
$ctrl = file_get_contents($ROOT . '/admin/products.php');
check('bulk ids are cast to int', str_contains($ctrl, "array_map('intval'"));
check('bulk delete is double-gated', str_contains($ctrl, "!== 'DELETE'"));
check('bulk requires CSRF',        str_contains($ctrl, 'admin_require_csrf()'));
check('bulk pixel keeps the three states', str_contains($ctrl, "\$raw === '' ? null : (int)\$raw"));
check('an unknown action is rejected', str_contains($ctrl, 'إجراء غير معروف'));
check('filters survive the redirect', str_contains($ctrl, "'q'        => \$_POST['f_q']"));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
