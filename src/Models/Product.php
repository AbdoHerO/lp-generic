<?php
class Product {
    public static function allActive(?string $q = null, ?int $categoryId = null): array {
        $sql = "SELECT * FROM products WHERE status=1";
        $params = [];
        if ($q !== null && $q !== '') {
            $sql .= " AND (title LIKE :q OR short_desc LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        if ($categoryId) {
            $sql .= " AND category_id = :cid";
            $params[':cid'] = $categoryId;
        }
        $sql .= " ORDER BY id DESC";
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function findBySlug(string $slug): ?array {
        $st = db()->prepare("SELECT * FROM products WHERE slug = :s AND status=1 LIMIT 1");
        $st->execute([':s' => $slug]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Admin preview — bypasses status filter */
    public static function findBySlugAny(string $slug): ?array {
        $st = db()->prepare("SELECT * FROM products WHERE slug = :s LIMIT 1");
        $st->execute([':s' => $slug]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array {
        $st = db()->prepare("SELECT * FROM products WHERE id = :i LIMIT 1");
        $st->execute([':i' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function media(int $productId): array {
        $st = db()->prepare("SELECT * FROM product_media WHERE product_id = :p ORDER BY position");
        $st->execute([':p' => $productId]);
        return $st->fetchAll();
    }

    public static function offers(int $productId): array {
        $st = db()->prepare("SELECT * FROM product_offers WHERE product_id = :p ORDER BY position");
        $st->execute([':p' => $productId]);
        return $st->fetchAll();
    }

    public static function findOffer(int $offerId, int $productId): ?array {
        $st = db()->prepare("SELECT * FROM product_offers WHERE id=:i AND product_id=:p LIMIT 1");
        $st->execute([':i' => $offerId, ':p' => $productId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function optionGroups(int $productId): array {
        $st = db()->prepare("SELECT * FROM product_option_groups WHERE product_id=:p ORDER BY position");
        $st->execute([':p' => $productId]);
        $groups = $st->fetchAll();
        if (!$groups) return [];
        $ids = array_column($groups, 'id');
        $in  = implode(',', array_map('intval', $ids));
        $vals = db()->query("SELECT * FROM product_option_values WHERE group_id IN ($in) ORDER BY position")->fetchAll();
        $byGroup = [];
        foreach ($vals as $v) $byGroup[$v['group_id']][] = $v;
        foreach ($groups as &$g) $g['values'] = $byGroup[$g['id']] ?? [];
        return $groups;
    }

    public static function related(int $productId, int $limit = 4): array {
        $st = db()->prepare("SELECT * FROM products WHERE status=1 AND id <> :i ORDER BY RAND() LIMIT $limit");
        $st->execute([':i' => $productId]);
        return $st->fetchAll();
    }
}
