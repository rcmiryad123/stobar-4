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

// Check if user has admin role
if ($role !== 'admin') {
    // Redirect to dashboard
    header('Location: index.php');
    exit;
}

// Get date range for reports
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get report type
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'movement';

// Get report data based on type
$report_data = [];

if ($report_type === 'movement') {
    // Get stock movement report
    $stmt = $conn->prepare('SELECT p.name as product_name, sm.type, SUM(sm.quantity) as total_quantity, 
                           COUNT(sm.id) as movement_count 
                           FROM stock_movements sm 
                           JOIN products p ON sm.product_id = p.id 
                           WHERE DATE(sm.date) BETWEEN DATE(?) AND DATE(?) 
                           GROUP BY p.name, sm.type 
                           ORDER BY p.name, sm.type');
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $report_data = $stmt->get_result();
    $stmt->close();
} else if ($report_type === 'low_stock') {
    // Get low stock report
    $stmt = $conn->prepare('SELECT p.name, cs.current_quantity, p.min_stock, p.unit, 
                           (cs.current_quantity - p.min_stock) as stock_difference 
                           FROM products p 
                           JOIN current_stock cs ON p.id = cs.id 
                           WHERE cs.current_quantity <= p.min_stock * 1.2 
                           ORDER BY stock_difference ASC');
    $stmt->execute();
    $report_data = $stmt->get_result();
    $stmt->close();
} else if ($report_type === 'usage') {
    // Get usage report
    $stmt = $conn->prepare('SELECT p.name, 
                           COALESCE(SUM(CASE WHEN sm.type = "out" AND DATE(sm.date) BETWEEN DATE(?) AND DATE(?) THEN sm.quantity ELSE 0 END), 0) as usage_quantity, 
                           COUNT(CASE WHEN sm.type = "out" AND DATE(sm.date) BETWEEN DATE(?) AND DATE(?) THEN sm.id ELSE NULL END) as usage_count, 
                           COALESCE(SUM(CASE WHEN sm.type = "out" AND DATE(sm.date) BETWEEN DATE(?) AND DATE(?) THEN sm.quantity ELSE 0 END) / 
                           DATEDIFF(DATE(?), DATE(?)) * 30, 0) as monthly_average 
                           FROM products p 
                           LEFT JOIN stock_movements sm ON p.id = sm.product_id 
                           GROUP BY p.name 
                           ORDER BY usage_quantity DESC');
    $stmt->bind_param('ssssssss', $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $end_date, $start_date);
    $stmt->execute();
    $report_data = $stmt->get_result();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Stock Inventory System</title>
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
                    <a href="index.php" class="nav-link">Dashboard</a>
                </div>
                <div class="nav-item">
                    <a href="products.php" class="nav-link">Products</a>
                </div>
                <div class="nav-item">
                    <a href="stock_movements.php" class="nav-link">Stock Movements</a>
                </div>
                <div class="nav-item">
                    <a href="reports.php" class="nav-link active">Reports</a>
                </div>
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
            <!-- Reports Section -->
            <div class="data-section">
                <div class="section-header">
                    <h2 class="section-title">Reports</h2>
                </div>
                
                <!-- Report Filters -->
                <div class="form-section">
                    <form method="get" action="reports.php" class="mb-lg">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select id="report_type" name="report_type" class="form-control">
                                    <option value="movement" <?php echo ($report_type === 'movement') ? 'selected' : ''; ?>>Stock Movement Report</option>
                                    <option value="low_stock" <?php echo ($report_type === 'low_stock') ? 'selected' : ''; ?>>Low Stock Report</option>
                                    <option value="usage" <?php echo ($report_type === 'usage') ? 'selected' : ''; ?>>Usage Report</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                            </div>
                            
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-primary">Generate Report</button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Report Results -->
                <?php if ($report_type === 'movement' && $report_data->num_rows > 0): ?>
                <div class="table-responsive">
                    <h3 class="mb-md">Stock Movement Report (<?php echo $start_date; ?> to <?php echo $end_date; ?>)</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Movement Type</th>
                                <th>Total Quantity</th>
                                <th>Movement Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $report_data->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td>
                                    <?php if ($row['type'] === 'in'): ?>
                                    <span class="text-success"><i class="fas fa-arrow-down"></i> In</span>
                                    <?php else: ?>
                                    <span class="text-danger"><i class="fas fa-arrow-up"></i> Out</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['total_quantity']; ?></td>
                                <td><?php echo $row['movement_count']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php elseif ($report_type === 'low_stock' && $report_data->num_rows > 0): ?>
                <div class="table-responsive">
                    <h3 class="mb-md">Low Stock Report</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current Stock</th>
                                <th>Minimum Stock</th>
                                <th>Unit</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $report_data->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo $row['current_quantity']; ?></td>
                                <td><?php echo $row['min_stock']; ?></td>
                                <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                <td>
                                    <?php 
                                    $status_class = 'status-medium';
                                    $status_text = 'Low';
                                    
                                    if ($row['current_quantity'] <= 0) {
                                        $status_class = 'status-low';
                                        $status_text = 'Out of Stock';
                                    } else if ($row['current_quantity'] <= $row['min_stock']) {
                                        $status_class = 'status-low';
                                        $status_text = 'Critical';
                                    }
                                    ?>
                                    <span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php elseif ($report_type === 'usage' && $report_data->num_rows > 0): ?>
                <div class="table-responsive">
                    <h3 class="mb-md">Usage Report (<?php echo $start_date; ?> to <?php echo $end_date; ?>)</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Total Usage</th>
                                <th>Usage Count</th>
                                <th>Monthly Average</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $report_data->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo $row['usage_quantity']; ?></td>
                                <td><?php echo $row['usage_count']; ?></td>
                                <td><?php echo round($row['monthly_average'], 1); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="chart-container">
                    <canvas id="usageChart"></canvas>
                </div>
                
                <script>
                    // Initialize usage chart with PHP data
                    document.addEventListener('DOMContentLoaded', function() {
                        const ctx = document.getElementById('usageChart').getContext('2d');
                        
                        // Prepare data for chart
                        const labels = [];
                        const data = [];
                        
                        <?php 
                        $report_data->data_seek(0);
                        while ($row = $report_data->fetch_assoc()): 
                        ?>
                            labels.push('<?php echo addslashes($row["name"]); ?>');
                            data.push(<?php echo $row["monthly_average"]; ?>);
                        <?php endwhile; ?>
                        
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Monthly Average Usage',
                                    data: data,
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
                <?php else: ?>
                <div class="alert alert-info">
                    <p>No data available for the selected report type and date range.</p>
                </div>
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