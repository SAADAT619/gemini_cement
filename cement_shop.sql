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
    quantity DECIMAL(10, 2) NOT NULL CHECK (quantity >= 0), -- Changed to DECIMAL to match purchase_items and sale_items
    unit VARCHAR(50),
    brand_name VARCHAR(255),
    type VARCHAR(50) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    UNIQUE KEY uk_products (name, category_id, unit, price, brand_name, type)
);

CREATE INDEX idx_products_created_at ON products (created_at);
CREATE INDEX idx_products_lookup ON products (name, category_id, unit, price, brand_name, type);

-- Table: sellers
CREATE TABLE sellers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: purchase_headers (Replaces purchases for main records)
CREATE TABLE purchase_headers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    paid DECIMAL(10, 2) NOT NULL,
    due DECIMAL(10, 2) NOT NULL,
    purchase_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE
);

-- Table: purchase_items (Stores individual items for each purchase)
CREATE TABLE purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT,
    product_id INT,
    quantity DECIMAL(10, 2) NOT NULL CHECK (quantity > 0), -- Changed to DECIMAL to match sale_items and products
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    total DECIMAL(10, 2) NOT NULL CHECK (total >= 0),
    unit VARCHAR(50) NOT NULL,
    type VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES purchase_headers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Table: customers
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
    INDEX idx_invoice_number (invoice_number)
);

-- Table: sale_items
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

-- Table: shop_details (Replaces settings table for editable shop details)
CREATE TABLE shop_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shop_name VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default shop details
INSERT INTO shop_details (id, shop_name, address, phone, email) VALUES
(1, 'Gemini Cement Store', '123 Business Avenue, City, Country', '+123-456-7890', 'contact@geminicement.com');

-- Insert a test user
INSERT INTO users (email, password, mobile_number, name, role) VALUES 
('admin@example.com', 'password123', '1234567890', 'Admin User', 'admin');

-- Insert sample categories
INSERT INTO categories (name) VALUES 
('Cement'),
('Rod');

-- Insert sample products
INSERT INTO products (category_id, name, price, quantity, unit, brand_name, type) VALUES 
(1, '7 Rings', 550.00, 15, 'bag', '7 Rings', ''),
(2, 'BSRM', 250.00, 20, 'piece', 'BSRM', '8mm'),
(2, 'Rod', 250.00, 40, 'piece', 'BSRM', '8mm');

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

-- Insert sample purchase headers
INSERT INTO purchase_headers (seller_id, total, paid, due, purchase_date, payment_method, invoice_number) VALUES 
(1, 5000.00, 4000.00, 1000.00, '2025-03-28', 'cash', 'PUR-20250328001'),
(2, 3000.00, 3000.00, 0.00, '2025-03-28', 'bank_transfer', 'PUR-20250328002');

-- Insert sample purchase items
INSERT INTO purchase_items (purchase_id, product_id, quantity, price, total, unit, type) VALUES 
(1, 1, 10, 500.00, 5000.00, 'bag', NULL),
(2, 2, 15, 200.00, 3000.00, 'piece', '8mm');

-- Insert sample sales
INSERT INTO sales (customer_id, sale_date, invoice_number, payment_method, total, paid, due) VALUES 
(1, '2025-03-28', 'INV-20250328001', 'cash', 3850.00, 3000.00, 850.00),
(2, '2025-03-28', 'INV-20250328002', 'credit_card', 2500.00, 2000.00, 500.00),
(3, '2025-03-27', 'INV-20250327001', 'bank_transfer', 1100.00, 1000.00, 100.00),
(4, '2025-03-27', 'INV-20250327002', 'cash', 550.00, 500.00, 50.00),
(5, '2025-03-26', 'INV-20250326001', 'credit_card', 250.00, 250.00, 0.00);

-- Insert sample sale items
INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal) VALUES 
(1, 1, 7, 550.00, 3850.00),
(2, 2, 10, 250.00, 2500.00),
(3, 1, 2, 550.00, 1100.00),
(4, 1, 1, 550.00, 550.00),
(5, 2, 1, 250.00, 250.00);