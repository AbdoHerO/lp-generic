<?php
require_once __DIR__ . '/../Models/Product.php';

class HomeController {
    public function index(): void {
        $products = Product::allActive();
        $cats = db()->query("SELECT * FROM categories ORDER BY position")->fetchAll();
        render('home', [
            'title'    => settings_get('store_name', 'متجر'),
            'products' => $products,
            'cats'     => $cats,
            'q'        => '',
            'currentCat' => null,
        ]);
    }

    public function search(): void {
        $q = clean_string($_GET['q'] ?? '', 120);
        $products = Product::allActive($q);
        $cats = db()->query("SELECT * FROM categories ORDER BY position")->fetchAll();
        render('home', [
            'title'    => 'بحث: ' . $q,
            'products' => $products,
            'cats'     => $cats,
            'q'        => $q,
            'currentCat' => null,
        ]);
    }

    public function category(string $slug): void {
        $st = db()->prepare("SELECT * FROM categories WHERE slug=:s LIMIT 1");
        $st->execute([':s' => $slug]);
        $cat = $st->fetch();
        if (!$cat) { not_found(); return; }
        $products = Product::allActive(null, (int)$cat['id']);
        $cats = db()->query("SELECT * FROM categories ORDER BY position")->fetchAll();
        render('home', [
            'title'    => $cat['name'],
            'products' => $products,
            'cats'     => $cats,
            'q'        => '',
            'currentCat' => $cat,
        ]);
    }
}
