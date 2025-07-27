<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        jsonResponse(false, 'Email dan password harus diisi');
    }
    
    if (!isValidEmail($email)) {
        jsonResponse(false, 'Format email tidak valid');
    }
    
    try {
        // Cek apakah tabel roles ada
        $check_tables = $pdo->query("SHOW TABLES LIKE 'roles'");
        if ($check_tables->rowCount() == 0) {
            jsonResponse(false, 'Database belum disetup dengan benar. Silakan import onebox.sql terlebih dahulu.');
        }
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.password, u.full_name, u.is_active, 
                   r.name as role_name, r.description as role_description
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ? AND u.is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_description'] = $user['role_description'];
            
            // Log activity (dengan error handling)
            try {
                logActivity($pdo, $user['id'], 'login', 'User logged in successfully');
            } catch(Exception $e) {
                // Jika gagal log, tetap lanjutkan login
                error_log('Log activity failed: ' . $e->getMessage());
            }
            
            // Redirect berdasarkan role
            $dashboard_url = getDashboardUrl($user['role_name']);
            
            jsonResponse(true, 'Login berhasil', ['redirect' => $dashboard_url]);
        } else {
            jsonResponse(false, 'Email atau password salah');
        }
    } catch(PDOException $e) {
        jsonResponse(false, 'Terjadi kesalahan koneksi database: ' . $e->getMessage());
    }
} else {
    jsonResponse(false, 'Method tidak diizinkan');
}
?>