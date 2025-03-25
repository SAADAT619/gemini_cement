-- Database: cement_shop

-- Table: users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Table: categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

-- Table: products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Table: sellers
CREATE TABLE sellers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT
);

-- -- Table: purchases
-- CREATE TABLE purchases (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     seller_id INT,
--     product_id INT,
--     quantity INT NOT NULL,
--     price DECIMAL(10, 2) NOT NULL,
--     total DECIMAL(10, 2) NOT NULL,
--     paid DECIMAL(10, 2) NOT NULL,
--     due DECIMAL(10, 2) NOT NULL,
--     purchase_date DATE NOT NULL,
--     invoice_number VARCHAR(255) NOT NULL,
--     FOREIGN KEY (seller_id) REFERENCES sellers(id),
--     FOREIGN KEY (product_id) REFERENCES products(id)
-- );

-- Table: customers
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT
);

-- Table: sales
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    paid DECIMAL(10, 2) NOT NULL,
    due DECIMAL(10, 2) NOT NULL,
    sale_date DATE NOT NULL,
    invoice_number VARCHAR(255) NOT NULL,
    payment_method VARCHAR(50),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    paid DECIMAL(10,2) NOT NULL,
    due DECIMAL(10,2) NOT NULL,
    purchase_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    invoice_number VARCHAR(20) NOT NULL,
    FOREIGN KEY (seller_id) REFERENCES sellers(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE settings (
    setting_key VARCHAR(255) PRIMARY KEY,
    value TEXT
);

INSERT INTO settings (setting_key, value) VALUES
('shop_name', 'Your Shop Name'),
('shop_address', 'Your Shop Address'),
('shop_phone', 'Your Shop Phone');
-- Insert a test user
INSERT INTO users (email, password) VALUES ('admin@example.com', 'password123');