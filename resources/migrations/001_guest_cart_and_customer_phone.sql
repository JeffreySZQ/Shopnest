-- ShopNest guest-cart merge and checkout contact details.
-- Back up the database before running this migration.
-- Safe to run when cart_merge_tokens already exists and/or customer_phone
-- has already been added to orders.

CREATE TABLE IF NOT EXISTS cart_merge_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    merge_token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_cart_merge_token (user_id, merge_token),
    CONSTRAINT fk_cart_merge_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @shopnest_has_customer_phone = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'customer_phone'
);

SET @shopnest_phone_sql = IF(
    @shopnest_has_customer_phone = 0,
    'ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(40) NULL AFTER customer_email',
    'SELECT "orders.customer_phone already exists" AS migration_status'
);

PREPARE shopnest_phone_stmt FROM @shopnest_phone_sql;
EXECUTE shopnest_phone_stmt;
DEALLOCATE PREPARE shopnest_phone_stmt;
