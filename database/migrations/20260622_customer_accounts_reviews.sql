USE dac_san_nha_dan;

ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER customer_address,
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash,
  ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER is_active;

CREATE TABLE IF NOT EXISTS product_reviews (
  review_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id     BIGINT UNSIGNED NOT NULL,
  order_id        VARCHAR(40) NOT NULL,
  product_id      VARCHAR(40) NOT NULL,
  rating          TINYINT UNSIGNED NOT NULL,
  review_text     VARCHAR(1000) NOT NULL,
  status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  moderated_by    BIGINT UNSIGNED NULL,
  moderated_at    DATETIME NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders(order_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  UNIQUE KEY uq_review_order_product_customer (order_id, product_id, customer_id),
  INDEX idx_reviews_public (status, rating, created_at),
  INDEX idx_reviews_customer (customer_id, created_at),
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
