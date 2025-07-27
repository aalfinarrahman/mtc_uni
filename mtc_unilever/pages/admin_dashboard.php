<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

checkRole('admin');

// Get statistics
$stats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
    $stats['total_users'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_requests FROM user_requests");
    $stats['total_requests'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_orders FROM maintenance_orders");
    $stats['total_orders'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_items FROM items");
    $stats['total_items'] = $stmt->fetchColumn();
} catch(PDOException $e) {
    $stats = ['total_users' => 0, 'total_requests' => 0, 'total_orders' => 0, 'total_items' => 0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneBox - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="sidebar">
            <div class="logo">
                <h2>OneBox Admin</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="#" class="active">Dashboard</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="view_requests.php">All Requests</a></li>
                <li><a href="view_orders.php">All Orders</a></li>
                <li><a href="manage_items.php">Manage Items</a></li>
                <li><a href="activity_logs.php">Activity Logs</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
        
        <main class="main-content">
            <header class="top-bar">
                <h1>Admin Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                </div>
            </header>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <p class="stat-number"><?php echo $stats['total_users']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Requests</h3>
                    <p class="stat-number"><?php echo $stats['total_requests']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <p class="stat-number"><?php echo $stats['total_orders']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Items</h3>
                    <p class="stat-number"><?php echo $stats['total_items']; ?></p>
                </div>
            </div>
            
            <div class="content-section">
                <h2>System Overview</h2>
                <p>Selamat datang di OneBox Admin Dashboard. Anda memiliki akses penuh ke semua fitur sistem.</p>
            </div>
        </main>
    </div>
</body>
</html>