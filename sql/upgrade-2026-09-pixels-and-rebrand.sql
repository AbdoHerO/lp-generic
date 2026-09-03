-- ---------------------------------------------------------------------------
-- Manual fallback for the per-landing-page pixels + tujjar.store rebrand.
--
-- You normally do NOT need this file: config/migrations.php applies the same
-- changes automatically on the first request after deploying. Run it by hand
-- only if the automatic migration was blocked (a database user without ALTER
-- rights, for example) or if you prefer to migrate during a maintenance window.
--
-- Safe to run once. Re-running raises "duplicate column" errors, which are
-- harmless — they mean the change is already in place.
-- ---------------------------------------------------------------------------
SET NAMES utf8mb4;

-- 1. The pixel library ------------------------------------------------------
CREATE TABLE IF NOT EXISTS pixels (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Carry the two old single-pixel settings over as the platform defaults ---
INSERT IGNORE INTO pixels (platform, name, pixel_id, is_default, status, notes)
SELECT 'facebook', 'Meta — الافتراضي', v, 1, 1, 'مستورد تلقائياً من الإعدادات العامة'
FROM settings WHERE k = 'fb_pixel_id' AND v <> '';

INSERT IGNORE INTO pixels (platform, name, pixel_id, is_default, status, notes)
SELECT 'tiktok', 'TikTok — الافتراضي', v, 1, 1, 'مستورد تلقائياً من الإعدادات العامة'
FROM settings WHERE k = 'tiktok_pixel_id' AND v <> '';

-- 3. Per-landing-page selection --------------------------------------------
--    NULL = inherit the platform default, 0 = no pixel here, N = pixels.id
ALTER TABLE products ADD COLUMN fb_pixel_id INT NULL AFTER og_image;
ALTER TABLE products ADD COLUMN tt_pixel_id INT NULL AFTER fb_pixel_id;

-- 4. Rebrand ----------------------------------------------------------------
INSERT INTO settings (k, v) VALUES ('store_name', 'tujjar.store')
  ON DUPLICATE KEY UPDATE v = 'tujjar.store';
INSERT INTO settings (k, v) VALUES ('store_logo', 'public/assets/img/logo.svg')
  ON DUPLICATE KEY UPDATE v = 'public/assets/img/logo.svg';
INSERT INTO settings (k, v) VALUES ('store_logo_light', 'public/assets/img/logo-light.svg')
  ON DUPLICATE KEY UPDATE v = 'public/assets/img/logo-light.svg';
INSERT INTO settings (k, v) VALUES ('store_favicon', 'public/assets/img/favicon.svg')
  ON DUPLICATE KEY UPDATE v = 'public/assets/img/favicon.svg';

-- 5. Tell the automatic migrator these are already done ---------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
  version    VARCHAR(64) PRIMARY KEY,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations (version) VALUES
('2026_09_03_001_pixels'),
('2026_09_03_002_product_pixel'),
('2026_09_03_003_rebrand');
