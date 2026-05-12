<?php
require_once __DIR__ . '/../Models/Product.php';

class ProductController {
    public function show(string $slug): void {
        // Reserved paths shouldn't reach here, but be safe:
        $reserved = ['admin','public','uploads','config','src','sql','assets'];
        if (in_array($slug, $reserved, true)) { not_found(); return; }

        // If admin is logged in and ?preview=1, allow inactive products
        $isAdmin = !empty($_SESSION['admin_id']);
        $preview = $isAdmin && !empty($_GET['preview']);
        $product = $preview ? Product::findBySlugAny($slug) : Product::findBySlug($slug);
        if (!$product) { not_found(); return; }

        $media   = Product::media((int)$product['id']);
        $offers  = Product::offers((int)$product['id']);
        $groups  = Product::optionGroups((int)$product['id']);
        $related = Product::related((int)$product['id'], 4);
        $sections = json_decode($product['sections_json'] ?? '{}', true) ?: [];

        render('product', [
            'title'    => $product['seo_title'] ?: $product['title'],
            'metaDesc' => $product['seo_description'] ?: $product['short_desc'],
            'ogImage'  => $product['og_image'] ?: $product['cover_image'],
            'product'  => $product,
            'media'    => $media,
            'offers'   => $offers,
            'groups'   => $groups,
            'related'  => $related,
            'sections' => $sections,
        ]);
    }
}
