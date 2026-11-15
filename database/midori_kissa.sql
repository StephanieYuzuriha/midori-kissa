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

-- Test users (passwords in the README; they are not real credentials)
INSERT INTO users (first_name, last_name, email, password_hash, phone, role, address, postal_code, city, province) VALUES
    ('Aiko', 'Martínez Sato', 'admin@midorikissa.test', '$2y$10$qUchhLBzYxO7x7zYasMHZOk6BRCOhBPx39pYFWpM6oRxQWaC3y2li', '600000001', 'admin', NULL, NULL, NULL, NULL),
    ('Lucía', 'Pérez Gómez', 'cliente@midorikissa.test', '$2y$10$JtlZWHRmoQ94t2Ydu2SFr.INTssTUPuqZEPqhSnwP0PZHBFt87rVu', '600000002', 'customer', 'C/ Ardigales 5, 1.º A', '39700', 'Castro Urdiales', 'Cantabria');

-- Test products for the catalog (15 products: 14 active and 1 withdrawn, three of them sold out)
INSERT INTO products (category_id, name, description, price, weight_grams, grade, origin_region,
    cultivar, preparation, stock, is_active, created_at) VALUES
    (1, 'Matcha ceremonial Uji Okumidori', 'Matcha de primera cosecha de Uji, suave y con un dulzor natural. Ideal para preparar en cuenco con agua.', 28.50, 30, 'ceremonial', 'Uji (Kioto)', 'Okumidori', 'Tamiza 2 g de matcha en el cuenco, añade 70 ml de agua a 75-80 °C y bate con el chasen en zigzag durante 20 segundos, hasta que se forme espuma.', 15, 1, '2026-10-20 10:00:00'),
    (1, 'Matcha ceremonial Yame Saemidori', 'Matcha intenso y cremoso de Yame, con notas vegetales y un final largo.', 32.00, 30, 'ceremonial', 'Yame (Fukuoka)', 'Saemidori', 'Tamiza 2 g de matcha en el cuenco, añade 70 ml de agua a 75-80 °C y bate con el chasen durante 20 segundos.', 8, 1, '2026-10-25 10:00:00'),
    (1, 'Matcha ceremonial Nishio Samidori', 'Matcha equilibrado de Nishio, con un color verde brillante y poco amargor.', 21.90, 20, 'ceremonial', 'Nishio (Aichi)', 'Samidori', 'Tamiza 2 g de matcha, añade 70 ml de agua a 75-80 °C y bate durante 20 segundos.', 0, 1, '2026-09-15 10:00:00'),
    (1, 'Matcha ceremonial Uji Samidori, lote de primavera', 'Edición limitada de la cosecha de primavera. Producto retirado de la venta.', 34.00, 30, 'ceremonial', 'Uji (Kioto)', 'Samidori', 'Tamiza 2 g de matcha, añade 70 ml de agua a 75-80 °C y bate durante 20 segundos.', 5, 0, '2026-11-05 10:00:00'),
    (2, 'Matcha culinario para latte', 'Matcha con carácter, pensado para lattes y bebidas con leche o bebida vegetal.', 14.90, 100, 'culinary', 'Kagoshima', 'Yabukita', 'Bate 2 g de matcha con 30 ml de agua a 80 °C y añade 200 ml de leche caliente o fría.', 25, 1, '2026-10-10 10:00:00'),
    (2, 'Matcha culinario para repostería', 'Matcha de sabor intenso que mantiene el color en bizcochos, galletas y helados.', 24.50, 250, 'culinary', 'Shizuoka', 'Yabukita', 'Tamízalo junto con la harina o el azúcar. Para un bizcocho, entre 10 y 15 g por cada 250 g de harina.', 12, 1, '2026-09-28 10:00:00'),
    (3, 'Sencha de Shizuoka', 'Té verde de hoja suelta, fresco y vegetal. El té más habitual en Japón.', 9.90, 100, NULL, 'Shizuoka', 'Yabukita', 'Infusiona 5 g en 200 ml de agua a 70 °C durante 1 minuto.', 30, 1, '2026-09-20 10:00:00'),
    (3, 'Hojicha tostado de Kioto', 'Té verde tostado, con notas de caramelo y muy poca cafeína. Perfecto para la tarde.', 8.50, 80, NULL, 'Kioto', NULL, 'Infusiona 5 g en 250 ml de agua a 90 °C durante 30 segundos.', 0, 1, '2026-10-15 10:00:00'),
    (4, 'Chasen de bambú de 80 varillas', 'Batidor tradicional de bambú para preparar matcha con una espuma fina.', 18.00, NULL, NULL, NULL, NULL, 'Humedécelo en agua templada antes de usarlo y déjalo secar en su soporte.', 10, 1, '2026-09-10 10:00:00'),
    (4, 'Chashaku de bambú', 'Cucharilla de bambú para medir el matcha. Cada cucharada equivale a 1 g, aproximadamente.', 6.50, NULL, NULL, NULL, NULL, NULL, 22, 1, '2026-09-05 10:00:00'),
    (4, 'Chawan de cerámica verde', 'Cuenco de cerámica esmaltada hecho a mano, con capacidad para una taza de matcha.', 35.00, NULL, NULL, NULL, NULL, NULL, 4, 1, '2026-10-30 10:00:00'),
    (4, 'Tamiz para matcha', 'Tamiz de acero inoxidable para deshacer los grumos del matcha antes de batirlo.', 12.00, NULL, NULL, NULL, NULL, 'Pasa el matcha por el tamiz con ayuda del chashaku, directamente sobre el cuenco.', 14, 1, '2026-11-08 10:00:00'),
    (4, 'Soporte para chasen', 'Soporte de cerámica que mantiene la forma del chasen mientras se seca.', 9.00, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-10-01 10:00:00'),
    (4, 'Bote hermético para matcha', 'Bote de acero con doble tapa que protege el matcha de la luz, el aire y la humedad.', 15.50, NULL, NULL, NULL, NULL, 'Guárdalo cerrado en un lugar fresco y oscuro, y consume el matcha en unas semanas una vez abierto.', 9, 1, '2026-11-02 10:00:00'),
    (4, 'Kit de iniciación al matcha', 'Incluye matcha ceremonial de 20 g, chasen, chashaku y chawan. Todo lo necesario para empezar.', 59.00, NULL, NULL, NULL, NULL, 'Sigue la guía incluida: tamiza 2 g de matcha, añade 70 ml de agua a 75-80 °C y bate durante 20 segundos.', 7, 1, '2026-11-10 10:00:00');

-- Provisional photos of the test products (they will be replaced from the admin panel)
INSERT INTO media (product_id, context, path, alt_text, sort_order) VALUES
    (1, 'product', '/images/products/matcha-ceremonial-uji-okumidori.svg', 'Matcha ceremonial Uji Okumidori', 1),
    (1, 'product', '/images/products/matcha-ceremonial-uji-okumidori-2.svg', 'Matcha ceremonial Uji Okumidori (otra vista)', 2),
    (2, 'product', '/images/products/matcha-ceremonial-yame-saemidori.svg', 'Matcha ceremonial Yame Saemidori', 1),
    (3, 'product', '/images/products/matcha-ceremonial-nishio-samidori.svg', 'Matcha ceremonial Nishio Samidori', 1),
    (4, 'product', '/images/products/matcha-ceremonial-uji-samidori-lote-de-primavera.svg', 'Matcha ceremonial Uji Samidori, lote de primavera', 1),
    (5, 'product', '/images/products/matcha-culinario-para-latte.svg', 'Matcha culinario para latte', 1),
    (6, 'product', '/images/products/matcha-culinario-para-reposteria.svg', 'Matcha culinario para repostería', 1),
    (7, 'product', '/images/products/sencha-de-shizuoka.svg', 'Sencha de Shizuoka', 1),
    (8, 'product', '/images/products/hojicha-tostado-de-kioto.svg', 'Hojicha tostado de Kioto', 1),
    (9, 'product', '/images/products/chasen-de-bambu-de-80-varillas.svg', 'Chasen de bambú de 80 varillas', 1),
    (10, 'product', '/images/products/chashaku-de-bambu.svg', 'Chashaku de bambú', 1),
    (11, 'product', '/images/products/chawan-de-ceramica-verde.svg', 'Chawan de cerámica verde', 1),
    (11, 'product', '/images/products/chawan-de-ceramica-verde-2.svg', 'Chawan de cerámica verde (otra vista)', 2),
    (12, 'product', '/images/products/tamiz-para-matcha.svg', 'Tamiz para matcha', 1),
    (13, 'product', '/images/products/soporte-para-chasen.svg', 'Soporte para chasen', 1),
    (14, 'product', '/images/products/bote-hermetico-para-matcha.svg', 'Bote hermético para matcha', 1),
    (15, 'product', '/images/products/kit-de-iniciacion-al-matcha.svg', 'Kit de iniciación al matcha', 1),
    (15, 'product', '/images/products/kit-de-iniciacion-al-matcha-2.svg', 'Kit de iniciación al matcha (otra vista)', 2);
