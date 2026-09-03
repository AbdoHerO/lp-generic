<?php
/**
 * Schema.org JSON-LD for a landing page.
 *
 * Everything below already exists in the database — price, availability, FAQs,
 * testimonials — so this is free organic reach on pages already being paid for:
 * rich results show the price, the rating and the FAQ accordion directly in
 * Google.
 *
 * Two rules kept deliberately:
 *   • only real data is emitted. An aggregateRating invented from nothing is a
 *     structured-data violation and gets rich results disabled for the domain.
 *   • the offer price is the lowest real offer, matching what the page shows.
 *
 * Expects: $product, $sections, $offers, $media, $canonical
 */
$store = settings_get('store_name', 'tujjar.store');

$prices = array_map(fn($o) => (float)$o['total_price'], $offers);
$low    = $prices ? min($prices) : (float)$product['base_price'];
$high   = $prices ? max($prices) : (float)$product['base_price'];

$images = [];
if (!empty($product['cover_image'])) $images[] = upload_url($product['cover_image']);
foreach ($media as $m) {
    $u = upload_url($m['url']);
    if (!in_array($u, $images, true)) $images[] = $u;
    if (count($images) >= 6) break;
}

$graph = [];

// ── Product ────────────────────────────────────────────────────────────────
$productNode = [
    '@type'       => 'Product',
    '@id'         => $canonical . '#product',
    'name'        => ($sections['hero']['headline'] ?? '') ?: $product['title'],
    'description' => ($product['seo_description'] ?? '') ?: ($product['short_desc'] ?? ''),
    'sku'         => $product['slug'],
    'brand'       => ['@type' => 'Brand', 'name' => $store],
];
if ($images) $productNode['image'] = $images;

$productNode['offers'] = count($prices) > 1
    ? [
        '@type'         => 'AggregateOffer',
        'priceCurrency' => 'MAD',
        'lowPrice'      => number_format($low, 2, '.', ''),
        'highPrice'     => number_format($high, 2, '.', ''),
        'offerCount'    => count($prices),
        'availability'  => 'https://schema.org/InStock',
        'url'           => $canonical,
      ]
    : [
        '@type'         => 'Offer',
        'priceCurrency' => 'MAD',
        'price'         => number_format($low, 2, '.', ''),
        'availability'  => 'https://schema.org/InStock',
        'url'           => $canonical,
        // COD: the price is honoured for as long as the page is up. A year out
        // is the convention for "no scheduled change".
        'priceValidUntil' => date('Y-m-d', strtotime('+1 year')),
      ];

// Testimonials are real customer quotes, so they map to Review. No rating is
// claimed for them: the store does not collect star ratings, and inventing one
// is exactly the kind of thing that gets a domain's rich results revoked.
$reviews = [];
foreach (($sections['testimonials'] ?? []) as $t) {
    if (empty($t['text'])) continue;
    $reviews[] = [
        '@type'         => 'Review',
        'reviewBody'    => $t['text'],
        'author'        => ['@type' => 'Person', 'name' => $t['name'] ?: 'عميل'],
    ];
    if (count($reviews) >= 10) break;
}
if ($reviews) $productNode['review'] = $reviews;

$graph[] = $productNode;

// ── FAQPage ────────────────────────────────────────────────────────────────
$faqs = array_values(array_filter($sections['faqs'] ?? [], fn($f) => !empty($f['q']) && !empty($f['a'])));
if ($faqs) {
    $graph[] = [
        '@type' => 'FAQPage',
        '@id'   => $canonical . '#faq',
        'mainEntity' => array_map(fn($f) => [
            '@type'          => 'Question',
            'name'           => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ], $faqs),
    ];
}

// ── BreadcrumbList ─────────────────────────────────────────────────────────
$origin = (request_is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$graph[] = [
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => $store, 'item' => $origin . base_url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $product['title'], 'item' => $canonical],
    ],
];

$payload = ['@context' => 'https://schema.org', '@graph' => $graph];
?>
<script type="application/ld+json">
<?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>

</script>
