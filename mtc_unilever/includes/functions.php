<?php
/**
 * Helper functions untuk OneBox
 */

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function redirectTo($url) {
    header("Location: $url");
    exit;
}

function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function checkRole($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        redirectTo('../index.html');
    }
}

function hasPermission($user_role, $required_permissions) {
    $permissions = [
        'admin' => ['view_all', 'create', 'edit', 'delete', 'manage_users'],
        'user' => ['view_own', 'create_request'],
        'maintenance' => ['view_requests', 'manage_requests', 'create_orders'],
        'engineering_store' => ['view_orders', 'manage_inventory', 'fulfill_orders']
    ];
    
    if (!isset($permissions[$user_role])) {
        return false;
    }
    
    return array_intersect($required_permissions, $permissions[$user_role]) === $required_permissions;
}

function getDashboardUrl($role) {
    $dashboards = [
        'admin' => '../mtc_unilever/pages/admin_dashboard.php',
        'user' => '../mtc_unilever/pages/user_dashboard.php',
        'maintenance' => '../mtc_unilever/pages/maintenance_dashboard.php', // Sementara redirect ke admin
        'engineering_store' => '../mtc_unilever/pages/admin_dashboard.php' // Sementara redirect ke admin
    ];
    
    return $dashboards[$role] ?? '../pages/user_dashboard.php';
}

function logActivity($pdo, $user_id, $action, $description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, 
            $action, 
            $description, 
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch(PDOException $e) {
        // Log error silently
        error_log('Activity log error: ' . $e->getMessage());
    }
}

function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge badge-warning">Pending</span>',
        'approved' => '<span class="badge badge-info">Approved</span>',
        'in_progress' => '<span class="badge badge-primary">In Progress</span>',
        'completed' => '<span class="badge badge-success">Completed</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        'shipped' => '<span class="badge badge-info">Shipped</span>',
        'delivered' => '<span class="badge badge-success">Delivered</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
}
?>