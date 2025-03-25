<?php
// Start session
session_start();

// Include database connection
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php');
    exit;
}

// Get database connection
$conn = getDB();

// Get current user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Initialize variables
$message = '';
$message_type = '';
$selected_product = null;
$movement_type = isset($_GET['type']) ? $_GET['type'] : 'in';

// Handle stock movement actions (add, delete)
if ($role === 'admin' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Handle delete action
    if ($action === 'delete' && isset($_GET['id'])) {
        $movement_id = (int)$_GET['id'];
        
        // Delete stock movement
        $stmt = $conn->prepare('DELETE FROM stock_movements WHERE id = ?');
        $stmt->bind_param('i', $movement_id);
        
        if ($stmt->execute()) {
            $message = 'Stock movement deleted successfully';
            $message_type = 'success';
        } else {
            $message = 'Error deleting stock movement: ' . $conn->error;
            $message_type = 'danger';
        }
        
        $stmt->close();
    }
    
    // Handle add form submission
    if (($_SERVER['REQUEST_METHOD'] === 'POST') && $action === 'add') {
        // Get form data
        $product_id = (int)$_POST['product_id'];
        $type = $_POST['type'];
        $quantity = (int)$_POST['quantity'];
        $notes = trim($_POST['notes']);
        
        // Validate form data
        if (empty($product_id) || empty($type) || $quantity <= 0) {
            $message = 'Please fill all required fields with valid values';
            $message_type = 'danger';
        } else {
            // Add new stock movement
            $stmt = $conn->prepare('INSERT INTO stock_movements (product_id, type, quantity, notes, created_by) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('isisi', $product_id, $type, $quantity, $notes, $user_id);
            
            if ($stmt->execute()) {
                $message = 'Stock movement added successfully';
                $message_type = 'success';
                
                // Redirect to stock movements page
                header('Location: stock_movements.php?message=' . urlencode($message) . '&type=' . $message_type);
                exit;
            } else {
                $message = 'Error adding stock movement: ' . $conn->error;
                $message_type = 'danger';
            }
            
            $stmt->close();
        }
    }
}

// Check for message in URL parameters
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'];
}

// Get product data for form
if (isset($_GET['product_id'])) {
    $product_id = (int)$_GET['product_id'];
    
    $stmt = $conn->prepare('SELECT p.id, p.name, p.unit, (COALESCE(SUM(CASE WHEN sm.type = "in" THEN sm.quantity ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN sm.type = "out" THEN sm.quantity ELSE 0 END), 0)) as current_quantity FROM products p LEFT JOIN stock_movements sm ON p.id = sm.product_id WHERE p.id = ? GROUP BY p.id, p.name, p.unit');
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $selected_product = $result->fetch_assoc();
    }
    
    $stmt->close();
}

// Get all products for dropdown
$stmt = $conn->prepare('SELECT id, name FROM products ORDER BY name');
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

// Get all stock movements
$stmt = $conn->prepare('SELECT sm.id, p.name as product_name, sm.type, sm.quantity, sm.date, sm.notes, u.username 
                       FROM stock_movements sm 
                       JOIN products p ON sm.product_id = p.id 
                       JOIN users u ON sm.created_by = u.id 
                       ORDER BY sm.date DESC');
$stmt->execute();
$stock_movements = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movements - Stock Inventory System</title>
    <link href="vendor/font-stock-movement.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container header-container">
            <div class="logo">
                <a href="index.php">STOBAR</a>
            </div>
            
            <nav class="nav">
                <div class="nav-item">
                    <a href="index.php" class="nav-link">Dashboard</a>
                </div>
                <div class="nav-item">
                    <a href="products.php" class="nav-link">Products</a>
                </div>
                <div class="nav-item">
                    <a href="stock_movements.php" class="nav-link active">Stock Movements</a>
                </div>
                <?php if ($role === 'admin'): ?>
                <div class="nav-item">
                    <a href="reports.php" class="nav-link">Reports</a>
                </div>
                <?php endif; ?>
            </nav>
            
            <div class="user-info">
                <span><?php echo htmlspecialchars($username); ?></span>
                <span class="user-role"><?php echo ucfirst(htmlspecialchars($role)); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <!-- Stock Movements Section -->
            <div class="data-section">
                <div class="section-header">
                    <h2 class="section-title">Stock Movements</h2>
                    <?php if ($role === 'admin'): ?>
                    <div>
                        <a href="stock_movements.php?action=add&type=in" class="btn btn-success btn-icon">
                            <i class="fas fa-plus"></i> Stock In
                        </a>
                        <a href="stock_movements.php?action=add&type=out" class="btn btn-danger btn-icon">
                            <i class="fas fa-minus"></i> Stock Out
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($role === 'admin' && isset($_GET['action']) && $_GET['action'] === 'add'): ?>
                <!-- Add Stock Movement Form -->
                <div class="form-section">
                    <h3><?php echo ($movement_type === 'in') ? 'Add Stock In' : 'Add Stock Out'; ?></h3>
                    
                    <form method="post" action="stock_movements.php?action=add">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($movement_type); ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="product_id" class="form-label">Product</label>
                                <select id="product_id" name="product_id" class="form-control" required>
                                    <option value="">Select Product</option>
                                    <?php while ($product = $products->fetch_assoc()): ?>
                                    <option value="<?php echo $product['id']; ?>" <?php echo (isset($selected_product) && $selected_product['id'] == $product['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" id="quantity" name="quantity" class="form-control" min="1" value="1" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Add Stock Movement</button>
                            <a href="stock_movements.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Stock Movements Table -->
                <?php if ($stock_movements->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Notes</th>
                                <th>Created By</th>
                                <?php if ($role === 'admin'): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($movement = $stock_movements->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($movement['date'])); ?></td>
                                <td><?php echo htmlspecialchars($movement['product_name']); ?></td>
                                <td>
                                    <?php if ($movement['type'] === 'in'): ?>
                                    <span class="text-success"><i class="fas fa-arrow-down"></i> In</span>
                                    <?php else: ?>
                                    <span class="text-danger"><i class="fas fa-arrow-up"></i> Out</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $movement['quantity']; ?></td>
                                <td><?php echo htmlspecialchars($movement['notes']); ?></td>
                                <td><?php echo htmlspecialchars($movement['username']); ?></td>
                                <?php if ($role === 'admin'): ?>
                                <td>
                                    <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $movement['id']; ?>)" class="btn btn-sm btn-danger" data-tooltip="Delete Movement">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p>No stock movements found. <?php if ($role === 'admin'): ?>Please add some stock movements.<?php endif; ?></p>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Delete Confirmation Modal -->
    <?php if ($role === 'admin'): ?>
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Delete</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this stock movement?</p>
                <p>This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container footer-content">
            <div class="footer-text">&copy; <?php echo date('Y'); ?> STOBAR - Stock Inventory Management System</div>
            <div class="footer-text">Version 1.0</div>
        </div>
    </footer>
    
    <script src="assets/js/script.js"></script>
    <?php if ($role === 'admin'): ?>
    <script>
        // Delete confirmation function
        function confirmDelete(movementId) {
            document.getElementById('confirmDeleteBtn').href = 'stock_movements.php?action=delete&id=' + movementId;
            
            // Show modal
            const modal = document.getElementById('deleteModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    </script>
    <?php endif; ?>
</body>
</html>