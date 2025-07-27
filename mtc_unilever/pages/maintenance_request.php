<h2>Create Maintenance Request</h2>

<form method="POST" action="" id="maintenance-form">
    <!-- Section 1: Request Identity -->
    <div class="form-section">
        <h3>1. Identitas Permintaan</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="requester_name">Nama Peminta *</label>
                <input type="text" id="requester_name" name="requester_name" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="request_title">Judul Permintaan *</label>
                <input type="text" id="request_title" name="request_title" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="request_date">Tanggal Pengajuan *</label>
                <input type="date" id="request_date" name="request_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label for="incident_time">Waktu Kejadian *</label>
                <input type="time" id="incident_time" name="incident_time" required>
            </div>
        </div>
    </div>
    
    <!-- Section 2: Production Details -->
    <div class="form-section">
        <h3>2. Detail Produksi</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="production_line">Line Produksi *</label>
                <input type="text" id="production_line" name="production_line" required>
            </div>
            <div class="form-group">
                <label for="machine_name">Nama Mesin *</label>
                <input type="text" id="machine_name" name="machine_name" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="problematic_unit">Unit Bermasalah</label>
                <input type="text" id="problematic_unit" name="problematic_unit">
            </div>
            <div class="form-group">
                <label for="shift_number">Shift *</label>
                <select id="shift_number" name="shift_number" required>
                    <option value="">Pilih Shift</option>
                    <option value="1">Shift 1</option>
                    <option value="2">Shift 2</option>
                    <option value="3">Shift 3</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Section 3: Problem -->
    <div class="form-section">
        <h3>3. Masalah</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="damage_type">Jenis Kerusakan *</label>
                <select id="damage_type" name="damage_type" required>
                    <option value="">Pilih Jenis Kerusakan</option>
                    <option value="Mekanik">Mekanik</option>
                    <option value="Elektrik">Elektrik</option>
                    <option value="Safety">Safety</option>
                    <option value="Software">Software</option>
                    <option value="Pneumatik">Pneumatik</option>
                    <option value="Hydraulik">Hydraulik</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="problem_description">Deskripsi Masalah *</label>
            <textarea id="problem_description" name="problem_description" required></textarea>
        </div>
    </div>
    
    <!-- Section 4: 5W1H (Optional) -->
    <div class="form-section">
        <h3>4. 5W1H (Opsional)</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="what_happened">What (Apa yang terjadi)</label>
                <textarea id="what_happened" name="what_happened"></textarea>
            </div>
            <div class="form-group">
                <label for="when_started">When (Kapan dimulai)</label>
                <input type="datetime-local" id="when_started" name="when_started">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="where_location">Where (Dimana lokasi)</label>
                <input type="text" id="where_location" name="where_location">
            </div>
            <div class="form-group">
                <label for="who_found">Who (Siapa yang menemukan)</label>
                <input type="text" id="who_found" name="who_found">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="which_component">Which (Komponen mana)</label>
                <input type="text" id="which_component" name="which_component">
            </div>
            <div class="form-group">
                <label for="how_occurred">How (Bagaimana terjadi)</label>
                <textarea id="how_occurred" name="how_occurred"></textarea>
            </div>
        </div>
    </div>
    
    <!-- Section 5: Additional -->
    <div class="form-section">
        <h3>5. Tambahan</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="priority">Prioritas *</label>
                <select id="priority" name="priority" required>
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="additional_notes">Catatan Tambahan</label>
            <textarea id="additional_notes" name="additional_notes"></textarea>
        </div>
    </div>
    
    <button type="submit" name="submit_maintenance" class="btn-primary">Submit Maintenance Request</button>
</form>

<!-- Display existing maintenance requests -->
<div style="margin-top: 40px;">
    <h2>My Maintenance Requests</h2>
    <?php if (empty($maintenance_requests)): ?>
        <p>Belum ada maintenance request.</p>
    <?php else: ?>
        <table class="maintenance-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Machine</th>
                    <th>Damage Type</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($maintenance_requests as $request): ?>
                <tr>
                    <td><?php echo htmlspecialchars($request['request_title']); ?></td>
                    <td><?php echo htmlspecialchars($request['machine_name']); ?></td>
                    <td><?php echo htmlspecialchars($request['damage_type']); ?></td>
                    <td><span class="priority-<?php echo strtolower($request['priority']); ?>"><?php echo $request['priority']; ?></span></td>
                    <td><?php echo getStatusBadge($request['status']); ?></td>
                    <td><?php echo formatDate($request['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>