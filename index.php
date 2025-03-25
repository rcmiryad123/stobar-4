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

// Get total products count
$stmt = $conn->prepare('SELECT COUNT(*) as total FROM products');
$stmt->execute();
$result = $stmt->get_result();
$total_products = $result->fetch_assoc()['total'];
$stmt->close();

// Get low stock products count
$stmt = $conn->prepare('SELECT COUNT(*) as low_stock FROM current_stock WHERE current_quantity <= min_stock');
$stmt->execute();
$result = $stmt->get_result();
$low_stock = $result->fetch_assoc()['low_stock'];
$stmt->close();

// Get total stock value (just a placeholder for this example)
$stmt = $conn->prepare('SELECT SUM(current_quantity) as total_items FROM current_stock');
$stmt->execute();
$result = $stmt->get_result();
$total_items = $result->fetch_assoc()['total_items'];
$stmt->close();

// Get recent stock movements
$stmt = $conn->prepare('SELECT sm.id, p.name as product_name, sm.type, sm.quantity, sm.date, sm.notes, u.username 
                       FROM stock_movements sm 
                       JOIN products p ON sm.product_id = p.id 
                       JOIN users u ON sm.created_by = u.id 
                       ORDER BY sm.date DESC LIMIT 5');
$stmt->execute();
$recent_movements = $stmt->get_result();
$stmt->close();

// Get low stock products
$stmt = $conn->prepare('SELECT cs.id, cs.name, cs.current_quantity, cs.min_stock, cs.unit 
                       FROM current_stock cs 
                       WHERE cs.current_quantity <= cs.min_stock 
                       ORDER BY (cs.min_stock - cs.current_quantity) DESC');
$stmt->execute();
$low_stock_products = $stmt->get_result();
$stmt->close();

// Get usage data for chart (average monthly usage)
$stmt = $conn->prepare('SELECT p.name, 
                        COALESCE(AVG(sm.quantity), 0) as avg_usage 
                        FROM products p 
                        LEFT JOIN stock_movements sm ON p.id = sm.product_id AND sm.type = "out" 
                        GROUP BY p.id 
                        ORDER BY avg_usage DESC 
                        LIMIT 5');
$stmt->execute();
$usage_data = $stmt->get_result();
$stmt->close();

// Prepare data for charts
$chart_labels = [];
$chart_data = [];

while ($row = $usage_data->fetch_assoc()) {
    $chart_labels[] = $row['name'];
    $chart_data[] = round($row['avg_usage'], 1);
}

// Get prediction data (days until depletion)
$stmt = $conn->prepare('SELECT p.id, p.name, cs.current_quantity, 
                        COALESCE(AVG(CASE WHEN sm.type = "out" THEN sm.quantity ELSE 0 END), 0.1) as avg_daily_usage 
                        FROM products p 
                        JOIN current_stock cs ON p.id = cs.id 
                        LEFT JOIN stock_movements sm ON p.id = sm.product_id 
                        GROUP BY p.id 
                        HAVING cs.current_quantity > 0 
                        ORDER BY (cs.current_quantity / avg_daily_usage) ASC 
                        LIMIT 5');
$stmt->execute();
$prediction_result = $stmt->get_result();
$stmt->close();

// Prepare prediction data for chart
$prediction_labels = [];
$prediction_data = [];
$prediction_colors = [];

while ($row = $prediction_result->fetch_assoc()) {
    $days_left = $row['avg_daily_usage'] > 0 ? round($row['current_quantity'] / $row['avg_daily_usage']) : 9999;
    $prediction_labels[] = $row['name'];
    $prediction_data[] = $days_left;
    
    // Set color based on days left
    if ($days_left <= 7) {
        $prediction_colors[] = 'rgba(220, 53, 69, 0.7)'; // Red for critical
    } else if ($days_left <= 14) {
        $prediction_colors[] = 'rgba(255, 193, 7, 0.7)'; // Yellow for warning
    } else {
        $prediction_colors[] = 'rgba(40, 167, 69, 0.7)'; // Green for good
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Stock Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a href="index.php" class="nav-link active">Dashboard</a>
                </div>
                <div class="nav-item">
                    <a href="products.php" class="nav-link">Products</a>
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
            <!-- Dashboard Stats -->
            <div class="dashboard">
                <div class="stat-card">
                    <h3 class="stat-title">Total Products</h3>
                    <div class="stat-value"><?php echo $total_products; ?></div>
                    <div class="stat-description">Different products in inventory</div>
                </div>
                
                <div class="stat-card">
                    <h3 class="stat-title">Low Stock Items</h3>
                    <div class="stat-value"><?php echo $low_stock; ?></div>
                    <div class="stat-description">Products below minimum stock level</div>
                </div>
                
                <div class="stat-card">
                    <h3 class="stat-title">Total Items</h3>
                    <div class="stat-value"><?php echo $total_items; ?></div>
                    <div class="stat-description">Total items in inventory</div>
                </div>
            </div>
            
            <!-- Low Stock Products -->
            <div class="data-section">
                <div class="section-header">
                    <h2 class="section-title">Low Stock Products</h2>
                    <?php if ($role === 'admin'): ?>
                    <a href="stock_movements.php?action=add&type=in" class="btn btn-primary btn-icon">
                        <i class="fas fa-plus"></i> Add Stock
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($low_stock_products->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current Stock</th>
                                <th>Minimum Stock</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <?php if ($role === 'admin'): ?>
                                <th>Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $low_stock_products->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo $product['current_quantity']; ?></td>
                                <td><?php echo $product['min_stock']; ?></td>
                                <td><?php echo htmlspecialchars($product['unit']); ?></td>
                                <td>
                                    <?php 
                                    $status_class = 'status-low';
                                    $status_text = 'Critical';
                                    
                                    if ($product['current_quantity'] > 0) {
                                        $status_class = 'status-medium';
                                        $status_text = 'Low';
                                    }
                                    ?>
                                    <span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <?php if ($role === 'admin'): ?>
                                <td>
                                    <a href="stock_movements.php?action=add&type=in&product_id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">Add Stock</a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p>No low stock products found.</p>
                <?php endif; ?>
            </div>
            
            <!-- Charts Section -->
            <div class="data-section">
                <div class="section-header">
                    <h2 class="section-title">Usage Statistics</h2>
                </div>
                
                <div class="chart-container">
                    <canvas id="usageChart"></canvas>
                </div>
                
                <script>
                    // Initialize usage chart with PHP data
                    document.addEventListener('DOMContentLoaded', function() {
                        const ctx = document.getElementById('usageChart').getContext('2d');
                        
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode($chart_labels); ?>,
                                datasets: [{
                                    label: 'Average Monthly Usage',
                                    data: <?php echo json_encode($chart_data); ?>,
                                    backgroundColor: 'rgba(74, 111, 165, 0.7)',
                                    borderColor: 'rgba(74, 111, 165, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Quantity'
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Product'
                                        }
                                    }
                                }
                            }
                        });
                    });
                </script>
            </div>
            
            <!-- Prediction Chart -->
            <div class="data-section">
                <div class="section-header">
                    <h2 class="section-title">Stock Depletion Prediction</h2>
                </div>
                
                <div class="chart-container">
                    <canvas id="predictionChart"></canvas>
                </div>
                
                <script>
                    // Initialize prediction chart with PHP data
                    document.addEventListener('DOMContentLoaded', function() {
                        const ctx = document.getElementById('predictionChart').getContext('2d');
                        
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode($prediction_labels); ?>,
                                datasets: [{
                                    label: 'Days Until Depletion',
                                    data: <?php echo json_encode($prediction_data); ?>,
                                    backgroundColor: <?php echo json_encode($prediction_colors); ?>,
                                    borderColor: <?php echo json_encode($prediction_colors); ?>,
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                indexAxis: 'y',
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Days'
                                        }
                                    },
                                    y: {
                                        title: {
                                            display: true,
                                            text: 'Product'
                                        }
                                    }
                                }
                            }
                        });
                    });
                </script>
            </div>
            
            <!-- Recent Stock Movements -->
            <div class="data-section">
                <div class="section-header">
                    <h2 class="section-title">Recent Stock Movements</h2>
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
                
                <?php if ($recent_movements->num_rows > 0): ?>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($movement = $recent_movements->fetch_assoc()): ?>
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
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p>No recent stock movements found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container footer-content">
            <div class="footer-text">&copy; <?php echo date('Y'); ?> STOBAR - Stock Inventory Management System</div>
            <div class="footer-text">Version 1.0</div>
        </div>
    </footer>
    
    <script src="assets/js/script.js"></script>
</body>
</html>