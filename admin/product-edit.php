<?php
require __DIR__ . '/_bootstrap.php';
admin_require_admin();

$id = (int)($_GET['id'] ?? 0);
$pdo = db();
$product = null;
$msg = null;

if ($id) {
    $product = Product::findAny($id);
    if (!$product) redirect(base_url('admin/products.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $action = $_POST['action'] ?? 'save';

    // Start from a template: creates an inactive product with content, option
    // groups and pricing tiers already in place, then opens it for editing.
    if ($action === 'from_template') {
        $title = clean_string($_POST['title'] ?? '', 180) ?: 'منتج جديد';
        try {
            $newId = PageTemplate::apply((string)($_POST['template'] ?? ''), $title);
            Activity::log('create', 'product', $newId, 'from template: ' . ($_POST['template'] ?? ''));
            redirect(base_url('admin/product-edit.php?id=' . $newId . '&from_template=1'));
        } catch (Throwable $e) {
            Log::exception('Template apply failed', $e, ['template' => $_POST['template'] ?? '']);
            $msg = 'تعذر إنشاء المنتج من القالب';
        }
    }

    if ($action === 'save') {
        $title = clean_string($_POST['title'] ?? '', 180);
        $slug  = strtolower(trim($_POST['slug'] ?? ''));
        $slug  = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug  = trim($slug, '-');
        if (!$slug) $slug = 'product-' . time();
        $cover = admin_upload_image('cover_image', $product['cover_image'] ?? null, 'cover_image_url');
        $og    = admin_upload_image('og_image',    $product['og_image']    ?? null, 'og_image_url');

        // Landing-page content. The editor posts structured fields; the JSON
        // pane posts a raw string. sections_mode says which one the operator was
        // actually looking at, so the two panes can never fight over the column.
        $existing = Sections::decode($product['sections_json'] ?? null);

        if (($_POST['sections_mode'] ?? 'form') === 'json') {
            $parsed = Sections::validateJson($_POST['sections_json'] ?? '');
            if ($parsed['ok']) {
                $sectionsJson = Sections::encode($parsed['sections']);
            } else {
                // Keeping the previous value is safer than saving a broken page,
                // but it must be said out loud — a silent revert reads as "my
                // edit did not save" with no reason given.
                $msg = 'JSON غير صالح (' . $parsed['error'] . ') — تم الاحتفاظ بالمحتوى السابق';
                $sectionsJson = Sections::encode($existing);
            }
        } else {
            $sectionsJson = Sections::encode(Sections::fromPost($_POST, $existing));
        }

        // Pixel choice per landing page: '' → inherit the platform default,
        // '0' → fire nothing for that platform here, N → pixels.id = N.
        $pixelChoice = static function (string $field) {
            $raw = $_POST[$field] ?? '';
            return $raw === '' ? null : (int)$raw;
        };

        // Campaign options. "Use the store colour" is stored as NULL rather
        // than a copy of the current store colour, so changing the store theme
        // later still reaches these pages.
        $accent = empty($_POST['accent_color_clear']) ? clean_string($_POST['accent_color'] ?? '', 9) : '';
        $cta    = empty($_POST['cta_color_clear'])    ? clean_string($_POST['cta_color'] ?? '', 9)    : '';
        $endsAt = trim((string)($_POST['campaign_ends_at'] ?? ''));
        $endsTs = $endsAt !== '' ? strtotime($endsAt) : false;

        // Variant B is stored raw only when it parses; a broken variant would
        // otherwise blank the page for half the traffic.
        $variantB = trim((string)($_POST['sections_json_b'] ?? ''));
        if ($variantB !== '') {
            $parsedB = Sections::validateJson($variantB);
            if ($parsedB['ok']) {
                $variantB = Sections::encode($parsedB['sections']);
            } else {
                $msg = 'JSON النسخة B غير صالح (' . $parsedB['error'] . ') — تم الاحتفاظ بالنسخة السابقة';
                $variantB = (string)($product['sections_json_b'] ?? '');
            }
        }

        $data = [
            ':accent_color'     => preg_match('/^#[0-9a-f]{6}$/i', $accent) ? $accent : null,
            ':cta_color'        => preg_match('/^#[0-9a-f]{6}$/i', $cta) ? $cta : null,
            ':campaign_ends_at' => $endsTs ? date('Y-m-d H:i:s', $endsTs) : null,
            ':ab_enabled'       => (!empty($_POST['ab_enabled']) && $variantB !== '') ? 1 : 0,
            ':ab_split'         => max(1, min(99, (int)($_POST['ab_split'] ?? 50))),
            ':sections_json_b'  => $variantB !== '' ? $variantB : null,
            ':category_id'    => (int)($_POST['category_id'] ?? 0) ?: null,
            ':title'          => $title,
            ':slug'           => $slug,
            ':short_desc'     => clean_string($_POST['short_desc'] ?? '', 500),
            ':full_desc'      => trim($_POST['full_desc'] ?? ''),
            ':cover_image'    => $cover,
            ':base_price'     => (float)($_POST['base_price'] ?? 0),
            ':compare_price'  => $_POST['compare_price'] !== '' ? (float)$_POST['compare_price'] : null,
            ':badges'         => clean_string($_POST['badges'] ?? '', 255),
            ':status'         => isset($_POST['status']) ? 1 : 0,
            ':seo_title'      => clean_string($_POST['seo_title'] ?? '', 200),
            ':seo_description'=> clean_string($_POST['seo_description'] ?? '', 300),
            ':og_image'       => $og,
            ':fb_pixel_id'    => $pixelChoice('fb_pixel_id'),
            ':tt_pixel_id'    => $pixelChoice('tt_pixel_id'),
            ':sections_json'  => $sectionsJson,
        ];

        if ($product) {
            $data[':id'] = $product['id'];
            $sql = "UPDATE products SET category_id=:category_id, title=:title, slug=:slug,
                    short_desc=:short_desc, full_desc=:full_desc, cover_image=:cover_image,
                    base_price=:base_price, compare_price=:compare_price, badges=:badges,
                    status=:status, seo_title=:seo_title, seo_description=:seo_description,
                    og_image=:og_image, fb_pixel_id=:fb_pixel_id, tt_pixel_id=:tt_pixel_id,
                    accent_color=:accent_color, cta_color=:cta_color,
                    campaign_ends_at=:campaign_ends_at, ab_enabled=:ab_enabled,
                    ab_split=:ab_split, sections_json_b=:sections_json_b,
                    sections_json=:sections_json WHERE id=:id";
            $pdo->prepare($sql)->execute($data);
            $newId = $product['id'];
        } else {
            $sql = "INSERT INTO products (category_id,title,slug,short_desc,full_desc,cover_image,base_price,compare_price,badges,status,seo_title,seo_description,og_image,fb_pixel_id,tt_pixel_id,accent_color,cta_color,campaign_ends_at,ab_enabled,ab_split,sections_json_b,sections_json)
                    VALUES (:category_id,:title,:slug,:short_desc,:full_desc,:cover_image,:base_price,:compare_price,:badges,:status,:seo_title,:seo_description,:og_image,:fb_pixel_id,:tt_pixel_id,:accent_color,:cta_color,:campaign_ends_at,:ab_enabled,:ab_split,:sections_json_b,:sections_json)";
            $pdo->prepare($sql)->execute($data);
            $newId = (int)$pdo->lastInsertId();
        }
        Activity::log($product ? 'update' : 'create', 'product', (int)$newId, $title);
        redirect(base_url('admin/product-edit.php?id=' . $newId . '&saved=1'));
    }

    // Offer fields, shared by add and edit so the two can never drift apart.
    $offerFields = static function (): array {
        return [
            ':l'  => clean_string($_POST['label'] ?? '', 160),
            ':q'  => max(1, (int)($_POST['quantity'] ?? 1)),
            ':t'  => (float)($_POST['total_price'] ?? 0),
            ':c'  => ($_POST['compare_price'] ?? '') !== '' ? (float)$_POST['compare_price'] : null,
            ':r'  => !empty($_POST['is_recommended']) ? 1 : 0,
            ':f'  => !empty($_POST['free_shipping']) ? 1 : 0,
            ':d'  => !empty($_POST['is_default']) ? 1 : 0,
            ':ro' => !empty($_POST['requires_options']) ? 1 : 0,
            ':po' => (int)($_POST['position'] ?? 0),
        ];
    };

    /**
     * Exactly one default per product.
     *
     * The page preselects the default offer on load, so two defaults means the
     * second silently wins and the first looks ignored. Clearing the others
     * here is what makes ticking the box do what it appears to do.
     */
    $keepOneDefault = static function (PDO $pdo, int $productId, int $keepOfferId): void {
        $pdo->prepare("UPDATE product_offers SET is_default = 0
                       WHERE product_id = :p AND id <> :i")
            ->execute([':p' => $productId, ':i' => $keepOfferId]);
    };

    if ($action === 'add_offer' && $product) {
        $data = $offerFields() + [':p' => $product['id']];
        $st = $pdo->prepare("INSERT INTO product_offers (product_id,label,quantity,total_price,compare_price,is_recommended,free_shipping,is_default,requires_options,position)
            VALUES (:p,:l,:q,:t,:c,:r,:f,:d,:ro,:po)");
        $st->execute($data);

        if ($data[':d']) $keepOneDefault($pdo, (int)$product['id'], (int)$pdo->lastInsertId());

        Activity::log('create', 'product', (int)$product['id'], 'offer: ' . $data[':l']);
        redirect(base_url('admin/product-edit.php?id=' . $product['id'] . '&tab=offers'));
    }

    if ($action === 'edit_offer' && $product) {
        $offerId = (int)($_POST['offer_id'] ?? 0);
        $data = $offerFields() + [':i' => $offerId, ':p' => $product['id']];

        // Scoped to this product, so a crafted offer_id cannot edit another
        // page's pricing.
        $pdo->prepare("UPDATE product_offers SET label=:l, quantity=:q, total_price=:t,
                       compare_price=:c, is_recommended=:r, free_shipping=:f, is_default=:d,
                       requires_options=:ro, position=:po
                       WHERE id=:i AND product_id=:p")->execute($data);

        if ($data[':d']) $keepOneDefault($pdo, (int)$product['id'], $offerId);

        Activity::log('update', 'product', (int)$product['id'], 'offer #' . $offerId . ': ' . $data[':l']);
        redirect(base_url('admin/product-edit.php?id=' . $product['id'] . '&tab=offers&offer_saved=' . $offerId));
    }
    if ($action === 'del_offer' && $product) {
        $st = $pdo->prepare("DELETE FROM product_offers WHERE id=:i AND product_id=:p");
        $st->execute([':i'=>(int)$_POST['offer_id'], ':p'=>$product['id']]);
        Activity::log('delete', 'product', (int)$product['id'], 'offer #' . (int)$_POST['offer_id']);
        redirect(base_url('admin/product-edit.php?id=' . $product['id'] . '&tab=offers'));
    }
    if ($action === 'add_group' && $product) {
        $st = $pdo->prepare("INSERT INTO product_option_groups (product_id,name,label,type,position,is_required)
            VALUES (:p,:n,:l,:t,:po,:r)");
        $st->execute([
            ':p'=>$product['id'],
            ':n'=>clean_string($_POST['name'] ?? '', 60),
            ':l'=>clean_string($_POST['label'] ?? '', 120),
            ':t'=>$_POST['type'] ?? 'select',
            ':po'=>(int)($_POST['position'] ?? 0),
            ':r'=>!empty($_POST['is_required']) ? 1 : 0,
        ]);
        redirect(base_url('admin/product-edit.php?id=' . $product['id'] . '#options'));
    }
    if ($action === 'del_group' && $product) {
        $st = $pdo->prepare("DELETE FROM product_option_groups WHERE id=:i AND product_id=:p");
        $st->execute([':i'=>(int)$_POST['group_id'], ':p'=>$product['id']]);
        redirect(base_url('admin/product-edit.php?id=' . $product['id'] . '#options'));
    }
    if ($action === 'add_value' && $product) {
        $st = $pdo->prepare("INSERT INTO product_option_values (group_id,value,swatch,position) VALUES (:g,:v,:s,:p)");
        $st->execute([
            ':g'=>(int)$_POST['group_id'],
            ':v'=>clean_string($_POST['value'] ?? '', 120),
            ':s'=>clean_string($_POST['swatch'] ?? '', 40) ?: null,
            ':p'=>(int)($_POST['position'] ?? 0),
        ]);
        redirect(base_url('admin/product-edit.php?id=' . $product['id'] . '#options'));
    }
    if ($action === 'del_value' && $product) {
        $st = $pdo->prepare("DELETE FROM product_option_values WHERE id=:i");
        $st->execute([':i'=>(int)$_POST['value_id']]);
        redirect(base_url('admin/product-edit.php?id=' . $product['id'] . '#options'));
    }
    if ($action === 'add_media' && $product) {
        $files = admin_upload_multi('media_files', 'media_urls');
        $kind  = $_POST['kind'] === 'slider' ? 'slider' : 'gallery';
        $anchor = $kind === 'slider' ? '#slider' : '#gallery';
        $posQ = $pdo->prepare("SELECT COALESCE(MAX(position),0) FROM product_media WHERE product_id=:p AND kind=:k");
        $posQ->execute([':p'=>$product['id'],':k'=>$kind]);
        $startPos = (int)$posQ->fetchColumn() + 1;
        $st = $pdo->prepare("INSERT INTO product_media (product_id,url,kind,position) VALUES (:p,:u,:k,:po)");
        foreach ($files as $i => $u) {
            $st->execute([':p'=>$product['id'],':u'=>$u,':k'=>$kind,':po'=>$startPos+$i]);
        }
        redirect(base_url('admin/product-edit.php?id=' . $product['id'] . $anchor));
    }
    if ($action === 'del_media' && $product) {
        $st = $pdo->prepare("DELETE FROM product_media WHERE id=:i AND product_id=:p");
        $st->execute([':i'=>(int)$_POST['media_id'], ':p'=>$product['id']]);
        $anchor = ($_POST['media_kind'] ?? '') === 'slider' ? '#slider' : '#gallery';
        redirect(base_url('admin/product-edit.php?id=' . $product['id'] . $anchor));
    }
    if ($action === 'reorder_media' && $product) {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        if (is_array($ids)) {
            $st = $pdo->prepare("UPDATE product_media SET position=:pos WHERE id=:id AND product_id=:p");
            foreach ($ids as $pos => $id) {
                $st->execute([':pos'=>(int)$pos, ':id'=>(int)$id, ':p'=>$product['id']]);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true]);
        exit;
    }
}

$cats = $pdo->query("SELECT * FROM categories ORDER BY position")->fetchAll();
$offers = $product ? Product::offers((int)$product['id']) : [];
$groups = $product ? Product::optionGroups((int)$product['id']) : [];
$media  = $product ? Product::media((int)$product['id']) : [];

require_once __DIR__ . '/../src/Models/Experiment.php';
require_once __DIR__ . '/../src/Models/ProductChecklist.php';

$sections  = Sections::decode($product['sections_json'] ?? null);
$checklist = ProductChecklist::build($product, $offers, $groups, $media, $sections);

admin_render('product-edit', [
    'pixels'   => Pixel::grouped(),
    // Results only mean something once the test has actually run.
    'abResults' => ($product && !empty($product['ab_enabled']))
        ? Experiment::results((int)$product['id']) : null,
    'sections'  => $sections,
    'checklist' => $checklist,
    'tabIssues' => ProductChecklist::tabIssues($checklist),
    'title'   => $product ? 'تعديل: ' . $product['title'] : 'منتج جديد',
    'product' => $product,
    'cats'    => $cats,
    'offers'  => $offers,
    'groups'  => $groups,
    'media'   => $media,
    'templates' => PageTemplate::all(),
    'msg'     => $msg ?? (isset($_GET['saved']) ? 'تم الحفظ'
                 : (isset($_GET['cloned']) ? 'تم إنشاء نسخة من المنتج. عدّل الحقول ثم فعّل الحالة.'
                 : (isset($_GET['from_template'])
                    ? 'تم إنشاء الصفحة من قالب. أضف الصور والأسعار ثم فعّل المنتج.' : null))),
]);
