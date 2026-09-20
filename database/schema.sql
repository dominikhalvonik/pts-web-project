-- TestLab Shop v1.0.0 / MariaDB / InnoDB / UTF-8
-- Import into an EMPTY database. Deliberately no DROP TABLE statements.
SET NAMES utf8mb4;
CREATE TABLE workspaces (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 name VARCHAR(100) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 workspace_id BIGINT UNSIGNED NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 name VARCHAR(100) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_users_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE products (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 workspace_id BIGINT UNSIGNED NOT NULL,
 sku VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 name VARCHAR(100) NOT NULL,
 description VARCHAR(500) NOT NULL,
 category VARCHAR(40) NOT NULL,
 price_cents INT UNSIGNED NOT NULL,
 stock INT UNSIGNED NOT NULL,
 active TINYINT UNSIGNED NOT NULL DEFAULT 1,
 UNIQUE KEY ux_products_workspace_sku (workspace_id,sku),
 CONSTRAINT fk_products_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
 CONSTRAINT ck_product_price CHECK (price_cents > 0 AND price_cents <= 1000000),
 CONSTRAINT ck_product_active CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE orders (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 order_number VARCHAR(35) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 workspace_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'created',
 currency CHAR(3) NOT NULL DEFAULT 'EUR',
 subtotal_cents INT UNSIGNED NOT NULL,
 discount_cents INT UNSIGNED NOT NULL,
 shipping_cents INT UNSIGNED NOT NULL,
 total_cents INT UNSIGNED NOT NULL,
 coupon_code VARCHAR(32) NOT NULL DEFAULT '',
 delivery_method VARCHAR(16) NOT NULL,
 customer_name VARCHAR(100) NOT NULL,
 customer_email VARCHAR(190) NOT NULL,
 address_line VARCHAR(190) NOT NULL DEFAULT '',
 city VARCHAR(100) NOT NULL DEFAULT '',
 postal_code VARCHAR(10) NOT NULL DEFAULT '',
 note VARCHAR(500) NOT NULL DEFAULT '',
 idempotency_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 cancelled_at DATETIME NULL,
 UNIQUE KEY ux_order_idempotency (user_id,idempotency_key),
 KEY ix_order_user_created (user_id,created_at),
 CONSTRAINT fk_order_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
 CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id),
 CONSTRAINT ck_order_status CHECK (status IN ('created','cancelled')),
 CONSTRAINT ck_delivery CHECK (delivery_method IN ('courier','pickup')),
 CONSTRAINT ck_order_total CHECK (discount_cents<=subtotal_cents AND total_cents=subtotal_cents-discount_cents+shipping_cents)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE order_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 sku VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 product_name VARCHAR(100) NOT NULL,
 unit_price_cents INT UNSIGNED NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 line_total_cents INT UNSIGNED NOT NULL,
 UNIQUE KEY ux_order_product (order_id,product_id),
 CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_item_product FOREIGN KEY (product_id) REFERENCES products(id),
 CONSTRAINT ck_item_quantity CHECK (quantity BETWEEN 1 AND 20),
 CONSTRAINT ck_item_total CHECK (line_total_cents=unit_price_cents*quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE api_tokens (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY ix_token_expiry (expires_at),
 CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE login_limits (
 bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 window_started DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
