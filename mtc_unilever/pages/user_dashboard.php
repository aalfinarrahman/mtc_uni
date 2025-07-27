<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/maintenance_functions.php';

checkRole('user');

// Initialize variables
$success_message = '';
$error_message = '';
$requests = [];
$maintenance_requests = [];
$monthly_stats = [];

// Handle maintenance request form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_maintenance'])) {
    try {
        $result = createMaintenanceRequest($pdo, $_POST, $_SESSION['user_id']);
        if ($result['success']) {
            $success_message = $result['message'];
            // Clear POST data to prevent resubmission
            header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
            exit;
        } else {
            $error_message = $result['message'];
        }
    } catch (Exception $e) {
        $error_message = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        error_log('Maintenance request error: ' . $e->getMessage());
    }
}

// Handle success message from redirect
if (isset($_GET['success'])) {
    $success_message = 'Maintenance request berhasil dibuat!';
}

// Get user's requests with error handling
try {
    $stmt = $pdo->prepare("
        SELECT ur.*, u.full_name as assigned_name 
        FROM user_requests ur 
        LEFT JOIN users u ON ur.assigned_to = u.id 
        WHERE ur.user_id = ? 
        ORDER BY ur.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log('Error fetching user requests: ' . $e->getMessage());
    $requests = [];
}

// Get user's maintenance requests
try {
    $maintenance_requests = getUserMaintenanceRequests($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    error_log('Error fetching maintenance requests: ' . $e->getMessage());
    $maintenance_requests = [];
}

// Get monthly request statistics for chart
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_requests,
            SUM(CASE WHEN source_table = 'user_requests' THEN 1 ELSE 0 END) as regular_requests,
            SUM(CASE WHEN source_table = 'maintenance_requests' THEN 1 ELSE 0 END) as maintenance_requests
        FROM (
            SELECT created_at, 'user_requests' as source_table FROM user_requests WHERE user_id = ?
            UNION ALL
            SELECT created_at, 'maintenance_requests' as source_table FROM maintenance_requests WHERE user_id = ?
        ) as all_requests
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log('Error fetching monthly stats: ' . $e->getMessage());
    $monthly_stats = [];
}

// Prepare chart data
$chart_labels = [];
$chart_data = [];
$chart_regular = [];
$chart_maintenance = [];

foreach ($monthly_stats as $stat) {
    $chart_labels[] = date('M Y', strtotime($stat['month'] . '-01'));
    $chart_data[] = (int)$stat['total_requests'];
    $chart_regular[] = (int)$stat['regular_requests'];
    $chart_maintenance[] = (int)$stat['maintenance_requests'];
}

// Calculate statistics
$total_requests = count($requests);
$total_maintenance = count($maintenance_requests);
$all_requests = array_merge($requests, $maintenance_requests);
$pending_count = count(array_filter($all_requests, function($r) { return $r['status'] === 'pending'; }));
$completed_count = count(array_filter($all_requests, function($r) { return $r['status'] === 'completed'; }));
$in_progress_count = count(array_filter($all_requests, function($r) { return in_array($r['status'], ['assigned', 'in_progress']); }));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneBox - User Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/maintenance.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <nav class="sidebar">
            <div class="logo">
                <i class="fas fa-cube"></i>
                <h2>OneBox User</h2>
            </div>
            <ul class="nav-menu">
                <li>
                    <a href="#" class="nav-link" onclick="showSection('dashboard'); return false;">
                        <i class="fas fa-chart-bar"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link" onclick="showSection('create-request'); return false;">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create Request</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link" onclick="showSection('my-requests'); return false;">
                        <i class="fas fa-list-alt"></i>
                        <span>History Request</span>
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
                    <h1 id="page-title">Dashboard</h1>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <span class="user-role">User</span>
                    </div>
                </div>
            </header>
            
            <div class="content-section">
                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success" id="success-alert">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                        <button class="alert-close" onclick="closeAlert('success-alert')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-error" id="error-alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                        <button class="alert-close" onclick="closeAlert('error-alert')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="section-content active">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon regular">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $total_requests; ?></h3>
                                <p>Regular Requests</p>
                                <span class="stat-trend">Total submitted</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon maintenance">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $total_maintenance; ?></h3>
                                <p>Maintenance Requests</p>
                                <span class="stat-trend">Total submitted</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon pending">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $pending_count; ?></h3>
                                <p>Pending</p>
                                <span class="stat-trend">Awaiting action</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon progress">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $in_progress_count; ?></h3>
                                <p>In Progress</p>
                                <span class="stat-trend">Being processed</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon completed">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $completed_count; ?></h3>
                                <p>Completed</p>
                                <span class="stat-trend">Successfully done</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3><i class="fas fa-chart-line"></i> Monthly Request Statistics</h3>
                                <p>Grafik request setiap bulannya (12 bulan terakhir)</p>
                                <div class="chart-controls">
                                    <button class="chart-toggle active" onclick="toggleChartType('line')">
                                        <i class="fas fa-chart-line"></i> Line
                                    </button>
                                    <button class="chart-toggle" onclick="toggleChartType('bar')">
                                        <i class="fas fa-chart-bar"></i> Bar
                                    </button>
                                </div>
                            </div>
                            <div class="chart-content">
                                <canvas id="monthlyChart" width="400" height="200"></canvas>
                            </div>
                            <div class="chart-legend">
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #4f46e5;"></span>
                                    <span>Regular Requests</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #f59e0b;"></span>
                                    <span>Maintenance Requests</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Create Request Section -->
                <div id="create-request-section" class="section-content">
                    <div class="form-container">
                        <div class="form-header">
                            <h2><i class="fas fa-plus-circle"></i> Create Maintenance Request</h2>
                            <p>Buat permintaan maintenance untuk equipment yang bermasalah</p>
                        </div>
                        
                        <form method="POST" action="" id="maintenance-form" novalidate>
                            <!-- Section 1: Request Identity -->
                            <div class="form-section" data-section="1">
                                <h3><i class="fas fa-user"></i> Identitas Permintaan</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="requester_name" class="required">Nama Peminta</label>
                                        <input type="text" id="requester_name" name="requester_name" 
                                               value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" 
                                               required readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="request_title" class="required">Judul Permintaan</label>
                                        <input type="text" id="request_title" name="request_title" 
                                               placeholder="Contoh: Kerusakan Motor Conveyor Line 1" required>
                                        <div class="field-hint">Berikan judul yang jelas dan spesifik</div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="request_date" class="required">Tanggal Pengajuan</label>
                                        <input type="date" id="request_date" name="request_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="incident_time" class="required">Waktu Kejadian</label>
                                        <input type="time" id="incident_time" name="incident_time" required>
                                        <div class="field-hint">Waktu saat masalah pertama kali terjadi</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 2: Production Details -->
                            <div class="form-section" data-section="2">
                                <h3><i class="fas fa-industry"></i> Detail Produksi</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="production_line" class="required">Line Produksi</label>
                                        <select id="production_line" name="production_line" required>
                                            <option value="">Pilih Line Produksi</option>
                                            <option value="Line 1">Line 1</option>
                                            <option value="Line 2">Line 2</option>
                                            <option value="Line 3">Line 3</option>
                                            <option value="Line 4">Line 4</option>
                                            <option value="Line 5">Line 5</option>
                                            <option value="Utility">Utility</option>
                                            <option value="Warehouse">Warehouse</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="machine_name" class="required">Nama Mesin</label>
                                        <input type="text" id="machine_name" name="machine_name" 
                                               placeholder="Contoh: Conveyor Belt A1" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="problematic_unit">Unit Bermasalah</label>
                                        <input type="text" id="problematic_unit" name="problematic_unit" 
                                               placeholder="Contoh: Motor Drive, Sensor, dll">
                                        <div class="field-hint">Bagian spesifik yang bermasalah (opsional)</div>
                                    </div>
                                    <div class="form-group">
                                        <label for="shift_number" class="required">Shift</label>
                                        <select id="shift_number" name="shift_number" required>
                                            <option value="">Pilih Shift</option>
                                            <option value="1">Shift 1 (07:00 - 15:00)</option>
                                            <option value="2">Shift 2 (15:00 - 23:00)</option>
                                            <option value="3">Shift 3 (23:00 - 07:00)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 3: Problem -->
                            <div class="form-section" data-section="3">
                                <h3><i class="fas fa-exclamation-triangle"></i> Detail Masalah</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="damage_type" class="required">Jenis Kerusakan</label>
                                        <select id="damage_type" name="damage_type" required>
                                            <option value="">Pilih Jenis Kerusakan</option>
                                            <option value="Mekanik">Mekanik</option>
                                            <option value="Elektrik">Elektrik</option>
                                            <option value="Safety">Safety</option>
                                            <option value="Software">Software</option>
                                            <option value="Pneumatik">Pneumatik</option>
                                            <option value="Hydraulik">Hydraulik</option>
                                            <option value="Instrumentasi">Instrumentasi</option>
                                            <option value="Lainnya">Lainnya</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="priority" class="required">Prioritas</label>
                                        <select id="priority" name="priority" required>
                                            <option value="Low">Low - Tidak mengganggu produksi</option>
                                            <option value="Medium" selected>Medium - Mengganggu sebagian produksi</option>
                                            <option value="High">High - Mengganggu produksi signifikan</option>
                                            <option value="Critical">Critical - Produksi berhenti total</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="problem_description" class="required">Deskripsi Masalah</label>
                                    <textarea id="problem_description" name="problem_description" 
                                              placeholder="Jelaskan masalah secara detail: apa yang terjadi, kapan mulai terjadi, gejala yang terlihat, dll." 
                                              required></textarea>
                                    <div class="field-hint">Berikan deskripsi yang detail untuk mempercepat penanganan</div>
                                </div>
                            </div>
                            
                            <!-- Section 4: 5W1H Analysis (Optional) -->
                            <div class="form-section" data-section="4">
                                <h3><i class="fas fa-search"></i> Analisis 5W1H (Opsional)</h3>
                                <p class="section-description">Informasi detail untuk analisis masalah yang lebih mendalam</p>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="what_happened">What - Apa yang terjadi?</label>
                                        <textarea id="what_happened" name="what_happened" 
                                                  placeholder="Jelaskan secara detail apa yang terjadi pada equipment"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="when_started">When - Kapan dimulai?</label>
                                        <input type="datetime-local" id="when_started" name="when_started">
                                        <div class="field-hint">Waktu pertama kali masalah terdeteksi</div>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="where_location">Where - Dimana lokasi spesifik?</label>
                                        <input type="text" id="where_location" name="where_location" 
                                               placeholder="Contoh: Area Line 1, Lantai 2, Ruang Utility">
                                    </div>
                                    <div class="form-group">
                                        <label for="who_found">Who - Siapa yang menemukan?</label>
                                        <input type="text" id="who_found" name="who_found" 
                                               placeholder="Nama operator/teknisi yang pertama menemukan">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="which_component">Which - Komponen mana yang bermasalah?</label>
                                        <input type="text" id="which_component" name="which_component" 
                                               placeholder="Contoh: Motor utama, Bearing, Sensor proximity">
                                    </div>
                                    <div class="form-group">
                                        <label for="how_occurred">How - Bagaimana masalah terjadi?</label>
                                        <textarea id="how_occurred" name="how_occurred" 
                                                  placeholder="Jelaskan kronologi atau proses terjadinya masalah"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 5: Additional Information -->
                            <div class="form-section" data-section="5">
                                <h3><i class="fas fa-sticky-note"></i> Informasi Tambahan</h3>
                                <div class="form-group">
                                    <label for="additional_notes">Catatan Tambahan</label>
                                    <textarea id="additional_notes" name="additional_notes" 
                                              placeholder="Informasi tambahan yang mungkin berguna: langkah yang sudah dicoba, kondisi lingkungan, dll."></textarea>
                                    <div class="field-hint">Informasi tambahan yang dapat membantu proses perbaikan</div>
                                </div>
                                
                                <div class="form-summary">
                                    <h4><i class="fas fa-clipboard-check"></i> Ringkasan Request</h4>
                                    <div class="summary-content">
                                        <div class="summary-item">
                                            <strong>Judul:</strong> <span id="summary-title">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <strong>Line:</strong> <span id="summary-line">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <strong>Mesin:</strong> <span id="summary-machine">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <strong>Jenis Kerusakan:</strong> <span id="summary-damage">-</span>
                                        </div>
                                        <div class="summary-item">
                                            <strong>Prioritas:</strong> <span id="summary-priority">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo"></i> Reset Form
                                </button>
                                <button type="submit" name="submit_maintenance" class="btn-primary" id="submit-btn">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- My Requests Section -->
                <div id="my-requests-section" class="section-content">
                    <div class="requests-header">
                        <h2><i class="fas fa-history"></i> My Request History</h2>
                        <p>Semua history request Anda (Regular + Maintenance)</p>
                        <div class="requests-stats">
                            <span class="stat-badge">Total: <?php echo $total_requests + $total_maintenance; ?></span>
                            <span class="stat-badge pending">Pending: <?php echo $pending_count; ?></span>
                            <span class="stat-badge progress">In Progress: <?php echo $in_progress_count; ?></span>
                            <span class="stat-badge completed">Completed: <?php echo $completed_count; ?></span>
                        </div>
                    </div>
                    
                    <!-- Filter and Search -->
                    <div class="requests-filters">
                        <div class="filter-group">
                            <label for="request-filter">Filter by Type:</label>
                            <select id="request-filter" onchange="filterRequests()">
                                <option value="all">All Requests</option>
                                <option value="regular">Regular Only</option>
                                <option value="maintenance">Maintenance Only</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="status-filter">Filter by Status:</label>
                            <select id="status-filter" onchange="filterRequests()">
                                <option value="all">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="assigned">Assigned</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="search-group">
                            <input type="text" id="request-search" placeholder="Search requests..." onkeyup="filterRequests()">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                    
                    <!-- Combined Requests Table -->
                    <div class="requests-container">
                        <?php if (empty($requests) && empty($maintenance_requests)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h4>No Requests Yet</h4>
                                <p>Anda belum memiliki request apapun.</p>
                                <button class="btn-primary" onclick="showSection('create-request')">
                                    <i class="fas fa-plus"></i> Create First Request
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="requests-table" id="requests-table">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Title</th>
                                            <th>Details</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Assigned To</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $request): ?>
                                        <tr class="request-row" data-type="regular" data-status="<?php echo $request['status']; ?>">
                                            <td><span class="type-badge regular"><i class="fas fa-clipboard-list"></i> Regular</span></td>
                                            <td class="request-title"><?php echo htmlspecialchars($request['title']); ?></td>
                                            <td class="request-details">
                                                <div class="detail-item">Description: <?php echo htmlspecialchars(substr($request['description'] ?? '', 0, 50)) . (strlen($request['description'] ?? '') > 50 ? '...' : ''); ?></div>
                                            </td>
                                            <td><span class="priority-<?php echo strtolower($request['priority']); ?>"><?php echo ucfirst($request['priority']); ?></span></td>
                                            <td><?php echo getStatusBadge($request['status']); ?></td>
                                            <td><?php echo $request['assigned_name'] ? htmlspecialchars($request['assigned_name']) : '<span class="not-assigned">Not assigned</span>'; ?></td>
                                            <td><?php echo formatDate($request['created_at']); ?></td>
                                            <td>
                                                <button class="btn-icon" onclick="viewRequest('regular', <?php echo $request['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php foreach ($maintenance_requests as $request): ?>
                                        <tr class="request-row" data-type="maintenance" data-status="<?php echo $request['status']; ?>">
                                            <td><span class="type-badge maintenance"><i class="fas fa-tools"></i> Maintenance</span></td>
                                            <td class="request-title"><?php echo htmlspecialchars($request['request_title']); ?></td>
                                            <td class="request-details">
                                                <div class="detail-item">Machine: <?php echo htmlspecialchars($request['machine_name']); ?></div>
                                                <div class="detail-item">Line: <?php echo htmlspecialchars($request['production_line']); ?></div>
                                                <div class="detail-item">Type: <?php echo htmlspecialchars($request['damage_type']); ?></div>
                                            </td>
                                            <td><span class="priority-<?php echo strtolower($request['priority']); ?>"><?php echo $request['priority']; ?></span></td>
                                            <td><?php echo getStatusBadge($request['status']); ?></td>
                                            <td><?php echo $request['assigned_to'] ? 'Maintenance Team' : '<span class="not-assigned">Not assigned</span>'; ?></td>
                                            <td><?php echo formatDate($request['created_at']); ?></td>
                                            <td>
                                                <button class="btn-icon" onclick="viewRequest('maintenance', <?php echo $request['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script src="../assets/js/maintenance.js"></script>
    <script>
        // Chart.js configuration
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Total Requests',
                    data: <?php echo json_encode($chart_data); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                }
            }
        });
        
        // Navigation functions
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').classList.add('active');
            
            // Add active class to clicked nav link
            event.target.closest('.nav-link').classList.add('active');
            
            // Update page title
            const titles = {
                'dashboard': 'Dashboard',
                'create-request': 'Create Request',
                'my-requests': 'My Requests'
            };
            document.getElementById('page-title').textContent = titles[sectionName];
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Show dashboard section by default
            showSection('dashboard');
            
            // Add click event listeners to navigation links
            document.querySelectorAll('.nav-link[onclick]').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const onclick = this.getAttribute('onclick');
                    if (onclick && onclick.includes('showSection')) {
                        const match = onclick.match(/showSection\('([^']+)'\)/);
                        if (match) {
                            showSection(match[1]);
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>