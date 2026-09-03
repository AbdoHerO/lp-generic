<?php
class Product {
    public static function allActive(?string $q = null, ?int $categoryId = null): array {
        $sql = "SELECT * FROM products WHERE status=1 AND deleted_at IS NULL";
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
        $st = db()->prepare("SELECT * FROM products WHERE slug = :s AND status=1 AND deleted_at IS NULL LIMIT 1");
        $st->execute([':s' => $slug]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Admin preview — bypasses status filter */
    public static function findBySlugAny(string $slug): ?array {
        $st = db()->prepare("SELECT * FROM products WHERE slug = :s AND deleted_at IS NULL LIMIT 1");
        $st->execute([':s' => $slug]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array {
        $st = db()->prepare("SELECT * FROM products WHERE id = :i AND deleted_at IS NULL LIMIT 1");
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

    /**
     * Admin listing: search, filter and page.
     *
     * The list was an unbounded `SELECT *`, which is fine at ten products and
     * unusable at three hundred — and it loads every row's cover image.
     *
     * @return array{rows: list<array>, total: int, page: int, per_page: int, pages: int}
     */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 25): array {
        // Retired products stay in the table so their orders keep their product
        // title; they are simply not listed unless the trash is asked for.
        $where  = !empty($filters['trashed']) ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL';
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (p.title LIKE :q OR p.slug LIKE :q OR p.short_desc LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if (($filters['status'] ?? '') !== '') {
            $where .= ' AND p.status = :st';
            $params[':st'] = (int)$filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $where .= ' AND p.category_id = :cid';
            $params[':cid'] = (int)$filters['category_id'];
        }
        // "Which pages report to this ad account" — the question that comes up
        // when an account is being retired.
        if (($filters['pixel_id'] ?? '') !== '') {
            $where .= ' AND (p.fb_pixel_id = :px OR p.tt_pixel_id = :px)';
            $params[':px'] = (int)$filters['pixel_id'];
        }

        $countSt = db()->prepare("SELECT COUNT(*) FROM products p WHERE $where");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $perPage = max(5, min(100, $perPage));
        $pages   = max(1, (int)ceil($total / $perPage));
        $page    = max(1, min($page, $pages));
        $offset  = ($page - 1) * $perPage;

        $st = db()->prepare("SELECT p.*, c.name AS category_name,
                                    (SELECT COUNT(*) FROM leads l WHERE l.product_id = p.id) AS lead_count
                             FROM products p
                             LEFT JOIN categories c ON c.id = p.category_id
                             WHERE $where
                             ORDER BY p.id DESC
                             LIMIT $perPage OFFSET $offset");
        $st->execute($params);

        return ['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page,
                'per_page' => $perPage, 'pages' => $pages];
    }

    /**
     * Retire a product: hidden everywhere, orders and their history untouched.
     *
     * The hard delete cascaded orders away, including delivered ones needed for
     * accounting — so "delete" now means this, and the real DELETE is reserved
     * for emptying the trash.
     */
    public static function softDelete(int $id): void {
        db()->prepare("UPDATE products SET deleted_at = NOW(), status = 0 WHERE id = :i")
            ->execute([':i' => $id]);
    }

    public static function restore(int $id): void {
        db()->prepare("UPDATE products SET deleted_at = NULL WHERE id = :i")->execute([':i' => $id]);
    }

    /** Permanent, and it takes the orders with it. Only from the trash screen. */
    public static function purge(int $id): void {
        db()->prepare("DELETE FROM products WHERE id = :i")->execute([':i' => $id]);
    }

    public static function trashCount(): int {
        return (int)db()->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NOT NULL")->fetchColumn();
    }

    /** Admin editing needs to reach a retired product to restore it. */
    public static function findAny(int $id): ?array {
        $st = db()->prepare("SELECT * FROM products WHERE id = :i LIMIT 1");
        $st->execute([':i' => $id]);
        return $st->fetch() ?: null;
    }

    public static function related(int $productId, int $limit = 4): array {
        $st = db()->prepare("SELECT * FROM products WHERE status=1 AND deleted_at IS NULL
                             AND id <> :i ORDER BY RAND() LIMIT $limit");
        $st->execute([':i' => $productId]);
        return $st->fetchAll();
    }
}
