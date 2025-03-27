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
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
('shop_name', 'Your Shop Name'),
('shop_address', 'Your Shop Address'),
('shop_phone', 'Your Shop Phone'),
('low_stock_threshold', '5'); -- Added setting for low stock threshold

-- Insert a test user
INSERT INTO users (email, password, mobile_number, name, role) VALUES 
('admin@example.com', 'password123', '1234567890', 'Admin User', 'admin');

-- Insert sample categories
INSERT INTO categories (name) VALUES 
('Cement'),
('Rod');

-- Insert sample products
INSERT INTO products (category_id, name, price, quantity, unit) VALUES 
(1, '7 rings', 550.00, 15, 'bag'),
(2, 'BSRM', 250.00, 0, 'piece');

-- Insert sample customers
INSERT INTO customers (name, phone, address) VALUES 
('MD SAMIN YASAR SADAAT', '1234567890', '123 Customer Street');

-- Insert sample sellers
INSERT INTO sellers (name, phone, address) VALUES 
('Seller One', '0987654321', '456 Seller Avenue');

-- Insert sample sales (for testing)
INSERT INTO sales (customer_id, sale_date, invoice_number, payment_method, total, paid, due) VALUES 
(1, '2025-03-27', 'INV-20250327131610', 'cash', 10000.00, 8000.00, 2000.00);

-- Insert sample sale items
INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal) VALUES 
(1, 1, 7, 10.00, 70.00);