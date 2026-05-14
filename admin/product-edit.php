<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$id = (int)($_GET['id'] ?? 0);
$pdo = db();
$product = null;
$msg = null;

if ($id) {
    $st = $pdo->prepare("SELECT * FROM products WHERE id=:i");
    $st->execute([':i' => $id]);
    $product = $st->fetch();
    if (!$product) redirect(base_url('admin/products.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $title = clean_string($_POST['title'] ?? '', 180);
        $slug  = strtolower(trim($_POST['slug'] ?? ''));
        $slug  = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug  = trim($slug, '-');
        if (!$slug) $slug = 'product-' . time();
        $cover = admin_upload_image('cover_image', $product['cover_image'] ?? null, 'cover_image_url');
        $og    = admin_upload_image('og_image',    $product['og_image']    ?? null, 'og_image_url');

        $sectionsJson = $_POST['sections_json'] ?? '{}';
        // validate JSON
        if (json_decode($sectionsJson, true) === null && trim($sectionsJson) !== '') {
            $msg = 'JSON غير صالح في الأقسام، تم الحفظ بالقيمة السابقة';
            $sectionsJson = $product['sections_json'] ?? '{}';
        }

        $data = [
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
            ':sections_json'  => $sectionsJson,
        ];

        if ($product) {
            $data[':id'] = $product['id'];
            $sql = "UPDATE products SET category_id=:category_id, title=:title, slug=:slug,
                    short_desc=:short_desc, full_desc=:full_desc, cover_image=:cover_image,
                    base_price=:base_price, compare_price=:compare_price, badges=:badges,
                    status=:status, seo_title=:seo_title, seo_description=:seo_description,
                    og_image=:og_image, sections_json=:sections_json WHERE id=:id";
            $pdo->prepare($sql)->execute($data);
            $newId = $product['id'];
        } else {
            $sql = "INSERT INTO products (category_id,title,slug,short_desc,full_desc,cover_image,base_price,compare_price,badges,status,seo_title,seo_description,og_image,sections_json)
                    VALUES (:category_id,:title,:slug,:short_desc,:full_desc,:cover_image,:base_price,:compare_price,:badges,:status,:seo_title,:seo_description,:og_image,:sections_json)";
            $pdo->prepare($sql)->execute($data);
            $newId = (int)$pdo->lastInsertId();
        }
        redirect(base_url('admin/product-edit.php?id=' . $newId . '&saved=1'));
    }

    if ($action === 'add_offer' && $product) {
        $st = $pdo->prepare("INSERT INTO product_offers (product_id,label,quantity,total_price,compare_price,is_recommended,free_shipping,is_default,requires_options,position)
            VALUES (:p,:l,:q,:t,:c,:r,:f,:d,:ro,:po)");
        $st->execute([
            ':p'=>$product['id'],
            ':l'=>clean_string($_POST['label'] ?? '', 160),
            ':q'=>max(1,(int)$_POST['quantity']),
            ':t'=>(float)$_POST['total_price'],
            ':c'=>$_POST['compare_price'] !== '' ? (float)$_POST['compare_price'] : null,
            ':r'=>!empty($_POST['is_recommended']) ? 1 : 0,
            ':f'=>!empty($_POST['free_shipping']) ? 1 : 0,
            ':d'=>!empty($_POST['is_default']) ? 1 : 0,
            ':ro'=>!empty($_POST['requires_options']) ? 1 : 0,
            ':po'=>(int)($_POST['position'] ?? 0),
        ]);
        redirect(base_url('admin/product-edit.php?id=' . $product['id'] . '#offers'));
    }
    if ($action === 'del_offer' && $product) {
        $st = $pdo->prepare("DELETE FROM product_offers WHERE id=:i AND product_id=:p");
        $st->execute([':i'=>(int)$_POST['offer_id'], ':p'=>$product['id']]);
        redirect(base_url('admin/product-edit.php?id=' . $product['id'] . '#offers'));
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

admin_render('product-edit', [
    'title'   => $product ? 'تعديل: ' . $product['title'] : 'منتج جديد',
    'product' => $product,
    'cats'    => $cats,
    'offers'  => $offers,
    'groups'  => $groups,
    'media'   => $media,
    'msg'     => $msg ?? (isset($_GET['saved']) ? 'تم الحفظ' : (isset($_GET['cloned']) ? 'تم إنشاء نسخة من المنتج. عدّل الحقول ثم فعّل الحالة.' : null)),
]);
