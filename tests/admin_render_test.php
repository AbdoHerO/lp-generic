<?php
/**
 * Admin templates — the pixel manager, the product editor's pixel picker and
 * the products list — rendered against an in-memory SQLite database.
 *
 * Run:  php tests/admin_render_test.php
 */
error_reporting(E_ALL); ini_set('display_errors', '1');
$ROOT = dirname(__DIR__);
$_SESSION = ['admin_id' => 1, 'admin_username' => 'admin', 'csrf' => str_repeat('a', 64)];

$PDO = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$PDO->exec("CREATE TABLE pixels (id INTEGER PRIMARY KEY AUTOINCREMENT, platform TEXT, name TEXT,
            pixel_id TEXT, access_token TEXT, test_event_code TEXT, is_default INT DEFAULT 0,
            status INT DEFAULT 1, notes TEXT)");
$PDO->exec("CREATE TABLE products (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, fb_pixel_id INT, tt_pixel_id INT)");
$PDO->exec("INSERT INTO pixels (platform,name,pixel_id,is_default,status,notes) VALUES
   ('facebook','Meta الافتراضي','640658465078889',1,1,'الحساب الرئيسي'),
   ('facebook','Meta شتاء','111222333',0,1,NULL),
   ('tiktok','TikTok الافتراضي','CM08HVJC77U7MRPGKD5G',1,1,NULL)");
$PDO->exec("INSERT INTO products (id,title,slug,fb_pixel_id,tt_pixel_id) VALUES
   (1,'سروال','casual-pants',2,NULL), (2,'قميص','shirt',NULL,0)");

function db(): PDO { return $GLOBALS['PDO']; }
function settings_get(string $k, $d = null) { return ['fb_pixel_id'=>'LEGACY','tiktok_pixel_id'=>''][$k] ?? $d; }
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_url($p = '') { return '/lp_tifaw/' . ltrim($p, '/'); }
function upload_url($p) { return preg_match('#^https?://#i', $p) ? $p : base_url($p); }
function csrf_token() { return $_SESSION['csrf']; }
function clean_string($v, int $max = 500): string {
    $v = is_string($v) ? trim($v) : '';
    return mb_substr(preg_replace('/\s+/u', ' ', $v) ?? '', 0, $max, 'UTF-8');
}
require $ROOT . '/src/Models/Pixel.php';
require $ROOT . '/src/Models/Sections.php';
require $ROOT . '/src/Models/Experiment.php';
require $ROOT . '/src/Models/ProductChecklist.php';

function render_admin(string $view, array $data, string $ROOT): string {
    extract($data, EXTR_SKIP);
    ob_start(); include $ROOT . '/admin/views/' . $view . '.php'; return ob_get_clean();
}

$checks = [];
$tmp = getenv('TEMP') ?: sys_get_temp_dir();

// --- Pixels manager --------------------------------------------------------
$usage = [];
foreach ($PDO->query("SELECT id,title,slug,fb_pixel_id,tt_pixel_id FROM products") as $p) {
    foreach (['fb_pixel_id','tt_pixel_id'] as $c) {
        if ($p[$c] !== null && (int)$p[$c] > 0) $usage[(int)$p[$c]][] = $p;
    }
}
$html = render_admin('pixels', [
    'grouped' => Pixel::grouped(), 'editing' => null, 'usage' => $usage,
    'msg' => null, 'err' => null,
    'legacy' => ['facebook' => 'LEGACY', 'tiktok' => ''],
], $ROOT);
file_put_contents($tmp . '/smoke_admin_pixels.html', $html);
$checks['pixels: renders']           = strlen($html) > 3000;
$checks['pixels: lists meta pixel']  = str_contains($html, '640658465078889');
$checks['pixels: lists tiktok']      = str_contains($html, 'CM08HVJC77U7MRPGKD5G');
$checks['pixels: shows usage page']  = str_contains($html, 'سروال');
$checks['pixels: add form present']  = str_contains($html, 'name="pixel_id"');
$checks['pixels: no PHP warning']    = !str_contains($html, 'Warning:') && !str_contains($html, 'Notice:');

// --- Pixels manager in edit mode ------------------------------------------
$html = render_admin('pixels', [
    'grouped' => Pixel::grouped(), 'editing' => Pixel::find(2), 'usage' => $usage,
    'msg' => 'تم الحفظ', 'err' => null, 'legacy' => ['facebook'=>'','tiktok'=>''],
], $ROOT);
$checks['pixels(edit): prefilled']   = str_contains($html, 'value="111222333"');
$checks['pixels(edit): hidden id']   = str_contains($html, 'name="id" value="2"');

// --- Product editor: the pixel picker -------------------------------------
$prod = ['id'=>1,'title'=>'سروال','slug'=>'casual-pants','short_desc'=>'','full_desc'=>'',
  'cover_image'=>'','og_image'=>'','base_price'=>249,'compare_price'=>499,'badges'=>'a,b','status'=>1,
  'seo_title'=>'','seo_description'=>'','sections_json'=>'{}','category_id'=>null,
  'fb_pixel_id'=>2,'tt_pixel_id'=>null,
  'accent_color'=>'#a07a3c','cta_color'=>null,'campaign_ends_at'=>null,
  'ab_enabled'=>0,'ab_split'=>50,'sections_json_b'=>null];
$sections = Sections::decode('{"hero":{"headline":"عنوان الاختبار","badges":["أ","ب"]},'
    . '"features":[{"icon":"🚚","title":"شحن","text":"سريع"}],'
    . '"testimonials":[{"name":"مريم","text":"رائع"}],"faqs":[{"q":"س؟","a":"ج."}],'
    . '"countdown_title":"عرض","cta_text":"اطلب"}');
$html = render_admin('product-edit', ['product'=>$prod,'cats'=>[],'offers'=>[],'groups'=>[],
  'media'=>[],'msg'=>null,'pixels'=>Pixel::grouped(),'sections'=>$sections,'abResults'=>null,
  'checklist'=>ProductChecklist::build($prod, [], [], [], $sections),
  'tabIssues'=>ProductChecklist::tabIssues(ProductChecklist::build($prod, [], [], [], $sections))], $ROOT);
file_put_contents($tmp . '/smoke_admin_prodedit.html', $html);
$htmlSaved = $html;
$checks['editor: pixel section']       = str_contains($html, 'التتبع والبكسلات');
$checks['editor: fb select']           = str_contains($html, 'name="fb_pixel_id"');
$checks['editor: tt select']           = str_contains($html, 'name="tt_pixel_id"');
$checks['editor: pinned fb selected']  = (bool)preg_match('/<option value="2"\s+selected>/', $html);
$checks['editor: inherit tt selected'] = (bool)preg_match('/<option value=""\s+selected>\s*افتراضي/u', $html);
$checks['editor: off option present']  = str_contains($html, 'بدون تتبع لهذه المنصة');
$checks['editor: no PHP warning']      = !str_contains($html, 'Warning:') && !str_contains($html, 'Notice:');

// The section editor replaced the raw JSON textarea.
$checks['sections: form mode present']   = str_contains($html, 'name="sections_mode"');
$checks['sections: hero headline field'] = str_contains($html, 'value="عنوان الاختبار"');
$checks['sections: badges joined']       = str_contains($html, 'value="أ, ب"');
$checks['sections: feature row filled']  = str_contains($html, 'value="شحن"');
$checks['sections: faq answer filled']   = str_contains($html, '>ج.</textarea>');
$checks['sections: emoji picker']        = str_contains($html, 'icon-pick');
$checks['sections: drag handles']        = str_contains($html, 'rep-drag');
$checks['sections: JSON pane kept']      = str_contains($html, 'id="secJsonArea"');
$checks['sections: JSON pane prefilled'] = str_contains($html, '&quot;headline&quot;: &quot;عنوان الاختبار&quot;');
$checks['sections: JSON pane is escaped']  = !str_contains($html, '</textarea><script>');

// --- Product editor for a brand-new product (no $product row) -------------
$html = render_admin('product-edit', ['product'=>null,'cats'=>[],'offers'=>[],'groups'=>[],
  'media'=>[],'msg'=>null,'pixels'=>Pixel::grouped(),'sections'=>Sections::blank(),'abResults'=>null,
  'checklist'=>ProductChecklist::build(null, [], [], [], Sections::blank()),
  'tabIssues'=>[]], $ROOT);
$checks['editor(new): renders']        = str_contains($html, 'name="fb_pixel_id"');
$checks['editor(new): no PHP warning'] = !str_contains($html, 'Warning:') && !str_contains($html, 'Notice:');
$checks['editor: campaign options']      = str_contains($htmlSaved ?? '', 'name="campaign_ends_at"');
$checks['editor: per-page colours']      = str_contains($htmlSaved ?? '', 'name="accent_color"')
                                           && str_contains($htmlSaved ?? '', 'name="cta_color"');
$checks['editor: A/B panel']             = str_contains($htmlSaved ?? '', 'name="sections_json_b"');
$checks['editor: A/B split field']       = str_contains($htmlSaved ?? '', 'name="ab_split"');
$checks['editor(new): no A/B panel']     = !str_contains($html, 'name="sections_json_b"');
// The editor is tabbed now: panels are hidden in place, not removed, so every
// field must still be in the DOM regardless of which tab is active.
$checks['editor: tab bar present']       = str_contains($htmlSaved ?? '', 'class="pe-tabs"');
$checks['editor: five tabs']             = substr_count($htmlSaved ?? '', 'class="pe-tab"') === 5;
$checks['editor: panels are tagged']     = substr_count($htmlSaved ?? '', 'data-tab="basics"') >= 2
                                           && str_contains($htmlSaved ?? '', 'data-tab="content"')
                                           && str_contains($htmlSaved ?? '', 'data-tab="offers"')
                                           && str_contains($htmlSaved ?? '', 'data-tab="media"')
                                           && str_contains($htmlSaved ?? '', 'data-tab="campaign"');
$checks['editor: readiness panel']       = str_contains($htmlSaved ?? '', 'id="peReadyPanel"');
$checks['editor: checklist items']       = str_contains($htmlSaved ?? '', 'pe-check');
$checks['editor: checklist navigates']   = str_contains($htmlSaved ?? '', 'data-goto=');
$checks['editor: sticky save bar']       = str_contains($htmlSaved ?? '', 'form="productForm"');
$checks['editor: save is outside the form'] =
    strpos($htmlSaved ?? '', 'form="productForm"') > strpos($htmlSaved ?? '', '</form>');
$checks['editor(new): tabs are locked']  = str_contains($html, 'pe-tab-lock') || !str_contains($html, 'class="pe-tabs"');
$checks['editor: preview dock present']   = str_contains($htmlSaved ?? '', 'id="previewDock"');
$checks['editor(new): no preview dock']   = !str_contains($html, 'id="previewDock"');
$checks['editor(new): one empty row per group'] =
    substr_count($html, 'name="sec[features][title][]"') === 1 &&
    substr_count($html, 'name="sec[faqs][q][]"') === 1;

// The products list has its own test — tests/products_list_test.php — because
// it needs real pagination, search and filter data rather than two stub rows.

$fail = 0;
foreach ($checks as $k => $v) { printf("%-38s %s\n", $k, $v ? 'ok' : 'FAIL'); if (!$v) $fail++; }
echo "\n" . (count($checks) - $fail) . '/' . count($checks) . " passed\n";
exit($fail ? 1 : 0);
