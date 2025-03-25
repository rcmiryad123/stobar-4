<?php
class Product {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getAll() {
        $query = 'SELECT p.id, p.name, p.description, p.unit, p.min_stock,
                 (COALESCE(SUM(CASE WHEN sm.type = "in" THEN sm.quantity ELSE 0 END), 0) -
                 COALESCE(SUM(CASE WHEN sm.type = "out" THEN sm.quantity ELSE 0 END), 0)) as current_quantity
                 FROM products p
                 LEFT JOIN stock_movements sm ON p.id = sm.product_id
                 GROUP BY p.id, p.name, p.description, p.unit, p.min_stock
                 ORDER BY p.name';
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    public function getById($id) {
        $stmt = $this->conn->prepare('SELECT id, name, description, unit, min_stock FROM products WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function add($name, $description, $unit, $min_stock) {
        $stmt = $this->conn->prepare('INSERT INTO products (name, description, unit, min_stock) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('sssi', $name, $description, $unit, $min_stock);
        return $stmt->execute();
    }
    
    public function update($id, $name, $description, $unit, $min_stock) {
        $stmt = $this->conn->prepare('UPDATE products SET name = ?, description = ?, unit = ?, min_stock = ? WHERE id = ?');
        $stmt->bind_param('sssii', $name, $description, $unit, $min_stock, $id);
        return $stmt->execute();
    }
    
    public function delete($id) {
        $stmt = $this->conn->prepare('DELETE FROM products WHERE id = ?');
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
    
    public function validateInput($name, $unit, $min_stock) {
        $errors = [];
        
        if (empty(trim($name))) {
            $errors[] = 'Product name is required';
        }
        
        if (empty(trim($unit))) {
            $errors[] = 'Unit is required';
        }
        
        if ($min_stock < 0) {
            $errors[] = 'Minimum stock cannot be negative';
        }
        
        return $errors;
    }
    
    public function getStockStatus($current_quantity, $min_stock) {
        if ($current_quantity <= 0) {
            return ['class' => 'status-low', 'text' => 'Out of Stock'];
        } else if ($current_quantity <= $min_stock) {
            return ['class' => 'status-medium', 'text' => 'Low'];
        }
        return ['class' => 'status-good', 'text' => 'Good'];
    }
}