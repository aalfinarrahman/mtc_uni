<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/maintenance_functions.php';

checkRole('admin');

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['status'];
    $assigned_to = $_POST['assigned_to'] ?? null;
    
    try {
        $stmt = $pdo->prepare("UPDATE maintenance_requests SET status = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $assigned_to, $request_id]);
        
        // Log status change
        $stmt = $pdo->prepare("INSERT INTO maintenance_request_history (request_id, status_to, changed_by, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$request_id, $new_status, $_SESSION['user_id'], 'Status updated by admin']);
        
        $success_message = 'Status berhasil diupdate!';
    } catch (PDOException $e) {
        $error_message = 'Gagal mengupdate status: ' . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$line_filter = $_GET['line'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "mr.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $where_conditions[] = "mr.priority = ?";
    $params[] = $priority_filter;
}

if ($line_filter) {
    $where_conditions[] = "mr.production_line = ?";
    $params[] = $line_filter;
}

if ($search) {
    $where_conditions[] = "(mr.request_title LIKE ? OR mr.machine_name LIKE ? OR mr.requester_name LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get maintenance requests
try {
    $sql = "
        SELECT mr.*, u.full_name as user_name, a.full_name as assigned_name
        FROM maintenance_requests mr
        LEFT JOIN users u ON mr.user_id = u.id
        LEFT JOIN users a ON mr.assigned_to = a.id
        $where_clause
        ORDER BY mr.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $maintenance_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $maintenance_requests = [];
    $error_message = 'Error fetching data: ' . $e->getMessage();
}

// Get statistics
try {
    $stats = [];
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM maintenance_requests");
    $stats['total'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM maintenance_requests WHERE status = 'pending'");
    $stats['pending'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as in_progress FROM maintenance_requests WHERE status IN ('assigned', 'in_progress')");
    $stats['in_progress'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as completed FROM maintenance_requests WHERE status = 'completed'");
    $stats['completed'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
}

// Get users for assignment
try {
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'technician') AND is_active = 1 ORDER BY full_name");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $technicians = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneBox - Maintenance Requests</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/maintenance.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="sidebar">
            <div class="logo">
                <i class="fas fa-cube"></i>
                <h2>OneBox Admin</h2>
            </div>
            <ul class="nav-menu">
                <li>
                    <a href="admin_dashboard.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="view_maintenance_requests.php" class="nav-link active">
                        <i class="fas fa-tools"></i>
                        <span>Maintenance Requests</span>
                    </a>
                </li>
                <li>
                    <a href="manage_users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Manage Users</span>
                    </a>
                </li>
                <li>
                    <a href="../auth/logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Maintenance Requests</h1>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <span class="user-role">Admin</span>
                    </div>
                </div>
            </header>
            
            <div class="content-section">
                <!-- Alert Messages -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Total Requests</p>
                            <span class="stat-trend">All time</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['pending']; ?></h3>
                            <p>Pending</p>
                            <span class="stat-trend">Awaiting assignment</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon progress">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['in_progress']; ?></h3>
                            <p>In Progress</p>
                            <span class="stat-trend">Being worked on</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon completed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['completed']; ?></h3>
                            <p>Completed</p>
                            <span class="stat-trend">Successfully done</span>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="requests-filters">
                    <form method="GET" action="" class="filter-form">
                        <div class="filter-group">
                            <label for="status">Filter by Status:</label>
                            <select name="status" id="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="priority">Filter by Priority:</label>
                            <select name="priority" id="priority">
                                <option value="">All Priority</option>
                                <option value="Low" <?php echo $priority_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
                                <option value="Medium" <?php echo $priority_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="High" <?php echo $priority_filter === 'High' ? 'selected' : ''; ?>>High</option>
                                <option value="Critical" <?php echo $priority_filter === 'Critical' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search">Search:</label>
                            <input type="text" name="search" id="search" placeholder="Search by title, machine, or requester..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="view_maintenance_requests.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Maintenance Requests Table -->
                <div class="table-container">
                    <?php if (empty($maintenance_requests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tools"></i>
                            <h3>No Maintenance Requests Found</h3>
                            <p>No maintenance requests match your current filters.</p>
                        </div>
                    <?php else: ?>
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Requester</th>
                                    <th>Machine</th>
                                    <th>Line</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenance_requests as $request): ?>
                                <tr>
                                    <td>#<?php echo $request['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($request['request_title']); ?></strong>
                                        <br><small><?php echo htmlspecialchars($request['damage_type']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['machine_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['production_line']); ?></td>
                                    <td>
                                        <span class="priority-badge priority-<?php echo strtolower($request['priority']); ?>">
                                            <?php echo $request['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo getStatusBadge($request['status']); ?>
                                    </td>
                                    <td>
                                        <?php echo $request['assigned_name'] ? htmlspecialchars($request['assigned_name']) : '-'; ?>
                                    </td>
                                    <td><?php echo formatDate($request['created_at']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" onclick="viewRequest(<?php echo $request['id']; ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon" onclick="updateStatus(<?php echo $request['id']; ?>)" title="Update Status">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal for Status Update -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Status</h3>
                <span class="close" onclick="closeModal('statusModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="modal_request_id" name="request_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal_status">Status:</label>
                        <select name="status" id="modal_status" required>
                            <option value="pending">Pending</option>
                            <option value="assigned">Assigned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modal_assigned_to">Assign To:</label>
                        <select name="assigned_to" id="modal_assigned_to">
                            <option value="">Select Technician</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                    <button type="submit" name="update_status" class="btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal for View Details -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Request Details</h3>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded via JavaScript -->
            </div>
        </div>
    </div>
    
    <script>
        function updateStatus(requestId) {
            document.getElementById('modal_request_id').value = requestId;
            document.getElementById('statusModal').style.display = 'block';
        }
        
        function viewRequest(requestId) {
            // Load request details via AJAX
            fetch(`get_request_details.php?id=${requestId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('viewModalBody').innerHTML = data;
                    document.getElementById('viewModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading request details');
                });
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const statusModal = document.getElementById('statusModal');
            const viewModal = document.getElementById('viewModal');
            if (event.target === statusModal) {
                statusModal.style.display = 'none';
            }
            if (event.target === viewModal) {
                viewModal.style.display = 'none';
            }
        }
        
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>