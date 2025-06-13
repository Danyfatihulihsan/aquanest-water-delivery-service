-- Create database if not exists
CREATE DATABASE IF NOT EXISTS aquanest;
USE aquanest;

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
    customer_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_phone (phone)
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    image VARCHAR(255),
    active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Couriers table
CREATE TABLE IF NOT EXISTS couriers (
    courier_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    status ENUM('available', 'assigned', 'off_duty') DEFAULT 'available',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    courier_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cod', 'bank_transfer', 'qris') NOT NULL,
    payment_status ENUM('pending', 'waiting', 'paid', 'failed') DEFAULT 'pending',
    payment_proof VARCHAR(255),
    order_status ENUM('pending', 'processing', 'on_delivery', 'delivered', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    estimated_delivery DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (courier_id) REFERENCES couriers(courier_id)
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Order tracking history table
CREATE TABLE IF NOT EXISTS order_tracking_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    is_completed BOOLEAN DEFAULT FALSE,
    is_current BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
);

-- Subscription plans table
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    delivery_count INT NOT NULL,
    discount VARCHAR(20),
    popular BOOLEAN DEFAULT FALSE,
    active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Subscriptions table
CREATE TABLE IF NOT EXISTS subscriptions (
    subscription_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    status ENUM('active', 'paused', 'cancelled') DEFAULT 'active',
    delivery_day VARCHAR(20) NOT NULL,
    next_delivery DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
);

-- Triggers for order tracking
DELIMITER //

CREATE TRIGGER after_order_insert
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    -- Insert initial tracking entry
    INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current)
    VALUES (NEW.order_id, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', TRUE, TRUE);
    
    -- Add payment tracking based on payment method
    IF NEW.payment_method = 'cod' THEN
        INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current)
        VALUES (NEW.order_id, 'Pembayaran COD', 'Pembayaran akan dilakukan saat pesanan tiba', FALSE, FALSE);
    ELSE
        INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current)
        VALUES (NEW.order_id, 'Menunggu Pembayaran', 'Silakan lakukan pembayaran sesuai metode yang dipilih', FALSE, FALSE);
    END IF;
END //

CREATE TRIGGER after_payment_update
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current)
        VALUES (NEW.order_id, 'Pembayaran Berhasil', 'Pembayaran telah dikonfirmasi', TRUE, TRUE);
    END IF;
    
    IF NEW.order_status = 'on_delivery' AND OLD.order_status != 'on_delivery' THEN
        INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current)
        VALUES (NEW.order_id, 'Dalam Pengiriman', 'Pesanan sedang dalam perjalanan', TRUE, TRUE);
    END IF;
    
    IF NEW.order_status = 'delivered' AND OLD.order_status != 'delivered' THEN
        INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current)
        VALUES (NEW.order_id, 'Pesanan Selesai', 'Pesanan telah diterima', TRUE, TRUE);
    END IF;
END //

DELIMITER ;

-- Insert sample data
INSERT INTO products (name, description, price, stock) VALUES
('Galon Air 19L', 'Galon air mineral 19 liter', 25000, 100),
('Galon Air 12L', 'Galon air mineral 12 liter', 18000, 100),
('Botol Air 1.5L', 'Botol air mineral 1.5 liter', 6000, 200);

INSERT INTO subscription_plans (name, description, price, duration, delivery_count, discount, popular) VALUES
('Paket Hemat', '1x pengiriman per minggu', 90000, 'Bulanan', 4, '10%', FALSE),
('Paket Populer', '2x pengiriman per minggu', 170000, 'Bulanan', 8, '15%', TRUE),
('Paket Bisnis', '3x pengiriman per minggu', 240000, 'Bulanan', 12, '20%', FALSE);

INSERT INTO couriers (name, phone, status) VALUES
('John Delivery', '081234567890', 'available'),
('Mike Express', '081234567891', 'available');
