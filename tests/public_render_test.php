<?php
/**
 * Public templates — layout, header/footer branding, and the per-page pixel
 * injection — rendered with stubbed data. No database and no config needed.
 *
 * Run:  php tests/public_render_test.php
 */
error_reporting(E_ALL); ini_set('display_errors', '1');
$ROOT = dirname(__DIR__);
$_SESSION = [];
$_SERVER['HTTP_HOST']   = 'tujjar.store';
$_SERVER['REQUEST_URI'] = '/casual-pants';

$SET = [
  'store_name' => 'tujjar.store', 'store_logo' => 'public/assets/img/logo.svg',
  'store_logo_light' => 'public/assets/img/logo-light.svg', 'store_favicon' => 'public/assets/img/favicon.svg',
  'accent_color' => '#a07a3c', 'gtm_id' => 'GTM-TEST', 'ga_id' => '', 'support_phone' => '+212600000000',
  'whatsapp' => '+212600000000', 'facebook_handle' => 'tujjar', 'header_banner' => 'شحن مجاني',
  'countdown_hours' => '25', 'show_footer_phone' => '1', 'show_footer_whatsapp' => '1', 'show_footer_facebook' => '1',
];
function settings_get(string $k, $d = null) { return $GLOBALS['SET'][$k] ?? $d; }
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_url($p = '') { return '/lp_tifaw/' . ltrim($p, '/'); }
function asset($p) { return base_url('public/assets/' . $p) . '?v=1'; }
function upload_url($p) { return preg_match('#^https?://#i', $p) ? $p : base_url($p); }
function store_logo_url($light = false) { return upload_url(settings_get($light ? 'store_logo_light' : 'store_logo')); }
function store_favicon_url() { return upload_url(settings_get('store_favicon')); }
function pixel_event_id($p) { return $p . '.deadbeefdeadbeef'; }
function request_is_https(): bool { return true; }
function morocco_cities(): array { return ['الدار البيضاء', 'الرباط', 'مراكش']; }
function responsive_img(?string $path, array $attrs = []): string {
    // The real helper lives in helpers.php (needs config + a session); its own
    // markup is covered by tests/image_test.php. Here it only has to render.
    $path = $path ?: 'public/assets/img/placeholder.svg';
    return '<img src="' . e(upload_url($path)) . '" class="' . e($attrs['class'] ?? '')
         . '" alt="' . e($attrs['alt'] ?? '') . '" loading="' . e($attrs['loading'] ?? 'lazy') . '">';
}
function pixel_context() { return $GLOBALS['PX']; }
// The order form signs its render time; the real implementation is used with a
// fixed secret so the rendered token is checkable below.
function app_secret() { return str_repeat('t', 64); }
require_once $ROOT . '/config/tokens.php';

$PX = ['facebook' => ['name' => 'Meta Winter', 'pixel_id' => '640658465078889'],
       'tiktok'   => ['name' => 'TT Main',     'pixel_id' => 'CM08HVJC77U7MRPGKD5G']];

function render_page(string $view, array $data, string $ROOT): string {
    extract($data, EXTR_SKIP);
    ob_start(); include $ROOT . '/src/Views/' . $view . '.php'; $content = ob_get_clean();
    ob_start(); include $ROOT . '/src/Views/layouts/public.php'; return ob_get_clean();
}

$product = ['id' => 1, 'slug' => 'casual-pants', 'title' => 'سروال كاجوال', 'short_desc' => 'وصف',
  'cover_image' => 'uploads/x.png', 'og_image' => 'uploads/x.png', 'base_price' => 249, 'compare_price' => 499,
  'badges' => 'الأكثر مبيعا,شحن مجاني', 'status' => 1, 'fb_pixel_id' => 2, 'tt_pixel_id' => null];
$offers = [
  ['id'=>10,'label'=>'واحد ب 249','quantity'=>1,'total_price'=>249,'compare_price'=>499,'is_recommended'=>0,'free_shipping'=>0,'is_default'=>1,'requires_options'=>1],
  ['id'=>11,'label'=>'إثنان ب 459','quantity'=>2,'total_price'=>459,'compare_price'=>998,'is_recommended'=>1,'free_shipping'=>1,'is_default'=>0,'requires_options'=>1]];
$groups = [['id'=>1,'name'=>'color','label'=>'اللون','type'=>'swatch','is_required'=>1,
            'values'=>[['value'=>'أسود','swatch'=>'#111'],['value'=>'بيج','swatch'=>'#c9b48a']]]];
$media  = [['url'=>'uploads/s1.png','kind'=>'slider','position'=>1],
           ['url'=>'uploads/g1.png','kind'=>'gallery','position'=>1]];
$sections = ['hero' => ['headline'=>'عنوان','subheadline'=>'وصف','badges'=>['جودة'],'cta'=>'اطلب'],
             'features' => [['icon'=>'🚚','title'=>'شحن','text'=>'سريع']],
             'testimonials' => [['name'=>'مريم','text'=>'رائع']],
             'faqs' => [['q'=>'سؤال؟','a'=>'جواب']]];

$checks = [];
$tmp = getenv('TEMP') ?: sys_get_temp_dir();

// --- landing page ----------------------------------------------------------
$html = render_page('product', ['title'=>'سروال','metaDesc'=>'وصف','ogImage'=>'uploads/x.png',
  'product'=>$product,'media'=>$media,'offers'=>$offers,'groups'=>$groups,'related'=>[],'sections'=>$sections], $ROOT);
file_put_contents($tmp . '/smoke_product.html', $html);
$checks['product: renders']              = strlen($html) > 6000;
$checks['product: logo in header']       = str_contains($html, 'class="brand-logo"');
$checks['product: light logo in footer'] = str_contains($html, 'class="ftr-logo"');
$checks['product: favicon link']         = str_contains($html, 'rel="icon"');
$checks['product: fb base code']         = str_contains($html, 'fbq(\'init\', "640658465078889")');
$checks['product: tt base code']         = str_contains($html, 'ttq.load("CM08HVJC77U7MRPGKD5G")');
$checks['product: LPX bridge']           = str_contains($html, 'window.LPX = window.LPX ||');
$checks['product: ViewContent call']     = str_contains($html, 'LPX.track(\'view_content\'');
$checks['product: PRODUCT_DATA slug']    = str_contains($html, '"casual-pants"');
$checks['product: gtm noscript']         = str_contains($html, 'googletagmanager.com/ns.html');
$checks['product: no PHP warning']       = !str_contains($html, 'Warning:') && !str_contains($html, 'Notice:');
$checks['product: canonical link']       = str_contains($html, 'rel="canonical" href="https://tujjar.store/lp_tifaw/casual-pants"');
$checks['product: JSON-LD present']      = str_contains($html, 'application/ld+json');
$checks['product: JSON-LD parses']       = (bool)(preg_match('#ld\+json">(.*?)</script>#s', $html, $ldm)
                                                  && json_decode(trim($ldm[1]), true) !== null);
$checks['product: city datalist']        = str_contains($html, 'list="moroccoCities"')
                                           && str_contains($html, '<datalist id="moroccoCities">');
$checks['product: draft endpoint exposed'] = str_contains($html, 'draftUrl');
$checks['product: image protection scoped'] = str_contains($html, "e.target.tagName === 'IMG'")
                                              && !str_contains($html, "e.key === 'F12'");
$checks['product: honeypot rendered']    = str_contains($html, 'name="website"');
$checks['product: signed stamp']         = (bool)preg_match('/name="form_ts" value="(\d+\.[0-9a-f]{64})"/', $html, $m);
$checks['product: stamp validates']      = isset($m[1]) && form_token_check($m[1])['ok'];

// --- a page whose editor fields were all left blank ------------------------
// The section editor always writes every key, so "blank" reaches the template
// as an empty string. Each of these must fall back rather than render nothing.
require_once $ROOT . '/src/Models/Sections.php';
if (!function_exists('clean_string')) {
    function clean_string($v, int $max = 500): string {
        $v = is_string($v) ? trim($v) : '';
        return mb_substr(preg_replace('/\s+/u', ' ', $v) ?? '', 0, $max, 'UTF-8');
    }
}
$blank = Sections::decode('{"hero":{"headline":"","subheadline":"","badges":[],"cta":""},'
                        . '"features":[],"testimonials":[],"faqs":[],'
                        . '"countdown_title":"","cta_text":""}');
$html5 = render_page('product', ['title'=>'x','metaDesc'=>'','ogImage'=>null,'product'=>$product,
  'media'=>$media,'offers'=>$offers,'groups'=>$groups,'related'=>[],'sections'=>$blank], $ROOT);
$checks['blank hero: falls back to product title'] = str_contains($html5, '<h1 class="p-title">سروال كاجوال</h1>');
$checks['blank hero: falls back to short_desc']    = str_contains($html5, '<p class="p-sub">وصف</p>');
$checks['blank hero: CTA has a default']           = str_contains($html5, '>اطلب الآن</a>');
$checks['blank countdown: has a default title']    = str_contains($html5, 'تخفيض 50%');
$checks['blank CTA text: has a default']           = str_contains($html5, 'إضغط هنا لطلب المنتج');
$checks['blank sections: no empty headings']       = !str_contains($html5, '<h1 class="p-title"></h1>');

// --- per-page theme, a real deadline, and the A/B variant marker ----------
$themed = $product + ['campaign_ends_at' => date('Y-m-d H:i:s', time() + 7200)];
$html6 = render_page('product', ['title'=>'x','metaDesc'=>'','ogImage'=>null,'product'=>$themed,
  'media'=>$media,'offers'=>$offers,'groups'=>$groups,'related'=>[],'sections'=>$sections,
  'pageAccent'=>'#ff5500','pageCta'=>'#00aa66','abVariant'=>'b'], $ROOT);

$checks['theme: page accent overrides the store'] = str_contains($html6, '--accent: #ff5500');
$checks['theme: CTA colour applied']              = str_contains($html6, 'background: #00aa66');
$checks['deadline: countdown carries the end']    = str_contains($html6, 'data-ends="');
$checks['A/B: variant travels with the order']    = str_contains($html6, 'name="ab_variant" value="b"');

// A deadline in the past must remove the countdown entirely rather than
// restarting it — a timer that resets forever is the thing being replaced.
$expired = $product + ['campaign_ends_at' => date('Y-m-d H:i:s', time() - 3600)];
$html7 = render_page('product', ['title'=>'x','metaDesc'=>'','ogImage'=>null,'product'=>$expired,
  'media'=>$media,'offers'=>$offers,'groups'=>$groups,'related'=>[],'sections'=>$sections], $ROOT);
$checks['deadline: an expired campaign hides the countdown'] = !str_contains($html7, 'id="countdown"');
$checks['deadline: the rest of the page still renders']      = str_contains($html7, 'id="orderForm"');

// No A/B on the page means no marker on the order.
$checks['A/B: no marker when not testing'] = !str_contains($html, 'name="ab_variant"');

// --- thank-you, real order -------------------------------------------------
$purchase = ['order_id'=>77,'id'=>'casual-pants','name'=>'سروال','value'=>459.0,
             'quantity'=>2,'currency'=>'MAD','phone'=>'0612345678','event_id'=>'purchase.77'];
$html2 = render_page('thank-you', ['title'=>'شكراً','purchase'=>$purchase], $ROOT);
file_put_contents($tmp . '/smoke_ty.html', $html2);
$checks['thankyou: purchase event']  = str_contains($html2, 'LPX.track(\'purchase\'');
$checks['thankyou: server value 459'] = str_contains($html2, '"value":459');
$checks['thankyou: identify phone']  = str_contains($html2, 'identify({ phone_number: "0612345678"');
$checks['thankyou: order ref shown'] = str_contains($html2, '#77');

// --- thank-you, direct visit (no claim on the order) -----------------------
$html3 = render_page('thank-you', ['title'=>'شكراً','purchase'=>null], $ROOT);
$checks['thankyou(direct): no purchase'] = !str_contains($html3, 'LPX.track(\'purchase\'');
$checks['thankyou(direct): renders']     = str_contains($html3, 'تم استلام طلبك');

// --- a page with TikTok deliberately off -----------------------------------
$PX = ['facebook' => ['name'=>'Meta','pixel_id'=>'111'], 'tiktok' => null];
$html4 = render_page('product', ['title'=>'x','metaDesc'=>'','ogImage'=>null,'product'=>$product,
  'media'=>$media,'offers'=>$offers,'groups'=>$groups,'related'=>[],'sections'=>$sections], $ROOT);
$checks['tt-off: fb still present'] = str_contains($html4, 'fbevents.js');
$checks['tt-off: tt absent']        = !str_contains($html4, 'analytics.tiktok.com');

$fail = 0;
foreach ($checks as $k => $v) { printf("%-38s %s\n", $k, $v ? 'ok' : 'FAIL'); if (!$v) $fail++; }
echo "\n" . (count($checks) - $fail) . '/' . count($checks) . " passed\n";
exit($fail ? 1 : 0);
