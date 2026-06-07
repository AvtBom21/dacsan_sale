USE dac_san_nha_dan;

CREATE TABLE IF NOT EXISTS purchase_plans (
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

CREATE TABLE IF NOT EXISTS plan_items (
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

CREATE TABLE IF NOT EXISTS purchase_plan_receipts (
  receipt_id VARCHAR(80) PRIMARY KEY,
  plan_id VARCHAR(60) NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_by BIGINT UNSIGNED NULL,
  note TEXT NULL,
  CONSTRAINT fk_receipts_plan FOREIGN KEY (plan_id) REFERENCES purchase_plans(plan_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_receipts_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_plan_receipt_items (
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

CREATE TABLE IF NOT EXISTS purchase_plan_orders (
  plan_id VARCHAR(60) NOT NULL,
  order_id VARCHAR(40) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (plan_id, order_id),
  INDEX idx_ppo_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO purchase_plan_orders (plan_id, order_id)
SELECT DISTINCT planned_plan_id, order_id
FROM order_items
WHERE planned_plan_id IS NOT NULL;
