-- Midori Kissa: database structure (MySQL 8.0)
-- Docker runs this script automatically the first time the database is created.

SET NAMES utf8mb4;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    address VARCHAR(200) NULL,
    postal_code CHAR(5) NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(8,2) NOT NULL,
    weight_grams SMALLINT UNSIGNED NULL,
    grade ENUM('ceremonial', 'culinary') NULL,
    origin_region VARCHAR(100) NULL,
    cultivar VARCHAR(100) NULL,
    preparation TEXT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_products_price CHECK (price >= 0),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NULL,
    context ENUM('web', 'product') NOT NULL,
    path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(150) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number CHAR(14) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending_payment', 'paid', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending_payment',
    subtotal DECIMAL(8,2) NOT NULL,
    shipping_cost DECIMAL(6,2) NOT NULL DEFAULT 0,
    total DECIMAL(8,2) NOT NULL,
    shipping_name VARCHAR(150) NOT NULL,
    shipping_address VARCHAR(200) NOT NULL,
    shipping_postal_code CHAR(5) NOT NULL,
    shipping_city VARCHAR(100) NOT NULL,
    shipping_province VARCHAR(100) NOT NULL,
    shipping_phone VARCHAR(20) NOT NULL,
    stripe_session_id VARCHAR(255) NULL UNIQUE,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    unit_price DECIMAL(8,2) NOT NULL,
    PRIMARY KEY (order_id, product_id),
    CONSTRAINT chk_order_items_quantity CHECK (quantity > 0),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed categories of the shop (RF11)
INSERT INTO categories (name, description) VALUES
    ('Matcha ceremonial', 'Matcha de máxima calidad para preparar en cuenco con agua.'),
    ('Matcha culinario', 'Matcha para lattes, repostería y cocina.'),
    ('Otros tés', 'Sencha, hojicha, genmaicha y otros tés japoneses.'),
    ('Utensilios', 'Chasen, chawan, chashaku y accesorios para preparar el té.');
