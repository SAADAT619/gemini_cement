-- Drop the database if it exists (optional, for resetting during development)
DROP DATABASE IF EXISTS cement_shop;

-- Create the database
CREATE DATABASE cement_shop;
USE cement_shop;

-- Table: users (Stores user accounts for the system)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    mobile_number VARCHAR(20),
    role VARCHAR(20) DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: categories (Stores product categories like Cement, Rod)
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_categories_name (name)
);

-- Table: category_units (Defines valid units for each category)
CREATE TABLE category_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    unit VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY uk_category_unit (category_id, unit)
);

-- Table: rod_types (Stores valid rod types like 8mm, 10mm, etc.)
CREATE TABLE rod_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: payment_methods (Stores valid payment methods)
CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: products (Stores product details)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    quantity DECIMAL(10, 2) NOT NULL CHECK (quantity >= 0),
    unit VARCHAR(50),
    brand_name VARCHAR(255),
    type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    UNIQUE KEY uk_products (name, category_id, unit, price, brand_name),
    INDEX idx_products_created_at (created_at),
    INDEX idx_products_lookup (name, category_id, unit, price, brand_name),
    INDEX idx_products_category_id (category_id),
    CONSTRAINT fk_product_unit FOREIGN KEY (category_id, unit) REFERENCES category_units(category_id, unit) ON DELETE SET NULL
);

-- Table: sellers (Stores seller information)
CREATE TABLE sellers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_phone (phone),
    FULLTEXT idx_address (address)
);

-- Table: purchases (Legacy table for buy.php compatibility, reverted to previous structure)
CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT,
    product_id INT,
    quantity DECIMAL(10, 2) CHECK (quantity > 0),
    price DECIMAL(10, 2) CHECK (price >= 0),
    total DECIMAL(10, 2) CHECK (total >= 0),
    paid DECIMAL(10, 2) CHECK (paid >= 0),
    due DECIMAL(10, 2) CHECK (due >= 0),
    purchase_date DATE NOT NULL,
    payment_method_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    unit VARCHAR(50),
    type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE RESTRICT,
    INDEX idx_purchase_date (purchase_date),
    INDEX idx_invoice_number (invoice_number)
);

-- Table: purchase_headers (Recommended structure for main purchase records)
CREATE TABLE purchase_headers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL CHECK (total >= 0),
    paid DECIMAL(10, 2) NOT NULL CHECK (paid >= 0),
    due DECIMAL(10, 2) NOT NULL CHECK (due >= 0),
    purchase_date DATE NOT NULL,
    payment_method_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE RESTRICT,
    INDEX idx_purchase_date (purchase_date),
    INDEX idx_invoice_number (invoice_number)
);

-- Table: purchase_items (Recommended structure for individual purchase items)
CREATE TABLE purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT,
    product_id INT,
    quantity DECIMAL(10, 2) NOT NULL CHECK (quantity > 0),
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    total DECIMAL(10, 2) NOT NULL CHECK (total >= 0),
    unit VARCHAR(50) NOT NULL,
    type VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES purchase_headers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_purchase_id (purchase_id),
    INDEX idx_product_id (product_id)
);

-- Table: customers (Stores customer information)
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_phone (phone),
    FULLTEXT idx_address (address)
);

-- Table: sales (Stores sales records, updated to use payment_method_id)
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    sale_date DATE NOT NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    payment_method_id INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL CHECK (total >= 0),
    paid DECIMAL(10, 2) NOT NULL CHECK (paid >= 0),
    due DECIMAL(10, 2) NOT NULL CHECK (due >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE RESTRICT,
    INDEX idx_sale_date (sale_date),
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_customer_id (customer_id),
    INDEX idx_payment_method_id (payment_method_id)
);

-- Table: sale_items (Stores individual items for each sale)
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT,
    product_id INT,
    quantity DECIMAL(10, 2) NOT NULL CHECK (quantity > 0),
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    subtotal DECIMAL(10, 2) NOT NULL CHECK (subtotal >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_sale_id (sale_id),
    INDEX idx_product_id (product_id)
);

-- Table: shop_details (Stores editable shop details)
CREATE TABLE shop_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shop_name VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Triggers to update product quantities and validate sale_date
DELIMITER //

-- Trigger: Before inserting a sale, ensure sale_date is not in the future
CREATE TRIGGER before_sale_insert
BEFORE INSERT ON sales
FOR EACH ROW
BEGIN
    IF NEW.sale_date > CURDATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Sale date cannot be in the future';
    END IF;
END //

-- Trigger: Before updating a sale, ensure sale_date is not in the future
CREATE TRIGGER before_sale_update
BEFORE UPDATE ON sales
FOR EACH ROW
BEGIN
    IF NEW.sale_date > CURDATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Sale date cannot be in the future';
    END IF;
END //

-- Trigger: After inserting a purchase item, increase product quantity
CREATE TRIGGER after_purchase_item_insert
AFTER INSERT ON purchase_items
FOR EACH ROW
BEGIN
    UPDATE products 
    SET quantity = quantity + NEW.quantity
    WHERE id = NEW.product_id;
END //

-- Trigger: After updating a purchase item, adjust product quantity
CREATE TRIGGER after_purchase_item_update
AFTER UPDATE ON purchase_items
FOR EACH ROW
BEGIN
    UPDATE products 
    SET quantity = quantity - OLD.quantity + NEW.quantity
    WHERE id = NEW.product_id;
END //

-- Trigger: After deleting a purchase item, decrease product quantity
CREATE TRIGGER after_purchase_item_delete
AFTER DELETE ON purchase_items
FOR EACH ROW
BEGIN
    UPDATE products 
    SET quantity = GREATEST(quantity - OLD.quantity, 0)
    WHERE id = OLD.product_id;
END //

-- Trigger: After inserting a sale item, decrease product quantity with stock check
CREATE TRIGGER after_sale_item_insert
AFTER INSERT ON sale_items
FOR EACH ROW
BEGIN
    DECLARE current_quantity DECIMAL(10, 2);
    
    SELECT quantity INTO current_quantity 
    FROM products 
    WHERE id = NEW.product_id FOR UPDATE;
    
    IF current_quantity < NEW.quantity THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Insufficient stock for product';
    ELSE
        UPDATE products 
        SET quantity = quantity - NEW.quantity
        WHERE id = NEW.product_id;
    END IF;
END //

-- Trigger: After updating a sale item, adjust product quantity with stock check
CREATE TRIGGER after_sale_item_update
AFTER UPDATE ON sale_items
FOR EACH ROW
BEGIN
    DECLARE current_quantity DECIMAL(10, 2);
    
    SELECT quantity INTO current_quantity 
    FROM products 
    WHERE id = NEW.product_id FOR UPDATE;
    
    SET current_quantity = current_quantity + OLD.quantity;
    
    IF current_quantity < NEW.quantity THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Insufficient stock for product after update';
    ELSE
        UPDATE products 
        SET quantity = current_quantity - NEW.quantity
        WHERE id = NEW.product_id;
    END IF;
END //

-- Trigger: After deleting a sale item, increase product quantity
CREATE TRIGGER after_sale_item_delete
AFTER DELETE ON sale_items
FOR EACH ROW
BEGIN
    UPDATE products 
    SET quantity = quantity + OLD.quantity
    WHERE id = OLD.product_id;
END //

DELIMITER ;

-- Insert default shop details
INSERT INTO shop_details (id, shop_name, address, phone, email) VALUES
(1, 'Gemini Cement Store', '123 Business Avenue, City, Country', '+123-456-7890', 'contact@geminicement.com');

-- Insert payment methods
INSERT INTO payment_methods (method) VALUES 
('Cash'),
('Bank Transfer'),
('Credit Card'),
('Other');

-- Insert a test user
INSERT INTO users (email, password, mobile_number, name, role) VALUES 
('admin@example.com', 'password123', '1234567890', 'Admin User', 'admin');

-- Insert sample categories
INSERT INTO categories (name) VALUES 
('Cement'),
('Rod');

-- Insert valid units for categories
INSERT INTO category_units (category_id, unit) VALUES 
(1, 'bags'),
(1, 'kg'),
(1, 'gram'),
(2, 'ton'),
(2, 'piece'),
(2, 'inches');

-- Insert valid rod types
INSERT INTO rod_types (type) VALUES 
('8mm'),
('10mm'),
('12mm'),
('16mm'),
('20mm'),
('25mm');

-- Insert sample products with initial quantities and rod types
INSERT INTO products (category_id, name, price, quantity, unit, brand_name, type) VALUES 
(1, '7 Rings', 550.00, 15, 'bags', '7 Rings', NULL),
(1, 'Shah Cement', 540.00, 20, 'bags', 'Shah Cement', NULL),
(1, 'Premier Cement', 530.00, 25, 'bags', 'Premier', NULL),
(2, 'BSRM', 250.00, 20, 'piece', 'BSRM', '8mm'),
(2, 'KSRM', 260.00, 30, 'piece', 'KSRM', '10mm'),
(2, 'Rod', 250.00, 40, 'piece', 'BSRM', '12mm');

-- Insert sample customers
INSERT INTO customers (name, phone, address) VALUES 
('MD Samin Yasar Sadaat', '1234567890', '123 Customer Street'),
('John Doe', '0987654321', '456 Customer Avenue'),
('Jane Smith', '1122334455', '789 Customer Lane'),
('Alice Johnson', '2233445566', '101 Customer Road'),
('Bob Brown', '3344556677', '202 Customer Blvd');

-- Insert sample sellers
INSERT INTO sellers (name, phone, address) VALUES 
('Seller One', '0987654321', '456 Seller Avenue'),
('Seller Two', '1122334455', '789 Seller Lane'),
('Seller Three', '6677889900', '321 Seller Road'),
('Seller Four', '5566778899', '654 Seller Street');

-- Insert sample purchase headers
INSERT INTO purchase_headers (seller_id, total, paid, due, purchase_date, payment_method_id, invoice_number) VALUES 
(1, 5000.00, 4000.00, 1000.00, '2025-04-10', 1, 'PUR-20250410001'),
(2, 3000.00, 2500.00, 500.00, '2025-04-11', 2, 'PUR-20250411001'),
(1, 2200.00, 2000.00, 200.00, '2025-03-15', 1, 'PUR-20250315001'),
(2, 1500.00, 1500.00, 0.00, '2025-03-20', 3, 'PUR-20250320001'),
(3, 5400.00, 5000.00, 400.00, '2025-04-11', 1, 'PUR-20250411002'),
(4, 2600.00, 2600.00, 0.00, '2025-04-11', 2, 'PUR-20250411003');

-- Insert sample purchase items
INSERT INTO purchase_items (purchase_id, product_id, quantity, price, total, unit, type) VALUES 
(1, 1, 10, 500.00, 5000.00, 'bags', NULL),
(2, 4, 15, 200.00, 3000.00, 'piece', '8mm'),
(3, 1, 4, 550.00, 2200.00, 'bags', NULL),
(4, 4, 6, 250.00, 1500.00, 'piece', '10mm'),
(5, 2, 10, 540.00, 5400.00, 'bags', NULL),
(6, 5, 10, 260.00, 2600.00, 'piece', '12mm');

-- Insert sample purchases (reverted to previous structure for buy.php compatibility)
INSERT INTO purchases (seller_id, product_id, quantity, price, total, paid, due, purchase_date, payment_method_id, invoice_number, unit, type) VALUES 
(1, NULL, NULL, NULL, 5000.00, 4000.00, 1000.00, '2025-04-10', 1, 'PUR-20250410001', NULL, NULL),
(1, 1, 10, 500.00, 5000.00, 4000.00, 1000.00, '2025-04-10', 1, 'PUR-20250410001', 'bags', NULL),
(2, NULL, NULL, NULL, 3000.00, 2500.00, 500.00, '2025-04-11', 2, 'PUR-20250411001', NULL, NULL),
(2, 4, 15, 200.00, 3000.00, 2500.00, 500.00, '2025-04-11', 2, 'PUR-20250411001', 'piece', '8mm'),
(1, NULL, NULL, NULL, 2200.00, 2000.00, 200.00, '2025-03-15', 1, 'PUR-20250315001', NULL, NULL),
(1, 1, 4, 550.00, 2200.00, 2000.00, 200.00, '2025-03-15', 1, 'PUR-20250315001', 'bags', NULL),
(2, NULL, NULL, NULL, 1500.00, 1500.00, 0.00, '2025-03-20', 3, 'PUR-20250320001', NULL, NULL),
(2, 4, 6, 250.00, 1500.00, 1500.00, 0.00, '2025-03-20', 3, 'PUR-20250320001', 'piece', '10mm'),
(3, NULL, NULL, NULL, 5400.00, 5000.00, 400.00, '2025-04-11', 1, 'PUR-20250411002', NULL, NULL),
(3, 2, 10, 540.00, 5400.00, 5000.00, 400.00, '2025-04-11', 1, 'PUR-20250411002', 'bags', NULL),
(4, NULL, NULL, NULL, 2600.00, 2600.00, 0.00, '2025-04-11', 2, 'PUR-20250411003', NULL, NULL),
(4, 5, 10, 260.00, 2600.00, 2600.00, 0.00, '2025-04-11', 2, 'PUR-20250411003', 'piece', '12mm');

-- Insert sample sales (updated sale_date for SALE-20250413001 to 2025-04-11)
INSERT INTO sales (customer_id, sale_date, invoice_number, payment_method_id, total, paid, due) VALUES 
(1, '2025-03-28', 'SALE-20250328001', 1, 3850.00, 3850.00, 0.00),
(2, '2025-03-28', 'SALE-20250328002', 3, 2500.00, 2500.00, 0.00),
(3, '2025-03-27', 'SALE-20250327001', 2, 1100.00, 1100.00, 0.00),
(4, '2025-03-27', 'SALE-20250327002', 1, 550.00, 550.00, 0.00),
(5, '2025-03-26', 'SALE-20250326001', 3, 250.00, 250.00, 0.00),
(1, '2025-04-10', 'SALE-20250410001', 1, 2750.00, 2000.00, 750.00),
(2, '2025-04-11', 'SALE-20250411001', 2, 1250.00, 1000.00, 250.00),
(3, '2025-04-01', 'SALE-20250401001', 1, 1650.00, 1500.00, 150.00),
(4, '2025-03-15', 'SALE-20250315001', 2, 750.00, 750.00, 0.00),
(5, '2025-04-11', 'SALE-20250411002', 1, 2650.00, 2500.00, 150.00); -- Changed from 2025-04-13 to 2025-04-11

-- Insert sample sale items (updated sale_id for the last sale to match the new order)
INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal) VALUES 
(1, 1, 7, 550.00, 3850.00),
(2, 4, 10, 250.00, 2500.00),
(3, 1, 2, 550.00, 1100.00),
(4, 1, 1, 550.00, 550.00),
(5, 4, 1, 250.00, 250.00),
(6, 1, 5, 550.00, 2750.00),
(7, 4, 5, 250.00, 1250.00),
(8, 1, 3, 550.00, 1650.00),
(9, 4, 3, 250.00, 750.00),
(10, 3, 5, 530.00, 2650.00); -- Updated sale_id to 10

-- Comment: Final product quantities after purchases and sales
-- Product 1 (7 Rings): 15 + 10 + 4 - 7 - 2 - 1 - 5 - 3 = 11
-- Product 2 (Shah Cement): 20 + 10 = 30
-- Product 3 (Premier Cement): 25 - 5 = 20
-- Product 4 (BSRM): 20 + 15 + 6 - 10 - 1 - 5 - 3 = 22
-- Product 5 (KSRM): 30 + 10 = 40
-- Product 6 (Rod): 40 (unchanged)