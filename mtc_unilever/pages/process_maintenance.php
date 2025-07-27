<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/maintenance_functions.php';

checkRole('user');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create_request':
            $result = createMaintenanceRequest($pdo, $_POST, $_SESSION['user_id']);
            echo json_encode($result);
            break;
            
        case 'get_requests':
            $requests = getUserMaintenanceRequests($pdo, $_SESSION['user_id']);
            echo json_encode(['success' => true, 'data' => $requests]);
            break;
            
        case 'get_request':
            if (isset($_POST['request_id'])) {
                $request = getMaintenanceRequestById($pdo, $_POST['request_id'], $_SESSION['user_id']);
                if ($request) {
                    echo json_encode(['success' => true, 'data' => $request]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Request not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Request ID required']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Action required']);
}
?>