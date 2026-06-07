-- ============================================================
-- ĐẶC SẢN NHÀ DÂN - MySQL/MariaDB schema + seed data
-- Mục tiêu:
--   1) Chuyển dữ liệu từ Apps Script/Google Sheets sang PHP + MySQL.
--   2) UOM tách riêng, tồn kho tính bằng base UOM theo từng sản phẩm.
--   3) 1 sản phẩm có nhiều ảnh: 1 ảnh base/card + nhiều ảnh detail.
--   4) Phù hợp import bằng XAMPP/phpMyAdmin.
--
-- Cách dùng nhanh:
--   - Tạo folder: products_image/ nằm cùng cấp với index.php và admin.php.
--   - Tạo các thư mục con theo mã sản phẩm (VD: products_image/pro_001/).
--   - Copy ảnh/video sản phẩm vào đúng thư mục và tên file trong bảng product_images.image_path.
--   - Import file SQL này vào phpMyAdmin.
-- ============================================================

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+07:00';
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS dac_san_nha_dan
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dac_san_nha_dan;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW IF EXISTS v_product_cards;
DROP VIEW IF EXISTS v_inventory_summary;
DROP TABLE IF EXISTS purchase_plan_receipt_items;
DROP TABLE IF EXISTS purchase_plan_receipts;
DROP TABLE IF EXISTS purchase_plan_orders;
DROP TABLE IF EXISTS order_item_allocations;
DROP TABLE IF EXISTS plan_items;
DROP TABLE IF EXISTS purchase_plans;
DROP TABLE IF EXISTS inventory_movements;
DROP TABLE IF EXISTS inventory_lots;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS product_uoms;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS shipping_zones;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS admin_users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. MASTER DATA
-- ============================================================

CREATE TABLE settings (
  setting_key   VARCHAR(80) PRIMARY KEY,
  setting_value TEXT,
  note          VARCHAR(255),
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipping_zones (
  zone_id       VARCHAR(40) PRIMARY KEY,
  zone_name     VARCHAR(120) NOT NULL,
  fee_vnd       INT NOT NULL DEFAULT 0,
  is_default    TINYINT(1) NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  category_id    VARCHAR(40) PRIMARY KEY,
  category_name  VARCHAR(120) NOT NULL,
  category_slug  VARCHAR(160) NOT NULL UNIQUE,
  sort_order     INT NOT NULL DEFAULT 0,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  product_id          VARCHAR(40) PRIMARY KEY,
  product_name        VARCHAR(200) NOT NULL,
  product_slug        VARCHAR(220) NOT NULL UNIQUE,
  category_id         VARCHAR(40) NOT NULL,
  category_label      VARCHAR(120) NOT NULL,
  default_source      ENUM('Binh Dinh','Gia Lai','Unknown') NOT NULL DEFAULT 'Unknown',
  short_description   VARCHAR(255),
  full_description    TEXT,
  ingredients         TEXT,
  base_uom_label      VARCHAR(80) NOT NULL COMMENT 'Đơn vị nhỏ nhất để quản lý tồn kho theo từng sản phẩm',
  shelf_life_value    INT DEFAULT 0,
  shelf_life_unit     ENUM('days','months','') DEFAULT '',
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(category_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_products_active_category (is_active, category_id),
  INDEX idx_products_source (default_source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- UOM theo từng sản phẩm.
-- conversion_to_base = 1 UOM này bằng bao nhiêu base unit của sản phẩm.
-- Ví dụ: base của Bò một nắng là Gói 500g.
--        pro_006_500G conversion_to_base = 1
--        pro_006_1KG  conversion_to_base = 2
--        Bán 1 x pro_006_1KG => qty_base = 1 * 2 = trừ 2 Gói 500g.
CREATE TABLE product_uoms (
  uom_id              VARCHAR(60) PRIMARY KEY,
  product_id          VARCHAR(40) NOT NULL,
  uom_label           VARCHAR(120) NOT NULL,
  conversion_to_base  DECIMAL(12,3) NOT NULL DEFAULT 1,
  unit_price_vnd      INT NOT NULL DEFAULT 0,
  cost_price_vnd      INT NOT NULL DEFAULT 0,
  is_base_unit        TINYINT(1) NOT NULL DEFAULT 0,
  is_default          TINYINT(1) NOT NULL DEFAULT 0,
  is_sellable         TINYINT(1) NOT NULL DEFAULT 1,
  is_purchasable      TINYINT(1) NOT NULL DEFAULT 1,
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  sort_order          INT NOT NULL DEFAULT 0,
  note                VARCHAR(255),
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_uom_product FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_uom_product_default (product_id, is_default),
  INDEX idx_uom_product_sellable (product_id, is_sellable, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  image_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id    VARCHAR(40) NOT NULL,
  image_path    VARCHAR(255) NOT NULL COMMENT 'Local path, ví dụ products_image/pro_006_bo_mot_nang_main.jpg',
  source_url    TEXT NULL COMMENT 'URL gốc nếu cần tải ảnh về local',
  image_alt     VARCHAR(255),
  is_base       TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = ảnh chính hiển thị trên card/listing',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  sort_order    INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_images_product_sort (product_id, sort_order),
  INDEX idx_images_base (product_id, is_base)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. CUSTOMER / ORDER
-- ============================================================

CREATE TABLE customers (
  customer_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_name    VARCHAR(160) NOT NULL,
  customer_phone   VARCHAR(30) NOT NULL,
  customer_address VARCHAR(255),
  note             TEXT,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_phone (customer_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  order_id          VARCHAR(40) PRIMARY KEY,
  customer_id       BIGINT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status            ENUM('new','confirmed','ordered','received','ready','done','cancelled') NOT NULL DEFAULT 'new',
  customer_name     VARCHAR(160) NOT NULL,
  customer_phone    VARCHAR(30) NOT NULL,
  customer_address  VARCHAR(255),
  receive_date      DATE NULL,
  note              TEXT,
  shipping_method   ENUM('delivery','pickup') NOT NULL DEFAULT 'delivery',
  shipping_zone_id  VARCHAR(40) NULL,
  shipping_fee_vnd  INT NOT NULL DEFAULT 0,
  subtotal_vnd      INT NOT NULL DEFAULT 0,
  total_vnd         INT NOT NULL DEFAULT 0,
  source_summary    ENUM('Binh Dinh','Gia Lai','Mixed','Unknown') NOT NULL DEFAULT 'Unknown',
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_orders_shipping_zone FOREIGN KEY (shipping_zone_id) REFERENCES shipping_zones(zone_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_orders_status_created (status, created_at),
  INDEX idx_orders_phone (customer_phone),
  INDEX idx_orders_receive_date (receive_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  order_item_id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id                      VARCHAR(40) NOT NULL,
  line_no                       INT NOT NULL,
  product_id                    VARCHAR(40) NOT NULL,
  product_name_snapshot         VARCHAR(200) NOT NULL,
  uom_id                        VARCHAR(60) NOT NULL,
  uom_label_snapshot            VARCHAR(120) NOT NULL,
  source_location               ENUM('Binh Dinh','Gia Lai','Unknown') NOT NULL DEFAULT 'Unknown',
  qty_uom                       DECIMAL(12,3) NOT NULL DEFAULT 0 COMMENT 'Số lượng khách mua theo UOM bán',
  conversion_to_base_snapshot   DECIMAL(12,3) NOT NULL DEFAULT 1 COMMENT 'Chốt tỷ lệ tại thời điểm bán để không lệch lịch sử',
  qty_base                      DECIMAL(12,3) NOT NULL DEFAULT 0 COMMENT 'qty_uom * conversion_to_base_snapshot',
  unit_price_vnd                INT NOT NULL DEFAULT 0,
  line_total_vnd                INT NOT NULL DEFAULT 0,
  allocated_lot_id              VARCHAR(60) NULL,
  planned_plan_id               VARCHAR(60) NULL,
  planned_at                    DATETIME NULL,
  created_at                    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(order_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_order_items_uom FOREIGN KEY (uom_id) REFERENCES product_uoms(uom_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  UNIQUE KEY uq_order_line (order_id, line_no),
  INDEX idx_order_items_product (product_id),
  INDEX idx_order_items_lot (allocated_lot_id),
  INDEX idx_order_items_plan (planned_plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. INVENTORY - LUÔN LƯU BẰNG BASE UOM
-- ============================================================

CREATE TABLE inventory_lots (
  lot_id                       VARCHAR(60) PRIMARY KEY,
  product_id                   VARCHAR(40) NOT NULL,
  source_location              ENUM('Binh Dinh','Gia Lai','Unknown') NOT NULL DEFAULT 'Unknown',
  qty_base_on_hand             DECIMAL(12,3) NOT NULL DEFAULT 0,
  qty_base_reserved            DECIMAL(12,3) NOT NULL DEFAULT 0,
  received_date                DATE NOT NULL,
  expiry_date                  DATE NULL,
  supplier_name                VARCHAR(160),
  cost_per_base_unit_vnd       INT NOT NULL DEFAULT 0,
  received_uom_id              VARCHAR(60) NULL COMMENT 'UOM dùng lúc nhập; tồn vẫn quy đổi về base',
  received_qty_uom             DECIMAL(12,3) DEFAULT 0,
  conversion_to_base_snapshot  DECIMAL(12,3) DEFAULT 1,
  note                         TEXT,
  created_at                   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at                   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_lot_product FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_lot_received_uom FOREIGN KEY (received_uom_id) REFERENCES product_uoms(uom_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_lots_product_expiry (product_id, expiry_date),
  INDEX idx_lots_source (source_location),
  INDEX idx_lots_available (product_id, qty_base_on_hand, qty_base_reserved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_movements (
  movement_id                  VARCHAR(60) PRIMARY KEY,
  created_at                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  movement_type                ENUM('IN','OUT','ADJUST','RESERVE','UNRESERVE') NOT NULL,
  ref_type                     ENUM('ORDER','LOT','MANUAL','PLAN','OPENING') NOT NULL DEFAULT 'MANUAL',
  ref_id                       VARCHAR(80) NULL,
  lot_id                       VARCHAR(60) NULL,
  product_id                   VARCHAR(40) NOT NULL,
  source_location              ENUM('Binh Dinh','Gia Lai','Unknown') NOT NULL DEFAULT 'Unknown',
  uom_id                       VARCHAR(60) NULL,
  qty_uom                      DECIMAL(12,3) DEFAULT 0,
  conversion_to_base_snapshot  DECIMAL(12,3) NOT NULL DEFAULT 1,
  qty_base                     DECIMAL(12,3) NOT NULL DEFAULT 0,
  cost_per_base_unit_vnd       INT NOT NULL DEFAULT 0,
  note                         TEXT,
  CONSTRAINT fk_mov_lot FOREIGN KEY (lot_id) REFERENCES inventory_lots(lot_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_mov_product FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_mov_uom FOREIGN KEY (uom_id) REFERENCES product_uoms(uom_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_mov_product_date (product_id, created_at),
  INDEX idx_mov_type_date (movement_type, created_at),
  INDEX idx_mov_ref (ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_item_allocations (
  allocation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_item_id BIGINT UNSIGNED NOT NULL,
  order_id      VARCHAR(40) NOT NULL,
  lot_id        VARCHAR(60) NOT NULL,
  product_id    VARCHAR(40) NOT NULL,
  qty_base      DECIMAL(12,3) NOT NULL DEFAULT 0,
  movement_id   VARCHAR(60) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_alloc_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_alloc_order FOREIGN KEY (order_id) REFERENCES orders(order_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_alloc_lot FOREIGN KEY (lot_id) REFERENCES inventory_lots(lot_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_alloc_product FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_alloc_movement FOREIGN KEY (movement_id) REFERENCES inventory_movements(movement_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_alloc_order_item (order_item_id),
  INDEX idx_alloc_order (order_id),
  INDEX idx_alloc_lot (lot_id),
  INDEX idx_alloc_movement (movement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_plans (
  plan_id VARCHAR(60) PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  order_from_date DATE NOT NULL,
  order_to_date DATE NOT NULL,
  status ENUM('draft','ordered','partial_received','received','closed','cancelled') NOT NULL DEFAULT 'draft',
  supplier_scope ENUM('Binh Dinh','Gia Lai','Mixed','Unknown') NOT NULL DEFAULT 'Mixed',
  note TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_purchase_plans_status_created (status, created_at),
  INDEX idx_purchase_plans_range (order_from_date, order_to_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_plan_orders (
  plan_id VARCHAR(60) NOT NULL,
  order_id VARCHAR(40) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (plan_id, order_id),
  INDEX idx_ppo_order (order_id),
  CONSTRAINT fk_ppo_plan FOREIGN KEY (plan_id) REFERENCES purchase_plans(plan_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ppo_order FOREIGN KEY (order_id) REFERENCES orders(order_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plan_items (
  plan_item_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id VARCHAR(60) NOT NULL,
  product_id VARCHAR(40) NOT NULL,
  product_name_snapshot VARCHAR(200) NOT NULL,
  uom_id VARCHAR(60) NOT NULL,
  uom_label_snapshot VARCHAR(120) NOT NULL,
  source_location ENUM('Binh Dinh','Gia Lai','Unknown') NOT NULL DEFAULT 'Unknown',
  qty_needed_uom DECIMAL(12,3) NOT NULL DEFAULT 0,
  qty_planned_uom DECIMAL(12,3) NOT NULL DEFAULT 0,
  qty_received_uom DECIMAL(12,3) NOT NULL DEFAULT 0,
  conversion_to_base_snapshot DECIMAL(12,3) NOT NULL DEFAULT 1,
  qty_needed_base DECIMAL(12,3) NOT NULL DEFAULT 0,
  qty_planned_base DECIMAL(12,3) NOT NULL DEFAULT 0,
  qty_received_base DECIMAL(12,3) NOT NULL DEFAULT 0,
  cost_per_uom_vnd INT NOT NULL DEFAULT 0,
  note TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_plan_items_plan FOREIGN KEY (plan_id) REFERENCES purchase_plans(plan_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_plan_items_product FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_plan_items_uom FOREIGN KEY (uom_id) REFERENCES product_uoms(uom_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  UNIQUE KEY uq_plan_item_product_uom_source (plan_id, product_id, uom_id, source_location),
  INDEX idx_plan_items_product (product_id),
  INDEX idx_plan_items_source (source_location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_plan_receipts (
  receipt_id VARCHAR(80) PRIMARY KEY,
  plan_id VARCHAR(60) NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_by BIGINT UNSIGNED NULL,
  note TEXT NULL,
  CONSTRAINT fk_receipts_plan FOREIGN KEY (plan_id) REFERENCES purchase_plans(plan_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_receipts_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_plan_receipt_items (
  receipt_item_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_id VARCHAR(80) NOT NULL,
  plan_id VARCHAR(60) NOT NULL,
  plan_item_id BIGINT UNSIGNED NULL,
  lot_id VARCHAR(60) NOT NULL,
  product_id VARCHAR(40) NOT NULL,
  uom_id VARCHAR(60) NOT NULL,
  qty_received_uom DECIMAL(12,3) NOT NULL,
  conversion_to_base_snapshot DECIMAL(12,3) NOT NULL,
  qty_received_base DECIMAL(12,3) NOT NULL,
  cost_per_uom_vnd INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipt_items_receipt FOREIGN KEY (receipt_id) REFERENCES purchase_plan_receipts(receipt_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_receipt_items_plan FOREIGN KEY (plan_item_id) REFERENCES plan_items(plan_item_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_receipt_items_lot FOREIGN KEY (lot_id) REFERENCES inventory_lots(lot_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_receipt_items_plan (plan_id),
  INDEX idx_receipt_items_lot (lot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_users (
  admin_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(80) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL COMMENT 'Tạo bằng PHP password_hash(), không lưu mật khẩu plain text',
  full_name      VARCHAR(160),
  role           ENUM('owner','admin','staff') NOT NULL DEFAULT 'staff',
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- 4. SEED DATA
-- ============================================================


INSERT INTO settings (setting_key, setting_value, note) VALUES
  ('store_name', 'Đặc Sản Nhà Dân', 'Tên cửa hàng'),
  ('store_phone', '0378456926', 'Số điện thoại nhận đơn'),
  ('zalo_link', 'https://zalo.me/0378456926', 'Link Zalo'),
  ('free_ship_threshold', '300000', 'Miễn phí ship khi đơn hàng >= ngưỡng này'),
  ('default_shipping_zone_id', 'ZONE_HCM_INNER', 'Vùng giao hàng mặc định');

INSERT INTO shipping_zones (zone_id, zone_name, fee_vnd, is_default, is_active) VALUES
  ('ZONE_HCM_INNER', 'Nội thành HCM', 25000, 1, 1),
  ('ZONE_HCM_OUTER', 'Ngoại thành HCM', 40000, 0, 1),
  ('ZONE_NEARBY', 'Tỉnh lân cận', 60000, 0, 1),
  ('ZONE_REMOTE', 'Tỉnh xa', 80000, 0, 1);

INSERT INTO categories (category_id, category_name, category_slug, sort_order) VALUES
  ('Cate_001', 'Đặc sản khô', 'dac-san-kho', 1),
  ('Cate_002', 'Thịt một nắng', 'thit-mot-nang', 2),
  ('Cate_003', 'Đặc sản Bình Định', 'dac-san-binh-dinh', 3),
  ('Cate_004', 'Yến sào', 'yen-sao', 4),
  ('Cate_711', '7-Eleven', '7-eleven', 99);

INSERT INTO products (product_id, product_name, product_slug, category_id, category_label, default_source, short_description, full_description, ingredients, base_uom_label, shelf_life_value, shelf_life_unit, is_active, created_at, updated_at) VALUES
  ('pro_001', 'Chả lụa cây', 'cha-lua-cay', 'Cate_003', 'Đặc sản Bình Định', 'Binh Dinh', 'Chả lụa cây truyền thống Bình Định', 'Chả lụa cây truyền thống Bình Định, làm từ thịt heo xay nhuyễn, gói chặt và hấp chín, giữ độ dai mềm tự nhiên. Giá bán theo kg: 170.000 VNĐ/kg, tiết kiệm khi mua theo kg.', 'Thịt heo, muối, nước mắm, tiêu, lá chuối', 'Cây 500g', 14, 'days', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_002', 'Nem chua cây', 'nem-chua-cay', 'Cate_003', 'Đặc sản Bình Định', 'Binh Dinh', 'Nem chua cây đặc sản Bình Định', 'Chả nem cây đặc sản Bình Định, vị đậm đà, thơm mùi thịt và gia vị, có thể chiên hoặc nướng tùy khẩu vị. Giá bán theo kg: 170.000 VNĐ/kg, mua theo kg giá ưu đãi hơn.', 'Thịt heo, bì heo, tỏi, ớt, lá chuối, gia vị', 'Cây 500g', 7, 'days', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_003', 'Chả lụa gói', 'cha-lua-goi', 'Cate_003', 'Đặc sản Bình Định', 'Binh Dinh', 'Chả lụa gói nhỏ tiện lợi Bình Định', 'Chả lụa quấn lá chuối truyền thống, gói nhỏ tiện lợi, giữ trọn hương vị thơm ngon. 10 Cái – giá bán 75.000 VNĐ/gói.', 'Thịt heo, muối, nước mắm, tiêu, lá chuối', 'Cái', 14, 'days', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_004', 'Nem chua gói', 'nem-chua-goi', 'Cate_003', 'Đặc sản Bình Định', 'Binh Dinh', 'Nem chua gói tiện lợi Bình Định', 'Chả nem gói tiện lợi, hương vị đậm đà, phù hợp cho bữa ăn gia đình hoặc tiệc nhỏ. Có thể chiên hoặc nướng tùy nhu cầu sử dụng.', 'Thịt heo, bì heo, tỏi, ớt, lá chuối, gia vị', 'Cái', 7, 'days', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_005', 'Chả ram', 'cha-ram', 'Cate_003', 'Đặc sản Bình Định', 'Binh Dinh', 'Chả ram tôm đất đặc sản Bình Định', 'Chả ram nướng đặc sản Bình Định, nhân tôm nguyên con, lớp ngoài vàng thơm khi nướng. Giá bán theo kg: 170.000 VNĐ/kg, mua tròn kg tiết kiệm hơn.', 'Tôm đất, thịt heo, bún tàu, gia vị', 'Gói 500g', 14, 'days', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_013', 'Cá chỉ vàng', 'ca-chi-vang', 'Cate_001', 'Đặc sản khô', 'Binh Dinh', 'Cá chỉ vàng khô Bình Định', 'Cá chỉ vàng khô đặc sản Bình Định, được làm sạch và phơi khô tự nhiên theo truyền thống.', 'Cá chỉ vàng, muối', 'Hộp 500g', 4, 'months', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_006', 'Bò một nắng', 'bo-mot-nang', 'Cate_002', 'Thịt một nắng', 'Gia Lai', 'Bò một nắng đặc sản Gia Lai', 'Bò một nắng đặc sản Gia Lai, thịt bò tươi được tẩm ướp vừa vị và phơi nắng tự nhiên, giữ độ ngọt và dai mềm đặc trưng. Giá bán theo kg: 650.000 VNĐ/kg, ưu đãi hơn so với mua lẻ 0.5kg.', 'Thịt bò tươi, muối, sả, ớt, gia vị', 'Gói 500g', 4, 'months', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_007', 'Heo một nắng', 'heo-mot-nang', 'Cate_002', 'Thịt một nắng', 'Gia Lai', 'Heo một nắng đặc sản Gia Lai', 'Heo một nắng làm từ thịt heo tươi, cắt lát dày, tẩm ướp đậm đà và phơi nắng nhẹ. Khi chế biến cho mùi thơm hấp dẫn, thịt mềm béo vừa phải. Giá bán theo kg: 380.000 VNĐ/kg, tiết kiệm hơn so với mua 0.5kg.', 'Thịt heo tươi, muối, sả, ớt, gia vị', 'Gói 500g', 4, 'months', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_008', 'Gàu bò một nắng', 'gau-bo-mot-nang', 'Cate_002', 'Thịt một nắng', 'Gia Lai', 'Gàu bò một nắng giòn dai Gia Lai', 'Gàu bò một nắng là phần gân bò giòn dai tự nhiên, được tẩm ướp và phơi nắng vừa đủ, cho cảm giác dai giòn hấp dẫn khi nướng. Giá bán theo kg: 580.000 VNĐ/kg, mua tròn kg giá tốt hơn.', 'Gàu/gân bò, muối, sả, ớt, gia vị', 'Gói 500g', 4, 'months', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_009', 'Khô bò sợi', 'kho-bo-soi', 'Cate_001', 'Đặc sản khô', 'Gia Lai', 'Khô bò sợi tẩm gia vị Gia Lai', 'Khô bò sợi được xé từ thịt bò nguyên chất, tẩm gia vị truyền thống, cay nhẹ và thơm mùi sả ớt. Phù hợp dùng ăn liền hoặc làm món nhâm nhi.', 'Thịt bò, muối, sả, ớt, gia vị', 'Gói 500g', 5, 'months', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_010', 'Khô bò miếng', 'kho-bo-mieng', 'Cate_001', 'Đặc sản khô', 'Gia Lai', 'Khô bò miếng nguyên chất Gia Lai', 'Khô bò nguyên miếng được làm từ thịt bò tươi cắt miếng lớn, tẩm ướp vừa vị, giữ được độ dai và vị ngọt tự nhiên của thịt.', 'Thịt bò, muối, sả, ớt, gia vị', 'Hộp 500g', 5, 'months', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_011', 'Yến tinh chế (không kèm hộp)', 'yen-tinh-che-khong-kem-hop', 'Cate_004', 'Yến sào', 'Gia Lai', 'Yến tinh chế sạch, không kèm hộp', 'Yến tinh chế đã được làm sạch hoàn toàn, giữ nguyên sợi yến tự nhiên, thuận tiện cho việc chưng yến tại nhà hoặc sử dụng hằng ngày.', 'Yến sào nguyên chất', 'Hộp', 12, 'months', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('pro_012', 'Yến tinh chế (kèm hộp)', 'yen-tinh-che-kem-hop', 'Cate_004', 'Yến sào', 'Gia Lai', 'Yến tinh chế cao cấp, kèm hộp quà', 'Yến tinh chế cao cấp, đã làm sạch, đi kèm hộp đựng sang trọng, phù hợp làm quà biếu hoặc sử dụng lâu dài.', 'Yến sào nguyên chất', 'Hộp', 12, 'months', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('Pro_711_0', 'Pin cài Nét Việt', 'pin-cai-net-viet', 'Cate_711', '7-Eleven', 'Unknown', 'Bộ pin cài hình ảnh đặc trưng Việt Nam', 'Lấy cảm hứng từ những hình ảnh quen thuộc nhất của Việt Nam, bộ pin cài này tái hiện trọn vẹn vibe vừa truyền thống vừa hiện đại qua nét minh hoạ đáng yêu.', '', 'Cái', 0, '', 0, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
  ('Pro_711_1', 'Pin cài Chào Buổi Sáng', 'pin-cai-chao-buoi-sang', 'Cate_711', '7-Eleven', 'Unknown', 'Bộ pin cài hình ảnh đặc trưng Việt Nam', 'Lấy cảm hứng từ những hình ảnh quen thuộc nhất của Việt Nam, bộ pin cài này tái hiện trọn vẹn vibe vừa truyền thống vừa hiện đại qua nét minh hoạ đáng yêu.', '', 'Cái', 0, '', 0, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

INSERT INTO product_uoms (product_id, uom_id, uom_label, conversion_to_base, unit_price_vnd, cost_price_vnd, is_base_unit, is_default, is_sellable, is_purchasable, note) VALUES
  ('pro_001', 'pro_001_500G', 'Cây 500g', 1, 90000, 65000, 1, 1, 1, 1, ''),
  ('pro_001', 'pro_001_1KG', 'Cây 1kg', 2, 170000, 130000, 0, 1, 1, 1, 'Tiết kiệm khi mua 1kg'),
  ('pro_002', 'pro_002_500G', 'Cây 500g', 1, 90000, 65000, 1, 1, 1, 1, ''),
  ('pro_002', 'pro_002_1KG', 'Cây 1kg', 2, 170000, 130000, 0, 1, 1, 1, 'Tiết kiệm khi mua 1kg'),
  ('pro_003', 'pro_003_CAI', 'Cái (1 cái)', 1, 8000, 5000, 1, 1, 1, 1, ''),
  ('pro_003', 'pro_003_10CAI', 'Gói (10 cái)', 10, 75000, 50000, 0, 1, 1, 1, 'Mua gói 10 tiết kiệm hơn'),
  ('pro_004', 'pro_004_CAI', 'Cái (1 cái)', 1, 5000, 2500, 1, 0, 1, 1, 'Base unit để quy đổi tồn kho; không hiển thị bán lẻ'),
  ('pro_004', 'pro_004_GOI', 'Gói (10 cái)', 10, 50000, 25000, 0, 1, 1, 1, ''),
  ('pro_005', 'pro_005_500G', 'Gói 500g', 1, 90000, 50000, 1, 1, 1, 1, ''),
  ('pro_005', 'pro_005_1KG', 'Gói 1kg', 2, 170000, 100000, 0, 1, 1, 1, 'Tiết kiệm khi mua 1kg'),
  ('pro_006', 'pro_006_500G', 'Gói 500g', 1, 330000, 230000, 1, 1, 1, 1, ''),
  ('pro_006', 'pro_006_1KG', 'Gói 1kg', 2, 650000, 460000, 0, 1, 1, 1, 'Ưu đãi hơn mua lẻ 0.5kg'),
  ('pro_007', 'pro_007_500G', 'Gói 500g', 1, 200000, 125000, 1, 1, 1, 1, ''),
  ('pro_007', 'pro_007_1KG', 'Gói 1kg', 2, 380000, 250000, 0, 1, 1, 1, 'Tiết kiệm hơn so với mua 0.5kg'),
  ('pro_008', 'pro_008_500G', 'Gói 500g', 1, 300000, 200000, 1, 1, 1, 1, ''),
  ('pro_008', 'pro_008_1KG', 'Gói 1kg', 2, 580000, 400000, 0, 1, 1, 1, 'Mua tròn kg giá tốt hơn'),
  ('pro_009', 'pro_009_500G', 'Gói 500g', 1, 450000, 350000, 1, 1, 1, 1, ''),
  ('pro_010', 'pro_010_500G', 'Hộp 500g', 1, 500000, 400000, 1, 1, 1, 1, ''),
  ('pro_011', 'pro_011_HOP', 'Hộp', 1, 2800000, 2500000, 1, 1, 1, 1, ''),
  ('pro_012', 'pro_012_HOP', 'Hộp', 1, 3000000, 2700000, 1, 1, 1, 1, ''),
  ('pro_013', 'pro_013_500G', 'Hộp 500g', 1, 130000, 85000, 1, 1, 1, 1, ''),
  ('pro_013', 'pro_013_1KG', 'Hộp 1kg', 2, 250000, 170000, 0, 1, 1, 1, ''),
  ('Pro_711_0', 'Pro_711_0_CAI', 'Cái', 1, 42000, 42000, 1, 1, 1, 0, ''),
  ('Pro_711_1', 'Pro_711_1_CAI', 'Cái', 1, 42000, 42000, 1, 1, 1, 0, '');

-- Moi product chi duoc co 1 UOM default de v_product_cards khong duplicate.
-- Uu tien base UOM lam default; admin co the doi default sau trong UI.
UPDATE product_uoms SET is_default = 0;
UPDATE product_uoms SET is_default = 1 WHERE is_base_unit = 1;

INSERT INTO product_images (product_id, image_path, source_url, image_alt, is_base, sort_order) VALUES
  ('pro_001', 'products_image/pro_001/cha-lua-cay_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/Product_Images/chalua.jpg', 'Chả lụa cây', 1, 1),
  ('pro_001', 'products_image/pro_001/cha-lua-cay_detail_01.jpg', '', 'Chả lụa cây - ảnh chi tiết', 0, 2),
  ('pro_002', 'products_image/pro_002/nem-chua-cay_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/Product_Images/nemchua.jpg', 'Nem chua cây', 1, 1),
  ('pro_002', 'products_image/pro_002/nem-chua-cay_detail_01.jpg', '', 'Nem chua cây - ảnh chi tiết', 0, 2),
  ('pro_003', 'products_image/pro_003/cha-lua-goi_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/Product_Images/chalua_goi.png', 'Chả lụa gói', 1, 1),
  ('pro_003', 'products_image/pro_003/cha-lua-goi_detail_01.jpg', '', 'Chả lụa gói - ảnh chi tiết', 0, 2),
  ('pro_004', 'products_image/pro_004/nem-chua-goi_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/Product_Images/nemchua_goi.jpg', 'Nem chua gói', 1, 1),
  ('pro_004', 'products_image/pro_004/nem-chua-goi_detail_01.jpg', '', 'Nem chua gói - ảnh chi tiết', 0, 2),
  ('pro_005', 'products_image/pro_005/cha-ram_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/Product_Images/charamtomdat.jpg', 'Chả ram', 1, 1),
  ('pro_005', 'products_image/pro_005/cha-ram_detail_01.jpg', '', 'Chả ram - ảnh chi tiết', 0, 2),
  ('pro_013', 'products_image/pro_013/ca-chi-vang_main.jpg', '', 'Cá chỉ vàng', 1, 1),
  ('pro_013', 'products_image/pro_013/ca-chi-vang_detail_01.jpg', '', 'Cá chỉ vàng - ảnh chi tiết', 0, 2),
  ('pro_006', 'products_image/pro_006/bo-mot-nang_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/main/BoMotNang.png', 'Bò một nắng', 1, 1),
  ('pro_006', 'products_image/pro_006/bo-mot-nang_detail_01.jpg', '', 'Bò một nắng - ảnh chi tiết', 0, 2),
  ('pro_007', 'products_image/pro_007/heo-mot-nang_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/c13ba4ab0010f674207093e02dc97f8b5b2e480c/heo_mot_nang.jpg', 'Heo một nắng', 1, 1),
  ('pro_007', 'products_image/pro_007/heo-mot-nang_detail_01.jpg', '', 'Heo một nắng - ảnh chi tiết', 0, 2),
  ('pro_008', 'products_image/pro_008/gau-bo-mot-nang_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/c13ba4ab0010f674207093e02dc97f8b5b2e480c/gau_bo_mot_nang.png', 'Gàu bò một nắng', 1, 1),
  ('pro_008', 'products_image/pro_008/gau-bo-mot-nang_detail_01.jpg', '', 'Gàu bò một nắng - ảnh chi tiết', 0, 2),
  ('pro_009', 'products_image/pro_009/kho-bo-soi_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/Product_Images/KhoBoSoi.png', 'Khô bò sợi', 1, 1),
  ('pro_009', 'products_image/pro_009/kho-bo-soi_detail_01.jpg', '', 'Khô bò sợi - ảnh chi tiết', 0, 2),
  ('pro_010', 'products_image/pro_010/kho-bo-mieng_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/Product_Images/KhoBoMieng.png', 'Khô bò miếng', 1, 1),
  ('pro_010', 'products_image/pro_010/kho-bo-mieng_detail_01.jpg', '', 'Khô bò miếng - ảnh chi tiết', 0, 2),
  ('pro_011', 'products_image/pro_011/yen-tinh-che-khong-kem-hop_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/Product_Images/yen.jpg', 'Yến tinh chế (không kèm hộp)', 1, 1),
  ('pro_011', 'products_image/pro_011/yen-tinh-che-khong-kem-hop_detail_01.jpg', '', 'Yến tinh chế (không kèm hộp) - ảnh chi tiết', 0, 2),
  ('pro_012', 'products_image/pro_012/yen-tinh-che-kem-hop_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/Product_Images/yenvshop.png', 'Yến tinh chế (kèm hộp)', 1, 1),
  ('pro_012', 'products_image/pro_012/yen-tinh-che-kem-hop_detail_01.jpg', '', 'Yến tinh chế (kèm hộp) - ảnh chi tiết', 0, 2),
  ('Pro_711_0', 'products_image/Pro_711_0/pin-cai-net-viet_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/Product_Images/gen-n-bin2.jpg', 'Pin cài Nét Việt', 1, 1),
  ('Pro_711_0', 'products_image/Pro_711_0/pin-cai-net-viet_detail_01.jpg', '', 'Pin cài Nét Việt - ảnh chi tiết', 0, 2),
  ('Pro_711_1', 'products_image/Pro_711_1/pin-cai-chao-buoi-sang_main.jpg', 'https://raw.githubusercontent.com/AvtBom21/Certificate/a8e82260a5781ffc31c83de9bd047429472d386d/gen-n-bin1.jpg', 'Pin cài Chào Buổi Sáng', 1, 1),
  ('Pro_711_1', 'products_image/Pro_711_1/pin-cai-chao-buoi-sang_detail_01.jpg', '', 'Pin cài Chào Buổi Sáng - ảnh chi tiết', 0, 2);

INSERT INTO inventory_lots (lot_id, product_id, source_location, qty_base_on_hand, qty_base_reserved, received_date, expiry_date, supplier_name, cost_per_base_unit_vnd, note) VALUES
  ('LOT-BD-001', 'pro_001', 'Binh Dinh', 40, 0, '2026-05-01', '2026-05-15', 'NCC Bình Định A', 65000, 'Tồn đầu kỳ - base Cây 500g'),
  ('LOT-BD-002', 'pro_002', 'Binh Dinh', 30, 0, '2026-05-01', '2026-05-08', 'NCC Bình Định A', 65000, 'Tồn đầu kỳ - base Cây 500g'),
  ('LOT-BD-003', 'pro_003', 'Binh Dinh', 100, 0, '2026-05-01', '2026-05-15', 'NCC Bình Định A', 5000, 'Tồn đầu kỳ - base Cái'),
  ('LOT-BD-004', 'pro_004', 'Binh Dinh', 120, 0, '2026-05-01', '2026-05-08', 'NCC Bình Định A', 2500, 'Tồn đầu kỳ - base Cái'),
  ('LOT-BD-005', 'pro_005', 'Binh Dinh', 35, 0, '2026-05-01', '2026-05-15', 'NCC Bình Định A', 50000, 'Tồn đầu kỳ - base Gói 500g'),
  ('LOT-GL-001', 'pro_006', 'Gia Lai', 25, 0, '2026-05-01', '2026-09-01', 'NCC Gia Lai B', 230000, 'Tồn đầu kỳ - base Gói 500g'),
  ('LOT-GL-002', 'pro_007', 'Gia Lai', 25, 0, '2026-05-01', '2026-09-01', 'NCC Gia Lai B', 125000, 'Tồn đầu kỳ - base Gói 500g'),
  ('LOT-GL-003', 'pro_008', 'Gia Lai', 18, 0, '2026-05-01', '2026-09-01', 'NCC Gia Lai B', 200000, 'Tồn đầu kỳ - base Gói 500g'),
  ('LOT-GL-004', 'pro_009', 'Gia Lai', 20, 0, '2026-05-01', '2026-10-01', 'NCC Gia Lai B', 350000, 'Tồn đầu kỳ'),
  ('LOT-GL-005', 'pro_010', 'Gia Lai', 15, 0, '2026-05-01', '2026-10-01', 'NCC Gia Lai B', 400000, 'Tồn đầu kỳ'),
  ('LOT-GL-006', 'pro_011', 'Gia Lai', 8, 0, '2026-05-01', '2027-05-01', 'NCC Gia Lai C', 2500000, 'Tồn đầu kỳ'),
  ('LOT-GL-007', 'pro_012', 'Gia Lai', 8, 0, '2026-05-01', '2027-05-01', 'NCC Gia Lai C', 2700000, 'Tồn đầu kỳ'),
  ('LOT-BD-006', 'pro_013', 'Binh Dinh', 30, 0, '2026-05-01', '2026-09-01', 'NCC Bình Định B', 85000, 'Tồn đầu kỳ');

INSERT INTO inventory_movements (movement_id, created_at, movement_type, ref_type, ref_id, lot_id, product_id, source_location, uom_id, qty_uom, conversion_to_base_snapshot, qty_base, cost_per_base_unit_vnd, note) VALUES
  ('MOV-SEED-0001', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-BD-001', 'pro_001', 'Binh Dinh', NULL, 40, 1, 40, 65000, 'Tồn đầu kỳ - base Cây 500g'),
  ('MOV-SEED-0002', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-BD-002', 'pro_002', 'Binh Dinh', NULL, 30, 1, 30, 65000, 'Tồn đầu kỳ - base Cây 500g'),
  ('MOV-SEED-0003', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-BD-003', 'pro_003', 'Binh Dinh', NULL, 100, 1, 100, 5000, 'Tồn đầu kỳ - base Cái'),
  ('MOV-SEED-0004', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-BD-004', 'pro_004', 'Binh Dinh', NULL, 120, 1, 120, 2500, 'Tồn đầu kỳ - base Cái'),
  ('MOV-SEED-0005', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-BD-005', 'pro_005', 'Binh Dinh', NULL, 35, 1, 35, 50000, 'Tồn đầu kỳ - base Gói 500g'),
  ('MOV-SEED-0006', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-GL-001', 'pro_006', 'Gia Lai', NULL, 25, 1, 25, 230000, 'Tồn đầu kỳ - base Gói 500g'),
  ('MOV-SEED-0007', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-GL-002', 'pro_007', 'Gia Lai', NULL, 25, 1, 25, 125000, 'Tồn đầu kỳ - base Gói 500g'),
  ('MOV-SEED-0008', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-GL-003', 'pro_008', 'Gia Lai', NULL, 18, 1, 18, 200000, 'Tồn đầu kỳ - base Gói 500g'),
  ('MOV-SEED-0009', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-GL-004', 'pro_009', 'Gia Lai', NULL, 20, 1, 20, 350000, 'Tồn đầu kỳ'),
  ('MOV-SEED-0010', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-GL-005', 'pro_010', 'Gia Lai', NULL, 15, 1, 15, 400000, 'Tồn đầu kỳ'),
  ('MOV-SEED-0011', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-GL-006', 'pro_011', 'Gia Lai', NULL, 8, 1, 8, 2500000, 'Tồn đầu kỳ'),
  ('MOV-SEED-0012', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-GL-007', 'pro_012', 'Gia Lai', NULL, 8, 1, 8, 2700000, 'Tồn đầu kỳ'),
  ('MOV-SEED-0013', '2026-05-01 00:00:00', 'IN', 'OPENING', 'OPENING-STOCK', 'LOT-BD-006', 'pro_013', 'Binh Dinh', NULL, 30, 1, 30, 85000, 'Tồn đầu kỳ');


-- User admin mẫu cho local: username admin / password admin123.
-- Khi vận hành thật, hãy đổi bằng hash mới từ PHP:
-- C:\xampp\php\php.exe -r "echo password_hash('MatKhauManhCuaBan', PASSWORD_DEFAULT), PHP_EOL;"
INSERT INTO admin_users (username, password_hash, full_name, role, is_active) VALUES
  ('admin', '$2y$10$exGmuocWm77QMVusjJsUc.8Jakac6Z6yyQMicznBhRwDL/IXOSYya', 'Shop Admin', 'owner', 1);

-- ============================================================
-- 5. VIEWS PHỤC VỤ PHP QUERY NHANH
-- ============================================================

CREATE VIEW v_product_cards AS
SELECT
  p.product_id,
  p.product_name,
  p.product_slug,
  p.category_id,
  p.category_label,
  p.default_source,
  p.short_description,
  p.full_description,
  p.ingredients,
  p.base_uom_label,
  p.shelf_life_value,
  p.shelf_life_unit,
  img.image_path AS base_image_path,
  u.uom_id AS default_uom_id,
  u.uom_label AS default_uom_label,
  u.unit_price_vnd AS default_price_vnd,
  u.conversion_to_base AS default_conversion_to_base
FROM products p
LEFT JOIN product_images img
  ON img.product_id = p.product_id AND img.is_base = 1 AND img.is_active = 1
LEFT JOIN product_uoms u
  ON u.product_id = p.product_id AND u.is_default = 1 AND u.is_active = 1
WHERE p.is_active = 1;

CREATE VIEW v_inventory_summary AS
SELECT
  p.product_id,
  p.product_name,
  p.base_uom_label,
  p.default_source,
  COALESCE(SUM(l.qty_base_on_hand), 0) AS qty_base_on_hand,
  COALESCE(SUM(l.qty_base_reserved), 0) AS qty_base_reserved,
  COALESCE(SUM(l.qty_base_on_hand - l.qty_base_reserved), 0) AS qty_base_available,
  MIN(CASE WHEN l.qty_base_on_hand - l.qty_base_reserved > 0 THEN l.expiry_date END) AS nearest_expiry_date
FROM products p
LEFT JOIN inventory_lots l ON l.product_id = p.product_id
GROUP BY p.product_id, p.product_name, p.base_uom_label, p.default_source;

-- ============================================================
-- 6. QUERY MẪU CHO PHP
-- ============================================================

-- Lấy card sản phẩm:
-- SELECT * FROM v_product_cards ORDER BY category_id, product_name;

-- Lấy UOM hiển thị bán cho 1 sản phẩm:
-- SELECT * FROM product_uoms
-- WHERE product_id = 'pro_006' AND is_active = 1 AND is_sellable = 1
-- ORDER BY is_default DESC, sort_order, conversion_to_base;

-- Lấy gallery ảnh detail:
-- SELECT image_path, image_alt, is_base
-- FROM product_images
-- WHERE product_id = 'pro_006'
-- ORDER BY sort_order;

-- Công thức tính qty_base khi bán/nhập:
-- qty_base = qty_uom * conversion_to_base
-- Ví dụ bán 1kg bò một nắng:
-- SELECT 1 * conversion_to_base AS qty_base_to_deduct
-- FROM product_uoms WHERE uom_id = 'pro_006_1KG';
-- Kết quả = 2, tức trừ 2 Gói 500g khỏi inventory_lots.
