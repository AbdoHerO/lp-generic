-- lp_tifaw schema
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS lead_status_logs;
DROP TABLE IF EXISTS lead_items;
DROP TABLE IF EXISTS leads;
DROP TABLE IF EXISTS product_offers;
DROP TABLE IF EXISTS product_option_values;
DROP TABLE IF EXISTS product_option_groups;
DROP TABLE IF EXISTS product_media;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS settings;

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  position INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  short_desc VARCHAR(500) NULL,
  full_desc MEDIUMTEXT NULL,
  cover_image VARCHAR(255) NULL,
  base_price DECIMAL(10,2) DEFAULT 0,
  compare_price DECIMAL(10,2) NULL,
  badges VARCHAR(255) NULL,         -- comma-separated
  status TINYINT(1) DEFAULT 1,      -- 1 active, 0 inactive
  seo_title VARCHAR(200) NULL,
  seo_description VARCHAR(300) NULL,
  og_image VARCHAR(255) NULL,
  sections_json LONGTEXT NULL,      -- hero/features/testimonials/faqs/cta/policies
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  url VARCHAR(255) NOT NULL,
  kind ENUM('slider','gallery') DEFAULT 'gallery',
  position INT DEFAULT 0,
  CONSTRAINT fk_media_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_option_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,            -- e.g. color, size, tier
  label VARCHAR(120) NOT NULL,          -- arabic label
  type ENUM('swatch','select','radio','text') DEFAULT 'select',
  position INT DEFAULT 0,
  is_required TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_optg_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_option_values (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  value VARCHAR(120) NOT NULL,
  swatch VARCHAR(40) NULL,              -- hex for color swatch
  position INT DEFAULT 0,
  CONSTRAINT fk_optv_group FOREIGN KEY (group_id) REFERENCES product_option_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_offers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  label VARCHAR(160) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  total_price DECIMAL(10,2) NOT NULL,
  compare_price DECIMAL(10,2) NULL,
  is_recommended TINYINT(1) DEFAULT 0,
  free_shipping TINYINT(1) DEFAULT 0,
  is_default TINYINT(1) DEFAULT 0,
  requires_options TINYINT(1) DEFAULT 1,
  position INT DEFAULT 0,
  CONSTRAINT fk_offer_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  product_slug VARCHAR(200) NOT NULL,
  offer_id INT NULL,
  offer_label VARCHAR(200) NULL,
  quantity INT NOT NULL DEFAULT 1,
  total_price DECIMAL(10,2) NOT NULL,
  fullname VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  city VARCHAR(120) NULL,
  address VARCHAR(255) NULL,
  notes VARCHAR(500) NULL,
  status ENUM('new','called','confirmed','shipped','delivered','cancelled','no_answer') DEFAULT 'new',
  source VARCHAR(40) NULL,        -- facebook/tiktok/google/direct
  utm_source VARCHAR(120) NULL,
  utm_medium VARCHAR(120) NULL,
  utm_campaign VARCHAR(120) NULL,
  fbclid VARCHAR(255) NULL,
  ttclid VARCHAR(255) NULL,
  gclid VARCHAR(255) NULL,
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_leads_phone (phone),
  INDEX idx_leads_created (created_at),
  CONSTRAINT fk_leads_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lead_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lead_id INT NOT NULL,
  unit_index INT NOT NULL DEFAULT 1,
  options_json TEXT NULL,         -- {"color":"black","size":"L"}
  CONSTRAINT fk_li_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lead_status_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lead_id INT NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NOT NULL,
  note VARCHAR(500) NULL,
  admin_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lsl_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  k VARCHAR(80) PRIMARY KEY,
  v MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
