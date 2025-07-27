-- Tabel untuk Maintenance Requests
CREATE TABLE IF NOT EXISTS maintenance_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Bagian 1: Identitas Permintaan
    requester_name VARCHAR(255) NOT NULL,
    request_title VARCHAR(255) NOT NULL,
    request_date DATE NOT NULL,
    incident_time TIME NOT NULL,
    
    -- Bagian 2: Detail Produksi
    production_line VARCHAR(100) NOT NULL,
    machine_name VARCHAR(255) NOT NULL,
    problematic_unit VARCHAR(255),
    shift_number ENUM('1', '2', '3') NOT NULL,
    
    -- Bagian 3: Masalah
    damage_type ENUM('Mekanik', 'Elektrik', 'Safety', 'Software', 'Pneumatik', 'Hydraulik', 'Lainnya') NOT NULL,
    problem_description TEXT NOT NULL,
    
    -- Bagian 4: 5W1H (Opsional)
    what_happened TEXT,
    when_started DATETIME,
    where_location VARCHAR(255),
    who_found VARCHAR(255),
    which_component VARCHAR(255),
    how_occurred TEXT,
    
    -- Bagian 5: Tambahan
    photo_video_path VARCHAR(500),
    priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    additional_notes TEXT,
    
    -- System fields
    user_id INT NOT NULL,
    status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_to INT NULL,
    assigned_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Tabel untuk multiple file uploads
CREATE TABLE IF NOT EXISTS maintenance_request_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type ENUM('photo', 'video', 'document') NOT NULL,
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES maintenance_requests(id) ON DELETE CASCADE
);

-- Tabel untuk tracking status history
CREATE TABLE IF NOT EXISTS maintenance_request_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    status_from VARCHAR(50),
    status_to VARCHAR(50) NOT NULL,
    changed_by INT NOT NULL,
    notes TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES maintenance_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- Indexes untuk performa
CREATE INDEX idx_maintenance_requests_user ON maintenance_requests(user_id);
CREATE INDEX idx_maintenance_requests_status ON maintenance_requests(status);
CREATE INDEX idx_maintenance_requests_priority ON maintenance_requests(priority);
CREATE INDEX idx_maintenance_requests_date ON maintenance_requests(request_date);
CREATE INDEX idx_maintenance_requests_machine ON maintenance_requests(machine_name);
CREATE INDEX idx_maintenance_requests_line ON maintenance_requests(production_line);
CREATE INDEX idx_maintenance_request_files_request ON maintenance_request_files(request_id);
CREATE INDEX idx_maintenance_request_history_request ON maintenance_request_history(request_id);