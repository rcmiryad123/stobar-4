<?php
// Start session
session_start();

// Include database connection and Product model
require_once 'config/database.php';
require_once 'models/Product.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get database connection and initialize Product model
$conn = getDB();
$productModel = new Product($conn);

// Get current user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Initialize variables
$message = '';
$message_type = '';

// Handle product actions (add, edit, delete)
if ($role === 'admin' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Handle delete action
    if ($action === 'delete' && isset($_GET['id'])) {
        $product_id = (int)$_GET['id'];
        
        if ($productModel->delete($product_id)) {
            $message = 'Product deleted successfully';
            $message_type = 'success';
        } else {
            $message = 'Error deleting product';
            $message_type = 'danger';
        }
    }
    
    // Handle add/edit form submission
    if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($action === 'add' || $action === 'edit')) {
        // Get form data
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $unit = trim($_POST['unit']);
        $min_stock = (int)$_POST['min_stock'];
        
        // Validate input
        $errors = $productModel->validateInput($name, $unit, $min_stock);
        
        if (empty($errors)) {
            if ($action === 'add') {
                if ($productModel->add($name, $description, $unit, $min_stock)) {
                    $message = 'Product added successfully';
                    $message_type = 'success';
                    header('Location: products.php?message=' . urlencode($message) . '&type=' . $message_type);
                    exit;
                } else {
                    $message = 'Error adding product';
                    $message_type = 'danger';
                }
            } else if ($action === 'edit' && isset($_GET['id'])) {
                $product_id = (int)$_GET['id'];
                
                if ($productModel->update($product_id, $name, $description, $unit, $min_stock)) {
                    $message = 'Product updated successfully';
                    $message_type = 'success';
                    header('Location: products.php?message=' . urlencode($message) . '&type=' . $message_type);
                    exit;
                } else {
                    $message = 'Error updating product';
                    $message_type = 'danger';
                }
            }
        } else {
            $message = implode(', ', $errors);
            $message_type = 'danger';
        }
    }
}

// Check for message in URL parameters
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'];
}

// Get product data for edit form
$edit_product = null;
if ($role === 'admin' && isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    $edit_product = $productModel->getById($product_id);
}

// Get all products
$products = $productModel->getAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Stock Inventory System</title>
    <link href="vendor/font-products.css" rel="stylesheet">
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
                    <a href="products.php" class="nav-link active">Products</a>
                </div>
                <div class="nav-item">
                    <a href="stock_movements.php" class="nav-link">Stock Movements</a>
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
            
            <!-- Products Section -->
            <div class="data-section">
                <div class="section-header">
                    <h2 class="section-title">Products</h2>
                    <?php if ($role === 'admin'): ?>
                    <a href="products.php?action=add" class="btn btn-primary btn-icon">
                        <i class="fas fa-plus"></i> Add Product
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($role === 'admin' && isset($_GET['action']) && ($_GET['action'] === 'add' || $_GET['action'] === 'edit')): ?>
                <!-- Add/Edit Product Form -->
                <div class="form-section">
                    <h3><?php echo ($_GET['action'] === 'add') ? 'Add New Product' : 'Edit Product'; ?></h3>
                    
                    <form method="post" action="products.php?action=<?php echo $_GET['action']; ?><?php echo ($_GET['action'] === 'edit') ? '&id=' . $edit_product['id'] : ''; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name" class="form-label">Product Name</label>
                                <input type="text" id="name" name="name" class="form-control" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="unit" class="form-label">Unit</label>
                                <input type="text" id="unit" name="unit" class="form-control" value="<?php echo isset($edit_product) ? htmlspecialchars($edit_product['unit']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="min_stock" class="form-label">Minimum Stock</label>
                                <input type="number" id="min_stock" name="min_stock" class="form-control" value="<?php echo isset($edit_product) ? $edit_product['min_stock'] : '0'; ?>" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?php echo isset($edit_product) ? htmlspecialchars($edit_product['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary"><?php echo ($_GET['action'] === 'add') ? 'Add Product' : 'Update Product'; ?></button>
                            <a href="products.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Products Table -->
                <?php if ($products->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Unit</th>
                                <th>Current Stock</th>
                                <th>Min Stock</th>
                                <th>Status</th>
                                <?php if ($role === 'admin'): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $products->fetch_assoc()): ?>
                            <?php $status = $productModel->getStockStatus($product['current_quantity'], $product['min_stock']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['description']); ?></td>
                                <td><?php echo htmlspecialchars($product['unit']); ?></td>
                                <td><?php echo $product['current_quantity']; ?></td>
                                <td><?php echo $product['min_stock']; ?></td>
                                <td>
                                    <span class="status <?php echo $status['class']; ?>"><?php echo $status['text']; ?></span>
                                </td>
                                <?php if ($role === 'admin'): ?>
                                <td>
                                    <a href="products.php?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary" data-tooltip="Edit Product">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="stock_movements.php?action=add&type=in&product_id=<?php echo $product['id']; ?>" class="btn btn-sm btn-success" data-tooltip="Add Stock">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                    <a href="stock_movements.php?action=add&type=out&product_id=<?php echo $product['id']; ?>" class="btn btn-sm btn-warning" data-tooltip="Remove Stock">
                                        <i class="fas fa-minus"></i>
                                    </a>
                                    <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>')" class="btn btn-sm btn-danger" data-tooltip="Delete Product">
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
                <p>No products found. <?php if ($role === 'admin'): ?>Please add some products.<?php endif; ?></p>
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
                <p>Are you sure you want to delete the product <strong id="deleteProductName"></strong>?</p>
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
        function confirmDelete(productId, productName) {
            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('confirmDeleteBtn').href = 'products.php?action=delete&id=' + productId;
            
            // Show modal
            const modal = document.getElementById('deleteModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    </script>
    <?php endif; ?>
</body>
</html>