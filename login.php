<?php
// Start session
session_start();

// Include database connection
require_once 'config/database.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard
    header('Location: index.php');
    exit;
}

// Initialize variables
$username = '';
$error = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validate form data
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Get database connection
        $conn = getDB();
        
        // Prepare SQL statement
        $stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Check if user exists
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password is correct, set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect to dashboard
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
        
        // Close statement
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Stock Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        
        .login-container {
            background-color: white;
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-md);
            width: 100%;
            max-width: 400px;
            padding: var(--spacing-xl);
            animation: fadeIn var(--transition-normal);
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: var(--spacing-lg);
        }
        
        .login-logo h1 {
            color: var(--primary);
            font-size: var(--font-size-xxl);
            margin-bottom: var(--spacing-xs);
        }
        
        .login-logo p {
            color: var(--gray);
        }
        
        .login-form {
            margin-top: var(--spacing-lg);
        }
        
        .login-footer {
            text-align: center;
            margin-top: var(--spacing-lg);
            color: var(--gray);
            font-size: var(--font-size-sm);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <h1>STOBAR</h1>
            <p>Stock Inventory Management System</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form class="login-form" method="post" action="">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </div>
        </form>
        
        <div class="login-footer">
            <p>Default credentials:</p>
            <p>Admin: admin-rfn / 123456</p>
            <p>Guest: guest-rfn / 123456</p>
        </div>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>