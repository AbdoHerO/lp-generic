<?php
/**
 * Pixel — advertising pixels (Meta / TikTok) stored per account, then attached
 * to individual landing pages.
 *
 * A product column (fb_pixel_id / tt_pixel_id) holds one of three states:
 *   NULL  → inherit: use the platform's default pixel (or the legacy settings value)
 *   0     → off: fire no pixel of that platform on this landing page
 *   N     → use pixels.id = N
 *
 * The columns are intentionally FK-free so that 0 can carry the "off" meaning.
 * Pixel::delete() clears any product still pointing at the removed row.
 */
class Pixel {
    public const PLATFORMS = ['facebook', 'tiktok'];

    /** All pixels, optionally filtered to one platform. */
    public static function all(?string $platform = null, bool $activeOnly = false): array {
        $sql = "SELECT * FROM pixels WHERE 1=1";
        $params = [];
        if ($platform !== null) { $sql .= " AND platform = :p"; $params[':p'] = $platform; }
        if ($activeOnly)        { $sql .= " AND status = 1"; }
        $sql .= " ORDER BY platform, is_default DESC, name";
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Pixels grouped by platform: ['facebook' => [...], 'tiktok' => [...]]. */
    public static function grouped(bool $activeOnly = false): array {
        $out = array_fill_keys(self::PLATFORMS, []);
        foreach (self::all(null, $activeOnly) as $row) {
            $out[$row['platform']][] = $row;
        }
        return $out;
    }

    public static function find(int $id): ?array {
        if ($id <= 0) return null;
        $st = db()->prepare("SELECT * FROM pixels WHERE id = :i LIMIT 1");
        $st->execute([':i' => $id]);
        return $st->fetch() ?: null;
    }

    /** The pixel marked as default for a platform (active only). */
    public static function defaultFor(string $platform): ?array {
        $st = db()->prepare("SELECT * FROM pixels WHERE platform = :p AND is_default = 1 AND status = 1 LIMIT 1");
        $st->execute([':p' => $platform]);
        return $st->fetch() ?: null;
    }

    public static function save(array $d, ?int $id = null): int {
        $pdo = db();

        // The edit form never re-renders a stored token, so an empty field means
        // "leave it alone" — otherwise opening and saving a pixel would silently
        // disable its server-side reporting.
        $token = trim((string)($d['access_token'] ?? ''));
        if ($token === '' && $id) {
            $existing = self::find($id);
            $token = (string)($existing['access_token'] ?? '');
        }
        $platform = in_array($d['platform'] ?? '', self::PLATFORMS, true) ? $d['platform'] : 'facebook';
        $params = [
            ':platform'        => $platform,
            ':name'            => clean_string($d['name'] ?? '', 120) ?: strtoupper($platform) . ' Pixel',
            ':pixel_id'        => clean_string($d['pixel_id'] ?? '', 80),
            ':access_token'    => $token !== '' ? $token : null,
            ':test_event_code' => clean_string($d['test_event_code'] ?? '', 40) ?: null,
            ':status'          => !empty($d['status']) ? 1 : 0,
            ':notes'           => clean_string($d['notes'] ?? '', 255) ?: null,
        ];

        if ($id) {
            $params[':id'] = $id;
            $pdo->prepare("UPDATE pixels SET platform=:platform, name=:name, pixel_id=:pixel_id,
                           access_token=:access_token, test_event_code=:test_event_code,
                           status=:status, notes=:notes WHERE id=:id")->execute($params);
        } else {
            $pdo->prepare("INSERT INTO pixels (platform,name,pixel_id,access_token,test_event_code,status,notes)
                           VALUES (:platform,:name,:pixel_id,:access_token,:test_event_code,:status,:notes)")
                ->execute($params);
            $id = (int)$pdo->lastInsertId();
        }

        if (!empty($d['is_default'])) self::makeDefault($id, $platform);
        return $id;
    }

    /** Exactly one default per platform. */
    public static function makeDefault(int $id, string $platform): void {
        $pdo = db();
        $pdo->prepare("UPDATE pixels SET is_default = 0 WHERE platform = :p")->execute([':p' => $platform]);
        $pdo->prepare("UPDATE pixels SET is_default = 1 WHERE id = :i")->execute([':i' => $id]);
    }

    /** Delete a pixel and reset every landing page that referenced it to "inherit". */
    public static function delete(int $id): void {
        $pdo = db();
        $pdo->prepare("UPDATE products SET fb_pixel_id = NULL WHERE fb_pixel_id = :i")->execute([':i' => $id]);
        $pdo->prepare("UPDATE products SET tt_pixel_id = NULL WHERE tt_pixel_id = :i")->execute([':i' => $id]);
        $pdo->prepare("DELETE FROM pixels WHERE id = :i")->execute([':i' => $id]);
    }

    /** How many landing pages explicitly select this pixel. */
    public static function usageCount(int $id): int {
        $st = db()->prepare("SELECT COUNT(*) FROM products WHERE fb_pixel_id = :i OR tt_pixel_id = :i");
        $st->execute([':i' => $id]);
        return (int)$st->fetchColumn();
    }

    /**
     * Resolve which pixels fire on a page.
     *
     * @param array|null $product The landing page being rendered, or null for
     *                            site-wide pages (home, policies, thank-you fallback).
     * @return array{facebook: ?array, tiktok: ?array} rows shaped like the pixels table
     */
    public static function resolve(?array $product = null): array {
        return [
            'facebook' => self::resolveOne('facebook', $product['fb_pixel_id'] ?? null, 'fb_pixel_id'),
            'tiktok'   => self::resolveOne('tiktok',   $product['tt_pixel_id'] ?? null, 'tiktok_pixel_id'),
        ];
    }

    private static function resolveOne(string $platform, $override, string $legacySettingKey): ?array {
        // Explicitly turned off for this landing page.
        if ($override !== null && (int)$override === 0) return null;

        // Explicitly pinned to one pixel.
        if ($override !== null && (int)$override > 0) {
            $row = self::find((int)$override);
            if ($row && (int)$row['status'] === 1) return $row;
            // A disabled or deleted pixel must not silently fall back to another
            // advertiser's pixel — that would send this page's conversions to the
            // wrong ad account.
            return null;
        }

        // Inherit: platform default, then the legacy single-pixel setting.
        $row = self::defaultFor($platform);
        if ($row) return $row;

        $legacy = trim((string)settings_get($legacySettingKey, ''));
        if ($legacy !== '') {
            return [
                'id' => 0, 'platform' => $platform, 'name' => 'Legacy (Settings)',
                'pixel_id' => $legacy, 'access_token' => null, 'test_event_code' => null,
                'is_default' => 1, 'status' => 1, 'notes' => null,
            ];
        }
        return null;
    }

    /** Human label for a product's stored choice — used in admin lists. */
    public static function describeChoice($value, string $platform): string {
        if ($value === null)      return 'افتراضي';
        if ((int)$value === 0)    return 'معطّل';
        $row = self::find((int)$value);
        return $row ? $row['name'] : 'بكسل محذوف';
    }
}
