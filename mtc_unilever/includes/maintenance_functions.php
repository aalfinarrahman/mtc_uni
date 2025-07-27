<?php
// Maintenance Request Functions

function createMaintenanceRequest($pdo, $data, $user_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_requests (
                requester_name, request_title, request_date, incident_time,
                production_line, machine_name, problematic_unit, shift_number,
                damage_type, problem_description,
                what_happened, when_started, where_location, who_found, which_component, how_occurred,
                priority, additional_notes, user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            sanitizeInput($data['requester_name']),
            sanitizeInput($data['request_title']),
            $data['request_date'],
            $data['incident_time'],
            sanitizeInput($data['production_line']),
            sanitizeInput($data['machine_name']),
            sanitizeInput($data['problematic_unit']),
            $data['shift_number'],
            $data['damage_type'],
            sanitizeInput($data['problem_description']),
            sanitizeInput($data['what_happened']),
            $data['when_started'] ?: null,
            sanitizeInput($data['where_location']),
            sanitizeInput($data['who_found']),
            sanitizeInput($data['which_component']),
            sanitizeInput($data['how_occurred']),
            $data['priority'],
            sanitizeInput($data['additional_notes']),
            $user_id
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Maintenance request berhasil dibuat!'];
        } else {
            return ['success' => false, 'message' => 'Gagal membuat maintenance request.'];
        }
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function getUserMaintenanceRequests($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT mr.*, u.full_name as assigned_name 
            FROM maintenance_requests mr 
            LEFT JOIN users u ON mr.assigned_to = u.id 
            WHERE mr.user_id = ? 
            ORDER BY mr.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

function getMaintenanceRequestById($pdo, $request_id, $user_id = null) {
    try {
        $sql = "
            SELECT mr.*, u.full_name as assigned_name, creator.full_name as creator_name
            FROM maintenance_requests mr 
            LEFT JOIN users u ON mr.assigned_to = u.id 
            LEFT JOIN users creator ON mr.user_id = creator.id
            WHERE mr.id = ?
        ";
        
        $params = [$request_id];
        
        if ($user_id) {
            $sql .= " AND mr.user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return false;
    }
}

function updateMaintenanceRequestStatus($pdo, $request_id, $new_status, $assigned_to = null, $notes = null) {
    try {
        $pdo->beginTransaction();
        
        // Get current status
        $stmt = $pdo->prepare("SELECT status FROM maintenance_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) {
            throw new Exception("Request not found");
        }
        
        // Update request
        $sql = "UPDATE maintenance_requests SET status = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$new_status, $request_id];
        
        if ($assigned_to !== null) {
            $sql .= ", assigned_to = ?, assigned_at = CURRENT_TIMESTAMP";
            array_splice($params, -1, 0, [$assigned_to]);
        }
        
        if ($new_status === 'completed') {
            $sql .= ", completed_at = CURRENT_TIMESTAMP";
        }
        
        $sql .= " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Log status change
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_request_history (request_id, status_from, status_to, changed_by, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $request_id,
            $current['status'],
            $new_status,
            $_SESSION['user_id'],
            $notes
        ]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Status berhasil diupdate'];
    } catch(Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function getMaintenanceStats($pdo, $user_id = null) {
    try {
        $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN priority = 'Critical' THEN 1 ELSE 0 END) as critical
            FROM maintenance_requests";
        
        $params = [];
        if ($user_id) {
            $sql .= " WHERE user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [
            'total' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'critical' => 0
        ];
    }
}
?>