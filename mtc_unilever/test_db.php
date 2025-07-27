<?php
require_once 'config/database.php';

try {
    $stmt = $pdo->query("SELECT 'Database connected successfully' as message");
    $result = $stmt->fetch();
    echo json_encode(['success' => true, 'message' => $result['message']]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>