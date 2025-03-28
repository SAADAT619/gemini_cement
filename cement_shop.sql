-- Drop the database if it exists (optional, for resetting during development)
DROP DATABASE IF EXISTS cement_shop;

-- Create the database
CREATE DATABASE cement_shop;
USE cement_shop;

-- Table: users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    mobile_number VARCHAR(20),
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL CHECK (quantity >= 0), -- Prevent negative stock at the database level
    unit VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Table: sellers
CREATE TABLE sellers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: purchases
CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT,
    product_id INT,
    quantity INT NOT NULL CHECK (quantity > 0), -- Ensure quantity is positive
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    total DECIMAL(10, 2) NOT NULL CHECK (total >= 0),
    paid DECIMAL(10, 2) NOT NULL CHECK (paid >= 0),
    due DECIMAL(10, 2) NOT NULL CHECK (due >= 0),
    purchase_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_invoice_number (invoice_number) -- Index for faster lookups
);

-- Table: customers
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20), -- Removed NOT NULL constraint to allow optional phone numbers
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name), -- Index for faster searches by name
    INDEX idx_phone (phone), -- Index for faster searches by phone
    FULLTEXT idx_address (address) -- FULLTEXT index for faster searches by address
);

-- Table: sales
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    sale_date DATE NOT NULL,
    invoice_number VARCHAR(255) NOT NULL UNIQUE,
    payment_method VARCHAR(50) NOT NULL,
    total DECIMAL(10, 2) NOT NULL CHECK (total >= 0),
    paid DECIMAL(10, 2) NOT NULL CHECK (paid >= 0),
    due DECIMAL(10, 2) NOT NULL CHECK (due >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_invoice_number (invoice_number) -- Index for faster lookups
);

-- Table: sale_items
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT,
    product_id INT,
    quantity DECIMAL(10, 2) NOT NULL CHECK (quantity > 0), -- Ensure quantity is positive
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    subtotal DECIMAL(10, 2) NOT NULL CHECK (subtotal >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_sale_id (sale_id), -- Index for faster joins
    INDEX idx_product_id (product_id) -- Index for faster joins
);

-- Table: settings
CREATE TABLE settings (
    setting_key VARCHAR(255) PRIMARY KEY,
    value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (setting_key, value) VALUES
('shop_name', 'Cement Shop'),
('shop_address', '123 Cement Road, City'),
('shop_phone', '123-456-7890'),
('low_stock_threshold', '5'); -- Low stock threshold

-- Insert a test user
INSERT INTO users (email, password, mobile_number, name, role) VALUES 
('admin@example.com', 'password123', '1234567890', 'Admin User', 'admin');

-- Insert sample categories
INSERT INTO categories (name) VALUES 
('Cement'),
('Rod');

-- Insert sample products
INSERT INTO products (category_id, name, price, quantity, unit) VALUES 
(1, '7 Rings', 550.00, 15, 'bag'),
(2, 'BSRM', 250.00, 20, 'piece');

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
('Seller Two', '1122334455', '789 Seller Lane');

-- Insert sample purchases (for testing)
INSERT INTO purchases (seller_id, product_id, quantity, price, total, paid, due, purchase_date, payment_method, invoice_number) VALUES 
(1, 1, 10, 500.00, 5000.00, 4000.00, 1000.00, '2025-03-28', 'cash', 'PUR-20250328001'),
(2, 2, 15, 200.00, 3000.00, 3000.00, 0.00, '2025-03-28', 'bank_transfer', 'PUR-20250328002');

-- Insert sample sales (for testing)
INSERT INTO sales (customer_id, sale_date, invoice_number, payment_method, total, paid, due) VALUES 
(1, '2025-03-28', 'INV-20250328001', 'cash', 3850.00, 3000.00, 850.00),
(2, '2025-03-28', 'INV-20250328002', 'credit_card', 2500.00, 2000.00, 500.00),
(3, '2025-03-27', 'INV-20250327001', 'bank_transfer', 1100.00, 1000.00, 100.00),
(4, '2025-03-27', 'INV-20250327002', 'cash', 550.00, 500.00, 50.00),
(5, '2025-03-26', 'INV-20250326001', 'credit_card', 250.00, 250.00, 0.00);

-- Insert sample sale items (aligned with sales totals)
INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal) VALUES 
(1, 1, 7, 550.00, 3850.00), -- Matches total of INV-20250328001
(2, 2, 10, 250.00, 2500.00), -- Matches total of INV-20250328002
(3, 1, 2, 550.00, 1100.00), -- Matches total of INV-20250327001
(4, 1, 1, 550.00, 550.00), -- Matches total of INV-20250327002
(5, 2, 1, 250.00, 250.00); -- Matches total of INV-20250326001