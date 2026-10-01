-- =====================================================
-- Walk & Wear - Shoe Store Database
-- =====================================================

CREATE DATABASE IF NOT EXISTS walk_and_wear;
USE walk_and_wear;

-- -----------------------------------------------------
-- Table: categories
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- -----------------------------------------------------
-- Table: products
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    image VARCHAR(255) DEFAULT 'images/product-placeholder.svg',
    brand VARCHAR(100),
    size_available VARCHAR(100) DEFAULT '38,39,40,41,42,43',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

-- -----------------------------------------------------
-- Table: users (customers)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin', 'seller') NOT NULL DEFAULT 'customer',
    phone VARCHAR(30),
    address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO users (full_name, email, password, role) VALUES
('Site Admin', 'admin@walkandwear.local', '$2y$10$78apR7kAIUDQbHD9QHWR9u16ZszTwJLV7me0uvGiRB1khnl6ecd2.', 'admin'),
('Store Seller', 'seller@walkandwear.local', '$2y$10$FLqk86Zj55s3nD2qx4./ke47C9s02DkTaqYzrjOIw/WT5colgzgQG', 'seller');

-- -----------------------------------------------------
-- Table: orders
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    customer_name VARCHAR(150),
    customer_email VARCHAR(150),
    customer_address VARCHAR(255),
    delivery_latitude DECIMAL(10,7) DEFAULT NULL,
    delivery_longitude DECIMAL(10,7) DEFAULT NULL,
    customer_phone VARCHAR(30),
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','preparing','ready_for_delivery','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
    carrier_name VARCHAR(100) DEFAULT NULL,
    tracking_number VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS store_map_location (
    store_id TINYINT UNSIGNED PRIMARY KEY,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy_m DECIMAL(8,2) DEFAULT NULL,
    updated_by INT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE CASCADE
);

INSERT INTO store_map_location (store_id, latitude, longitude, accuracy_m, updated_by)
SELECT 1, 17.0236651, 121.6307742, NULL, user_id
FROM users
WHERE role = 'admin'
ORDER BY user_id
LIMIT 1;

-- -----------------------------------------------------
-- Table: order_items
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    size VARCHAR(10),
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Sample Categories
-- -----------------------------------------------------
INSERT INTO categories (name) VALUES
('Running'),
('Casual'),
('Basketball'),
('Sandals'),
('Boots');

-- -----------------------------------------------------
-- Sample Products
-- -----------------------------------------------------
INSERT INTO products (category_id, name, description, price, stock, image, brand, size_available) VALUES
(1, 'AirFlex Runner', 'Lightweight running shoes with breathable mesh upper and cushioned sole.', 1899.00, 25, 'images/shoe1.jpg', 'Walk & Wear', '38,39,40,41,42,43'),
(2, 'Urban Classic Sneaker', 'Everyday casual sneaker, perfect for street style and comfort.', 1599.00, 30, 'images/shoe2.jpg', 'Walk & Wear', '38,39,40,41,42,43,44'),
(3, 'Slam Dunk High-Tops', 'High-top basketball shoes with ankle support and grip sole.', 2499.00, 15, 'images/shoe3.jpg', 'Walk & Wear', '40,41,42,43,44,45'),
(4, 'Summer Breeze Sandals', 'Comfortable open sandals ideal for hot days.', 799.00, 40, 'images/shoe4.jpg', 'Walk & Wear', '37,38,39,40,41,42'),
(5, 'Trailblazer Boots', 'Durable outdoor boots with water-resistant coating.', 2999.00, 12, 'images/shoe5.jpg', 'Walk & Wear', '39,40,41,42,43,44'),
(2, 'Retro Canvas Low', 'Classic canvas low-top sneaker for a vintage look.', 1299.00, 35, 'images/shoe6.jpg', 'Walk & Wear', '36,37,38,39,40,41,42');
