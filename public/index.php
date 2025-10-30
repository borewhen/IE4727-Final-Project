<?php
require_once 'check_session.php';

// Check if user is logged in
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Menswear Store</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .home-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
        }
        .user-info {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        .btn-link {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-link:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="home-container">
        <h1>Welcome to Menswear Store</h1>
        
        <?php if ($user): ?>
            <div class="user-info">
                <h2>Hello, <?php echo htmlspecialchars($user['name']); ?>! 👋</h2>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Account Type:</strong> <?php echo $user['is_admin'] ? '👑 Administrator' : '🛍️ Customer'; ?></p>
                <a href="logout.php" class="logout-btn">Logout</a>
                <?php if ($user['is_admin']): ?>
                    <a href="admin_dashboard.php" class="btn-link">Admin Dashboard</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="user-info">
                <p>You are browsing as a guest.</p>
                <a href="login.php" class="btn-link">Login</a>
                <a href="register.php" class="btn-link" style="background: #28a745;">Register</a>
            </div>
        <?php endif; ?>
        
        <h2>Featured Products</h2>
        <p>Product catalog coming soon...</p>
    </div>
</body>
</html>