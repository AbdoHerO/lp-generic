<?php
/**
 * SEO surface: robots.txt, sitemap.xml, canonical URLs and JSON-LD.
 *
 * Structured data is worth testing precisely because it is invisible: a broken
 * @graph or an invented rating costs rich results for the whole domain and
 * nothing on the page looks wrong.
 *
 * Run:  php tests/seo_test.php
 */

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

$_SERVER['HTTP_HOST']   = 'tujjar.store';
$_SERVER['REQUEST_URI'] = '/casual-pants?fbclid=abc&utm_source=fb';

$PDO = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
function db(): PDO { return $GLOBALS['PDO']; }
function base_url(string $p = '') { return '/' . ltrim($p, '/'); }
function upload_url($p) { return preg_match('#^https?://#i', $p) ? $p : 'https://tujjar.store/' . ltrim($p, '/'); }
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function request_is_https(): bool { return true; }
function settings_get(string $k, $d = null) { return ['store_name' => 'tujjar.store'][$k] ?? $d; }
function clean_string($v, int $max = 500): string { return is_string($v) ? trim($v) : ''; }

$PDO->exec("CREATE TABLE products (id INTEGER PRIMARY KEY, slug TEXT, status INT, updated_at TEXT)");
$PDO->exec("CREATE TABLE categories (id INTEGER PRIMARY KEY, slug TEXT, position INT)");
$PDO->exec("INSERT INTO products (slug,status,updated_at) VALUES
    ('casual-pants',1,'2026-09-01 10:00:00'),
    ('shirt',1,'2026-08-20 10:00:00'),
    ('retired',0,'2026-01-01 10:00:00')");
$PDO->exec("INSERT INTO categories (slug,position) VALUES ('apparel',1)");

require_once $ROOT . '/src/Models/Product.php';
require_once $ROOT . '/src/Controllers/SeoController.php';

// ── robots.txt ─────────────────────────────────────────────────────────────
$seo    = new SeoController();
$robots = $seo->robotsBody();

check('robots disallows the admin',      str_contains($robots, 'Disallow: /admin/'));
check('robots disallows the order page', str_contains($robots, 'Disallow: /thank-you'));
check('robots disallows the lead endpoint', str_contains($robots, 'Disallow: /lead/'));
check('robots disallows search',         str_contains($robots, 'Disallow: /search'));
check('robots points at the sitemap',    str_contains($robots, 'Sitemap: https://tujjar.store/sitemap.xml'));
check('robots allows the rest',          str_contains($robots, 'Allow: /'));

// ── sitemap.xml ────────────────────────────────────────────────────────────
$sitemap = $seo->sitemapBody();

check('sitemap is well-formed XML', (bool)@simplexml_load_string($sitemap),
    (bool)@simplexml_load_string($sitemap) ? '' : 'parse failed');
check('sitemap declares the namespace', str_contains($sitemap, 'http://www.sitemaps.org/schemas/sitemap/0.9'));
check('sitemap lists the home page',    str_contains($sitemap, '<loc>https://tujjar.store/</loc>'));
check('sitemap lists an active product', str_contains($sitemap, '<loc>https://tujjar.store/casual-pants</loc>'));
check('sitemap omits an inactive product', !str_contains($sitemap, 'retired'));
check('sitemap lists categories',       str_contains($sitemap, 'category/apparel'));
check('sitemap lists policy pages',     str_contains($sitemap, 'page/privacy'));
check('sitemap carries lastmod',        str_contains($sitemap, '<lastmod>2026-09-01</lastmod>'));
check('landing pages outrank policies',
    strpos($sitemap, '<priority>0.9</priority>') < strpos($sitemap, '<priority>0.2</priority>'));

$xml = @simplexml_load_string($sitemap);
// home + 2 active products + 1 category + 3 policy pages
check('sitemap has one url per live page', $xml && count($xml->url) === 7, $xml ? (string)count($xml->url) : 'n/a');

// ── JSON-LD ────────────────────────────────────────────────────────────────
require_once $ROOT . '/src/Models/Sections.php';

$product = [
    'id' => 1, 'slug' => 'casual-pants', 'title' => 'سروال كاجوال',
    'short_desc' => 'سروال أنيق', 'seo_description' => 'اطلب سروالك بأفضل سعر',
    'cover_image' => 'uploads/cover.jpg', 'base_price' => 249,
];
$sections = Sections::decode('{"hero":{"headline":"سروال كاجوال كلاس"},'
    . '"testimonials":[{"name":"مريم","text":"زوين بزاف"},{"name":"كريم","text":"جودة عالية"}],'
    . '"faqs":[{"q":"هل تقبلون الدفع عند الاستلام؟","a":"نعم في كل المدن."},{"q":"كم مدة التوصيل؟","a":"1 إلى 3 أيام."}]}');
$offers = [
    ['total_price' => '249.00'], ['total_price' => '459.00'], ['total_price' => '629.00'],
];
$media = [['url' => 'uploads/s1.jpg'], ['url' => 'uploads/s2.jpg']];
$canonical = 'https://tujjar.store/casual-pants';

ob_start(); include $ROOT . '/src/Views/partials/structured-data.php'; $ld = ob_get_clean();

preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $ld, $m);
$data = json_decode(trim($m[1] ?? ''), true);

check('JSON-LD is valid JSON', is_array($data), json_last_error_msg());
check('JSON-LD declares the context', ($data['@context'] ?? '') === 'https://schema.org');

$byType = [];
foreach (($data['@graph'] ?? []) as $node) $byType[$node['@type']] = $node;

check('graph has a Product',        isset($byType['Product']));
check('graph has an FAQPage',       isset($byType['FAQPage']));
check('graph has a BreadcrumbList', isset($byType['BreadcrumbList']));

$p = $byType['Product'] ?? [];
check('product uses the hero headline', ($p['name'] ?? '') === 'سروال كاجوال كلاس');
check('product uses the SEO description', ($p['description'] ?? '') === 'اطلب سروالك بأفضل سعر');
check('product sku is the slug',    ($p['sku'] ?? '') === 'casual-pants');
check('product carries images',     count($p['image'] ?? []) === 3, (string)count($p['image'] ?? []));
check('cover image is first',       ($p['image'][0] ?? '') === 'https://tujjar.store/uploads/cover.jpg');

// Multiple tiers → AggregateOffer with the real span, not a made-up single price.
$o = $p['offers'] ?? [];
check('multiple tiers give an AggregateOffer', ($o['@type'] ?? '') === 'AggregateOffer');
check('low price is the cheapest tier',  ($o['lowPrice'] ?? '') === '249.00');
check('high price is the dearest tier',  ($o['highPrice'] ?? '') === '629.00');
check('offer count matches the tiers',   ($o['offerCount'] ?? 0) === 3);
check('currency is MAD',                 ($o['priceCurrency'] ?? '') === 'MAD');
check('availability is InStock',         str_contains((string)($o['availability'] ?? ''), 'InStock'));

// No invented ratings — this is the rule that keeps rich results enabled.
check('no aggregateRating is invented',  !isset($p['aggregateRating']));
check('testimonials map to reviews',     count($p['review'] ?? []) === 2);
check('reviews carry no star rating',    !isset($p['review'][0]['reviewRating']));
check('review author is the customer',   ($p['review'][0]['author']['name'] ?? '') === 'مريم');

$faq = $byType['FAQPage'] ?? [];
check('every FAQ becomes a Question', count($faq['mainEntity'] ?? []) === 2);
check('the question text is carried',
    ($faq['mainEntity'][0]['name'] ?? '') === 'هل تقبلون الدفع عند الاستلام؟');
check('the answer text is carried',
    ($faq['mainEntity'][0]['acceptedAnswer']['text'] ?? '') === 'نعم في كل المدن.');

check('breadcrumb ends at this page',
    ($byType['BreadcrumbList']['itemListElement'][1]['item'] ?? '') === $canonical);
check('Arabic is not escaped in the output', !str_contains($ld, '\u06'));

// A product with a single tier and no FAQs must still emit valid markup.
$offers   = [['total_price' => '249.00']];
$sections = Sections::decode('{}');
ob_start(); include $ROOT . '/src/Views/partials/structured-data.php'; $ld2 = ob_get_clean();
preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $ld2, $m2);
$d2 = json_decode(trim($m2[1] ?? ''), true);
$byType2 = [];
foreach (($d2['@graph'] ?? []) as $node) $byType2[$node['@type']] = $node;

check('a single tier gives a plain Offer', ($byType2['Product']['offers']['@type'] ?? '') === 'Offer');
check('the single price is exact',         ($byType2['Product']['offers']['price'] ?? '') === '249.00');
check('a single Offer has priceValidUntil', isset($byType2['Product']['offers']['priceValidUntil']));
check('no FAQPage when there are no FAQs',  !isset($byType2['FAQPage']));
check('no review key when there are none',  !isset($byType2['Product']['review']));
check('falls back to the product title',    ($byType2['Product']['name'] ?? '') === 'سروال كاجوال');

// ── canonical and noindex in the layout ────────────────────────────────────
$layout = file_get_contents($ROOT . '/src/Views/layouts/public.php');
check('layout emits a canonical link',   str_contains($layout, 'rel="canonical"'));
check('canonical strips the query string', str_contains($layout, "strtok(\$_SERVER['REQUEST_URI'] ?? '/', '?')"));
check('layout supports noindex',         str_contains($layout, 'noindex,nofollow'));
check('og:url matches the canonical',    str_contains($layout, 'property="og:url" content="<?= e($__canonical) ?>"'));

$leadCtrl = file_get_contents($ROOT . '/src/Controllers/LeadController.php');
check('thank-you is noindex',            str_contains($leadCtrl, "'noindex' => true"));
$homeCtrl = file_get_contents($ROOT . '/src/Controllers/HomeController.php');
check('search results are noindex',      str_contains($homeCtrl, "'noindex'  => true"));

$index = file_get_contents($ROOT . '/index.php');
check('robots.txt is routed',  str_contains($index, "'/robots.txt'"));
check('sitemap.xml is routed', str_contains($index, "'/sitemap.xml'"));
check('SEO routes come before the slug catch-all',
    strpos($index, "'/sitemap.xml'") < strpos($index, "'/{slug}'"));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
