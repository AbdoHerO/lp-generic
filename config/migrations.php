<?php
/**
 * Incremental schema migrations.
 *
 * sql/schema.sql is the "fresh install" definition and it DROPs tables, so it
 * can never run against a live database. Anything that has to reach an existing
 * install goes here instead: each migration runs once, is recorded in
 * schema_migrations, and must be safe to re-run if that record is ever lost.
 *
 * Called from db() on every request. The cost is one indexed SELECT.
 */

function run_migrations(PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version    VARCHAR(64) PRIMARY KEY,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $applied = $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
        $applied = array_flip($applied);

        foreach (migrations_list() as $version => $migration) {
            if (isset($applied[$version])) continue;
            $migration($pdo);
            $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)")->execute([$version]);
        }
    } catch (Throwable $e) {
        // A migration failure must not take the storefront down. Surface it in
        // the log; the admin panel shows the same state on its next boot.
        error_log('migration failed: ' . $e->getMessage());
    }
}

/** @return array<string, callable(PDO):void> ordered oldest → newest */
function migrations_list(): array {
    return [
        '2026_09_03_001_pixels'        => 'migration_pixels',
        '2026_09_03_002_product_pixel' => 'migration_product_pixel_columns',
        '2026_09_03_003_rebrand'       => 'migration_rebrand_tujjar_store',
        '2026_09_04_004_throttle'      => 'migration_throttle_hits',
        '2026_09_04_005_roles_audit'   => 'migration_roles_and_activity',
        '2026_09_04_006_soft_delete'   => 'migration_soft_delete',
        '2026_09_04_007_page_options'  => 'migration_page_options',
        '2026_09_04_008_drafts'        => 'migration_lead_drafts',
    ];
}

// ── helpers ────────────────────────────────────────────────────────────────

function _mig_has_column(PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

function _mig_has_table(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
}

function _mig_setting(PDO $pdo, string $key): ?string {
    $st = $pdo->prepare("SELECT v FROM settings WHERE k = ? LIMIT 1");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string)$v;
}

function _mig_set_setting(PDO $pdo, string $key, ?string $value): void {
    $pdo->prepare("INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v = VALUES(v)")
        ->execute([$key, $value]);
}

// ── migrations ─────────────────────────────────────────────────────────────

/**
 * The pixels table, seeded from the two single-pixel settings so an existing
 * install keeps firing the exact pixels it fired before this change.
 */
function migration_pixels(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pixels (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        platform        ENUM('facebook','tiktok') NOT NULL,
        name            VARCHAR(120) NOT NULL,
        pixel_id        VARCHAR(80)  NOT NULL,
        access_token    TEXT NULL,
        test_event_code VARCHAR(40) NULL,
        is_default      TINYINT(1) DEFAULT 0,
        status          TINYINT(1) DEFAULT 1,
        notes           VARCHAR(255) NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_pixels_platform_pid (platform, pixel_id),
        INDEX idx_pixels_platform (platform, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ins = $pdo->prepare("INSERT IGNORE INTO pixels (platform, name, pixel_id, is_default, status, notes)
                          VALUES (?,?,?,1,1,?)");
    $note = 'مستورد تلقائياً من الإعدادات العامة';

    $fb = trim((string)_mig_setting($pdo, 'fb_pixel_id'));
    if ($fb !== '') $ins->execute(['facebook', 'Meta — الافتراضي', $fb, $note]);

    $tt = trim((string)_mig_setting($pdo, 'tiktok_pixel_id'));
    if ($tt !== '') $ins->execute(['tiktok', 'TikTok — الافتراضي', $tt, $note]);
}

/** Per-landing-page pixel selection. NULL = inherit, 0 = off, N = pixels.id. */
function migration_product_pixel_columns(PDO $pdo): void {
    if (!_mig_has_column($pdo, 'products', 'fb_pixel_id')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN fb_pixel_id INT NULL AFTER og_image");
    }
    if (!_mig_has_column($pdo, 'products', 'tt_pixel_id')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN tt_pixel_id INT NULL AFTER fb_pixel_id");
    }
}

/** Shared rate-limit ledger for admin sign-in and order submission. */
function migration_throttle_hits(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS throttle_hits (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        bucket     VARCHAR(64) NOT NULL,
        ip         VARCHAR(64) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_throttle_bucket (bucket, created_at),
        INDEX idx_throttle_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Admin roles and an activity trail.
 *
 * One shared account with unlimited rights is fine for one person and a problem
 * the moment anyone else works the order queue: the caller who only needs to
 * change a status can also delete a product and every order attached to it.
 */
function migration_roles_and_activity(PDO $pdo): void {
    if (!_mig_has_column($pdo, 'admins', 'role')) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN role ENUM('admin','agent') NOT NULL DEFAULT 'admin' AFTER password_hash");
    }
    if (!_mig_has_column($pdo, 'admins', 'status')) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
    }
    if (!_mig_has_column($pdo, 'admins', 'last_login_at')) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL");
    }

    // Existing accounts keep full rights: a migration must never lock the only
    // operator out of their own panel.
    $pdo->exec("UPDATE admins SET role = 'admin' WHERE role IS NULL OR role = ''");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        admin_id    INT NULL,
        admin_name  VARCHAR(80) NULL,
        action      VARCHAR(40) NOT NULL,
        entity      VARCHAR(40) NOT NULL,
        entity_id   INT NULL,
        summary     VARCHAR(255) NULL,
        ip          VARCHAR(64) NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_created (created_at),
        INDEX idx_activity_entity (entity, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Soft delete for products.
 *
 * Deleting a product cascades its orders away, including delivered ones that
 * are needed for accounting. A retired page should disappear from the store and
 * stay in the ledger.
 */
function migration_soft_delete(PDO $pdo): void {
    if (!_mig_has_column($pdo, 'products', 'deleted_at')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
        $pdo->exec("CREATE INDEX idx_products_deleted ON products (deleted_at)");
    }
}

/**
 * Per-page presentation, a real campaign deadline, and A/B variants.
 *
 * All three are per landing page rather than store-wide, because that is the
 * level campaigns are run at: a page can match the creative that sent the
 * visitor, end when the promotion actually ends, and test its own copy.
 */
function migration_page_options(PDO $pdo): void {
    $cols = [
        // Message-match: the page can carry the ad's colours.
        'accent_color'     => "VARCHAR(9) NULL",
        'cta_color'        => "VARCHAR(9) NULL",
        // A genuine deadline, as an alternative to the rolling localStorage timer.
        'campaign_ends_at' => "DATETIME NULL",
        // A/B: variant B's sections, and the split.
        'ab_enabled'       => "TINYINT(1) NOT NULL DEFAULT 0",
        'ab_split'         => "TINYINT UNSIGNED NOT NULL DEFAULT 50",
        'sections_json_b'  => "LONGTEXT NULL",
    ];
    foreach ($cols as $name => $type) {
        if (!_mig_has_column($pdo, 'products', $name)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN `$name` $type");
        }
    }

    // Which variant an order came from — without this the test cannot be read.
    if (!_mig_has_column($pdo, 'leads', 'ab_variant')) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN ab_variant CHAR(1) NULL AFTER source");
    }
}

/**
 * Abandoned-form capture.
 *
 * A separate table, not a lead with a special status: a draft is not an order,
 * must never reach the order queue, the revenue report or a conversion pixel,
 * and needs its own retention rules.
 */
function migration_lead_drafts(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lead_drafts (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        product_id  INT NULL,
        phone       VARCHAR(40) NOT NULL,
        fullname    VARCHAR(160) NULL,
        offer_id    INT NULL,
        source      VARCHAR(40) NULL,
        converted   TINYINT(1) NOT NULL DEFAULT 0,
        ip          VARCHAR(64) NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_draft_phone_product (phone, product_id),
        INDEX idx_drafts_created (created_at, converted)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Rebrand CasaLux → tujjar.store.
 *
 * Only rewrites values that are still the shipped CasaLux defaults, so a store
 * that already picked its own name or uploaded its own logo keeps them.
 */
function migration_rebrand_tujjar_store(PDO $pdo): void {
    $name = _mig_setting($pdo, 'store_name');
    if ($name === null || $name === '' || stripos($name, 'casalu') !== false) {
        _mig_set_setting($pdo, 'store_name', 'tujjar.store');
    }

    $logo = (string)_mig_setting($pdo, 'store_logo');
    $isOldDefault = $logo === '' || str_contains($logo, 'lucci-moriny.sirv.com');
    if ($isOldDefault) {
        _mig_set_setting($pdo, 'store_logo', 'public/assets/img/logo.svg');
    }
    if (_mig_setting($pdo, 'store_logo_light') === null) {
        _mig_set_setting($pdo, 'store_logo_light', 'public/assets/img/logo-light.svg');
    }
    if (_mig_setting($pdo, 'store_favicon') === null) {
        _mig_set_setting($pdo, 'store_favicon', 'public/assets/img/favicon.svg');
    }
}
