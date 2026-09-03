<?php
/**
 * PageTemplate — a starting point for a new landing page.
 *
 * Clone already covers "the same as the last one". This covers the other case:
 * a new category, where the structure is familiar but nothing exists to copy.
 * A template fills in the editorial content, the option groups and the pricing
 * tiers, so the first save produces a page that is already coherent rather than
 * a blank form.
 *
 * Templates are JSON files in admin/templates/ — adding one is dropping a file
 * in, with no code change and no migration.
 *
 * Prices are deliberately absent. A template that guessed at prices would
 * either be ignored or, worse, published.
 */
class PageTemplate {
    private static function dir(): string {
        return dirname(__DIR__, 2) . '/admin/templates';
    }

    /** @return array<string, array> keyed by slug (the filename) */
    public static function all(): array {
        $out = [];
        foreach (glob(self::dir() . '/*.json') ?: [] as $file) {
            $key  = basename($file, '.json');
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data)) {
                require_once __DIR__ . '/Log.php';
                Log::warning('Unreadable page template', ['file' => basename($file)]);
                continue;
            }
            $data['key'] = $key;
            $out[$key] = $data;
        }
        ksort($out);
        return $out;
    }

    public static function find(string $key): ?array {
        // The key becomes a filename, so anything but a plain slug is refused.
        if (!preg_match('/^[a-z0-9_-]+$/', $key)) return null;
        $file = self::dir() . '/' . $key . '.json';
        if (!is_file($file)) return null;

        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data + ['key' => $key] : null;
    }

    /**
     * Create a product from a template.
     *
     * The product is created inactive with a placeholder title, because it has
     * no images and no prices yet — publishing it at this point would put an
     * empty page on the domain.
     *
     * @return int the new product id
     */
    public static function apply(string $key, string $title): int {
        $tpl = self::find($key);
        if (!$tpl) throw new InvalidArgumentException('Unknown template: ' . $key);

        require_once __DIR__ . '/Sections.php';

        $pdo  = db();
        $slug = self::uniqueSlug($pdo, $title ?: $key);

        $pdo->beginTransaction();
        try {
            $sections = Sections::normalise($tpl['sections'] ?? []);
            // The headline placeholder is replaced by the real title, so the
            // page reads correctly before anything else is edited.
            if (($sections['hero']['headline'] ?? '') === 'اسم المنتج هنا') {
                $sections['hero']['headline'] = $title;
            }

            $pdo->prepare(
                "INSERT INTO products (title, slug, status, sections_json)
                 VALUES (:t, :s, 0, :j)"
            )->execute([':t' => $title, ':s' => $slug, ':j' => Sections::encode($sections)]);

            $productId = (int)$pdo->lastInsertId();

            // Option groups and their values.
            $insG = $pdo->prepare("INSERT INTO product_option_groups
                                   (product_id, name, label, type, position, is_required)
                                   VALUES (:p,:n,:l,:t,:po,:r)");
            $insV = $pdo->prepare("INSERT INTO product_option_values
                                   (group_id, value, swatch, position) VALUES (:g,:v,:s,:po)");

            foreach (($tpl['option_groups'] ?? []) as $g) {
                $insG->execute([
                    ':p'  => $productId,
                    ':n'  => (string)($g['name'] ?? 'option'),
                    ':l'  => (string)($g['label'] ?? 'خيار'),
                    ':t'  => in_array($g['type'] ?? '', ['swatch','select','radio','text'], true) ? $g['type'] : 'select',
                    ':po' => (int)($g['position'] ?? 0),
                    ':r'  => !empty($g['is_required']) ? 1 : 0,
                ]);
                $groupId = (int)$pdo->lastInsertId();

                foreach (($g['values'] ?? []) as $i => $v) {
                    $insV->execute([
                        ':g'  => $groupId,
                        ':v'  => (string)($v['value'] ?? ''),
                        ':s'  => $v['swatch'] ?? null,
                        ':po' => $i + 1,
                    ]);
                }
            }

            // Offers, with prices left at zero on purpose — the operator sets
            // them, and a zero is obvious in the editor in a way a guess is not.
            $insO = $pdo->prepare("INSERT INTO product_offers
                (product_id,label,quantity,total_price,is_recommended,free_shipping,is_default,requires_options,position)
                VALUES (:p,:l,:q,0,:r,:f,:d,:ro,:po)");

            $hasOptions = !empty($tpl['option_groups']);
            foreach (($tpl['offers'] ?? []) as $o) {
                $insO->execute([
                    ':p'  => $productId,
                    ':l'  => (string)($o['label'] ?? 'عرض'),
                    ':q'  => max(1, (int)($o['quantity'] ?? 1)),
                    ':r'  => !empty($o['is_recommended']) ? 1 : 0,
                    ':f'  => !empty($o['free_shipping']) ? 1 : 0,
                    ':d'  => !empty($o['is_default']) ? 1 : 0,
                    ':ro' => $hasOptions ? 1 : 0,
                    ':po' => (int)($o['position'] ?? 0),
                ]);
            }

            $pdo->commit();
            return $productId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function uniqueSlug(PDO $pdo, string $from): string {
        // Arabic titles transliterate to nothing, so fall back to a short
        // readable id rather than an empty slug.
        $base = preg_replace('/[^a-z0-9\-]+/', '-', strtolower($from)) ?? '';
        $base = trim($base, '-');
        if ($base === '') $base = 'page-' . substr(bin2hex(random_bytes(4)), 0, 6);

        $check     = $pdo->prepare("SELECT 1 FROM products WHERE slug = :s LIMIT 1");
        $candidate = $base;
        for ($i = 2; $i < 200; $i++) {
            $check->execute([':s' => $candidate]);
            if (!$check->fetchColumn()) return $candidate;
            $candidate = $base . '-' . $i;
        }
        return $base . '-' . time();
    }
}
