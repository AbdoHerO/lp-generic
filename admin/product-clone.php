<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
admin_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(base_url('admin/products.php'));

$id = (int)($_POST['id'] ?? 0);
if (!$id) redirect(base_url('admin/products.php'));

$pdo = db();
$st = $pdo->prepare("SELECT * FROM products WHERE id=:i LIMIT 1");
$st->execute([':i' => $id]);
$src = $st->fetch();
if (!$src) redirect(base_url('admin/products.php'));

/** Build a unique slug from a base */
function clone_unique_slug(PDO $pdo, string $base): string {
    $base = preg_replace('/[^a-z0-9\-]+/', '-', strtolower($base));
    $base = trim($base, '-');
    if ($base === '') $base = 'product-' . time();
    $candidate = $base . '-copy';
    $i = 2;
    $check = $pdo->prepare("SELECT 1 FROM products WHERE slug=:s LIMIT 1");
    while (true) {
        $check->execute([':s' => $candidate]);
        if (!$check->fetchColumn()) return $candidate;
        $candidate = $base . '-copy-' . $i;
        $i++;
        if ($i > 999) return $base . '-copy-' . time();
    }
}

$newSlug  = clone_unique_slug($pdo, $src['slug']);
$newTitle = $src['title'] . ' (نسخة)';

try {
    $pdo->beginTransaction();

    // 1) Clone the product row (status disabled by default to avoid accidental publish)
    $ins = $pdo->prepare("INSERT INTO products
        (category_id,title,slug,short_desc,full_desc,cover_image,base_price,compare_price,badges,status,seo_title,seo_description,og_image,sections_json)
        VALUES (:category_id,:title,:slug,:short_desc,:full_desc,:cover_image,:base_price,:compare_price,:badges,:status,:seo_title,:seo_description,:og_image,:sections_json)");
    $ins->execute([
        ':category_id'    => $src['category_id'],
        ':title'          => $newTitle,
        ':slug'           => $newSlug,
        ':short_desc'     => $src['short_desc'],
        ':full_desc'      => $src['full_desc'],
        ':cover_image'    => $src['cover_image'],
        ':base_price'     => $src['base_price'],
        ':compare_price'  => $src['compare_price'],
        ':badges'         => $src['badges'],
        ':status'         => 0, // start as inactive
        ':seo_title'      => $src['seo_title'],
        ':seo_description'=> $src['seo_description'],
        ':og_image'       => $src['og_image'],
        ':sections_json'  => $src['sections_json'],
    ]);
    $newId = (int)$pdo->lastInsertId();

    // 2) Clone media (reuse same file URLs — admin can replace later)
    $st = $pdo->prepare("SELECT url, kind, position FROM product_media WHERE product_id=:p ORDER BY id");
    $st->execute([':p' => $id]);
    $insM = $pdo->prepare("INSERT INTO product_media (product_id,url,kind,position) VALUES (:p,:u,:k,:po)");
    foreach ($st->fetchAll() as $m) {
        $insM->execute([':p'=>$newId, ':u'=>$m['url'], ':k'=>$m['kind'], ':po'=>$m['position']]);
    }

    // 3) Clone offers
    $st = $pdo->prepare("SELECT * FROM product_offers WHERE product_id=:p ORDER BY id");
    $st->execute([':p' => $id]);
    $insO = $pdo->prepare("INSERT INTO product_offers
        (product_id,label,quantity,total_price,compare_price,is_recommended,free_shipping,is_default,requires_options,position)
        VALUES (:p,:l,:q,:t,:c,:r,:f,:d,:ro,:po)");
    foreach ($st->fetchAll() as $o) {
        $insO->execute([
            ':p'=>$newId, ':l'=>$o['label'], ':q'=>$o['quantity'], ':t'=>$o['total_price'],
            ':c'=>$o['compare_price'], ':r'=>$o['is_recommended'], ':f'=>$o['free_shipping'],
            ':d'=>$o['is_default'], ':ro'=>$o['requires_options'], ':po'=>$o['position'],
        ]);
    }

    // 4) Clone option groups + their values
    $st = $pdo->prepare("SELECT * FROM product_option_groups WHERE product_id=:p ORDER BY id");
    $st->execute([':p' => $id]);
    $groups = $st->fetchAll();
    $insG = $pdo->prepare("INSERT INTO product_option_groups
        (product_id,name,label,type,position,is_required) VALUES (:p,:n,:l,:t,:po,:r)");
    $insV = $pdo->prepare("INSERT INTO product_option_values
        (group_id,value,swatch,position) VALUES (:g,:v,:s,:po)");
    $stV  = $pdo->prepare("SELECT * FROM product_option_values WHERE group_id=:g ORDER BY id");
    foreach ($groups as $g) {
        $insG->execute([
            ':p'=>$newId, ':n'=>$g['name'], ':l'=>$g['label'],
            ':t'=>$g['type'], ':po'=>$g['position'], ':r'=>$g['is_required'],
        ]);
        $newGroupId = (int)$pdo->lastInsertId();
        $stV->execute([':g' => $g['id']]);
        foreach ($stV->fetchAll() as $v) {
            $insV->execute([
                ':g'=>$newGroupId, ':v'=>$v['value'],
                ':s'=>$v['swatch'], ':po'=>$v['position'],
            ]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('product-clone failed: ' . $e->getMessage());
    redirect(base_url('admin/products.php?clone_err=1'));
}

redirect(base_url('admin/product-edit.php?id=' . $newId . '&cloned=1'));
