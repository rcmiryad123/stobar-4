-- Database schema for Stock Inventory Web Application (STOBAR)

-- Create database
CREATE DATABASE IF NOT EXISTS stobar;
USE stobar;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'guest') NOT NULL DEFAULT 'guest',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    unit VARCHAR(20) NOT NULL,
    min_stock INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create stock_movements table
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    type ENUM('in', 'out') NOT NULL,
    quantity INT NOT NULL,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    created_by INT NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Create current_stock view
CREATE OR REPLACE VIEW current_stock AS
SELECT 
    p.id,
    p.name,
    p.description,
    p.unit,
    p.min_stock,
    COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity ELSE 0 END), 0) -
    COALESCE(SUM(CASE WHEN sm.type = 'out' THEN sm.quantity ELSE 0 END), 0) AS current_quantity
FROM 
    products p
LEFT JOIN 
    stock_movements sm ON p.id = sm.product_id
GROUP BY 
    p.id, p.name, p.description, p.unit, p.min_stock;

-- Insert default admin user (password: admin123) and guest user (password: guest123)
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$YWxwaGFiZXRhMTIzNOPxQOKVrO5gQ0s6Y7zV8FCM.Byg5rQWQW/Ue', 'admin'),
('guest', '$2y$10$YWxwaGFiZXRhMTIzNOPxQOKVrO5gQ0s6Y7zV8FCM.Byg5rQWQW/Ue', 'guest');

-- Insert sample products
INSERT INTO products (name, description, unit, min_stock) VALUES
('Laptop', 'Business laptop with 16GB RAM', 'unit', 5),
('Printer Paper', 'A4 size printer paper', 'ream', 10),
('Ballpoint Pen', 'Blue ballpoint pen', 'box', 3),
('Stapler', 'Standard office stapler', 'unit', 2),
('Whiteboard Marker', 'Black whiteboard marker', 'box', 4);

-- Insert sample stock movements
INSERT INTO stock_movements (product_id, type, quantity, notes, created_by) VALUES
(1, 'in', 20, 'Initial stock', 1),
(2, 'in', 50, 'Initial stock', 1),
(3, 'in', 30, 'Initial stock', 1),
(4, 'in', 15, 'Initial stock', 1),
(5, 'in', 25, 'Initial stock', 1),
(1, 'out', 3, 'Used for new employees', 1),
(2, 'out', 5, 'Monthly usage', 1),
(3, 'out', 2, 'Office supply', 1),
(5, 'out', 4, 'Used for meeting', 1);