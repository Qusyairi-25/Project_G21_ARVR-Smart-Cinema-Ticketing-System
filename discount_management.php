<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has staff/admin privileges
if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    header("Location: homepage.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Staff';
$role = $_SESSION['role'] ?? 'staff';

// Handle adding new discount code
if (isset($_POST['add_discount'])) {
    $code = strtoupper(trim($_POST['code']));
    $discount_percent = (int)$_POST['discount_percent'];
    $expiry_date = $_POST['expiry_date'];
    $generated_for = !empty($_POST['generated_for']) ? (int)$_POST['generated_for'] : null;
    
    // Check if code already exists
    $check_sql = "SELECT code_id FROM discount_codes WHERE code = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $code);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $error_message = "Discount code already exists!";
    } else {
        $insert_sql = "INSERT INTO discount_codes (code, discount_percent, status, generated_for, generated_by, generated_at, expiry_date) 
                       VALUES (?, ?, 'active', ?, ?, NOW(), ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("siiis", $code, $discount_percent, $generated_for, $user_id, $expiry_date);
        
        if ($insert_stmt->execute()) {
            $success_message = "Discount code <strong>" . htmlspecialchars($code) . "</strong> created successfully!";
        } else {
            $error_message = "Error creating discount code: " . $conn->error;
        }
    }
}

// Handle status update (activate/deactivate/expire)
if (isset($_POST['update_status'])) {
    $code_id = (int)$_POST['code_id'];
    $new_status = $_POST['status'];
    
    $update_sql = "UPDATE discount_codes SET status = ? WHERE code_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $code_id);
    
    if ($update_stmt->execute()) {
        $success_message = "Discount code status updated!";
    } else {
        $error_message = "Error updating status.";
    }
}

// Handle delete discount code (admin only)
if (isset($_POST['delete_code']) && $role === 'admin') {
    $code_id = (int)$_POST['code_id'];
    
    $delete_sql = "DELETE FROM discount_codes WHERE code_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $code_id);
    
    if ($delete_stmt->execute()) {
        $success_message = "Discount code deleted successfully!";
    } else {
        $error_message = "Error deleting discount code.";
    }
}

// Fetch all discount codes
$sql = "SELECT dc.*, 
        u1.username as generated_by_name,
        u2.username as generated_for_name,
        u3.username as used_by_name
        FROM discount_codes dc
        LEFT JOIN users u1 ON dc.generated_by = u1.user_id
        LEFT JOIN users u2 ON dc.generated_for = u2.user_id
        LEFT JOIN users u3 ON dc.used_by = u3.user_id
        ORDER BY dc.generated_at DESC";
$result = $conn->query($sql);
$discounts = $result->fetch_all(MYSQLI_ASSOC);

// Get stats
$stats_sql = "SELECT 
                COUNT(*) as total_codes,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_codes,
                SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_codes,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_codes
              FROM discount_codes";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get staff users for dropdown
$staff_sql = "SELECT user_id, username FROM users WHERE role IN ('staff', 'admin') ORDER BY username";
$staff_result = $conn->query($staff_sql);
$staff_users = $staff_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Discount Management - <?php echo ucfirst($role); ?> Panel</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #0a0a0a;
            color: white;
        }
        
        .staff-header {
            background: linear-gradient(135deg, #1a0a0f 0%, #0a0a0a 100%);
            padding: 20px 40px;
            border-bottom: 2px solid #9c27b0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .logo h1 {
            font-size: 24px;
            background: linear-gradient(45deg, #9c27b0, #ff4d6d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo p {
            font-size: 12px;
            opacity: 0.7;
        }
        
        .staff-badge {
            background: #9c27b0;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info .username {
            color: #9c27b0;
            font-weight: 600;
        }
        
        .logout-btn {
            background: rgba(156,39,176,0.2);
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            transition: 0.3s;
        }
        
        .logout-btn:hover {
            background: #9c27b0;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.1);
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px 40px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.05);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            transition: 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.08);
        }
        
        .stat-card i {
            font-size: 40px;
            color: #9c27b0;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
        }
        
        .stat-card .label {
            font-size: 14px;
            opacity: 0.7;
        }
        
        .form-section {
            padding: 20px 40px;
        }
        
        .form-card {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .form-card h3 {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: rgba(255,255,255,0.8);
        }
        
        .form-group input, .form-group select {
            width: 100%;
            max-width: 400px;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.5);
            color: white;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #9c27b0;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #6a1b9a, #9c27b0);
            color: white;
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .discounts-section {
            padding: 20px 40px;
        }
        
        .discounts-table {
            width: 100%;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            background: rgba(255,255,255,0.03);
            border-radius: 15px;
            overflow: hidden;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        th {
            background: rgba(156,39,176,0.2);
            font-weight: 600;
        }
        
        tr:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .status-badge {
            display: inline-block;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-active { background: #4CAF50; }
        .status-used { background: #9c27b0; }
        .status-expired { background: #f44336; }
        
        .code-display {
            font-family: monospace;
            font-size: 14px;
            font-weight: 700;
            background: rgba(0,0,0,0.5);
            padding: 4px 8px;
            border-radius: 5px;
            display: inline-block;
        }
        
        .discount-percent {
            color: #ff9800;
            font-weight: 700;
        }
        
        .action-icons {
            display: flex;
            gap: 10px;
        }
        
        .action-icons button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
            transition: 0.3s;
        }
        
        .action-icons .edit { color: #4CAF50; }
        .action-icons .delete { color: #f44336; }
        
        .message {
            padding: 15px 40px;
            margin: 0 40px 20px 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: rgba(76, 175, 80, 0.2);
            border-left: 4px solid #4CAF50;
        }
        
        .message.error {
            background: rgba(244, 67, 54, 0.2);
            border-left: 4px solid #f44336;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: #1a1a1a;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        
        .info-text {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .staff-header {
                padding: 15px 20px;
            }
            
            .stats-container, .form-section, .discounts-section {
                padding: 20px;
            }
            
            th, td {
                padding: 10px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="staff-header">
        <div class="logo">
            <h1>🎟️ Discount Management</h1>
            <p>Create and manage discount codes</p>
        </div>
        
        <div class="user-info">
            <span class="staff-badge"><i class="fas fa-tags"></i> 
                <?php echo $role === 'admin' ? 'ADMIN' : 'STAFF'; ?>
            </span>
            <span class="username"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
            <a href="staff_homepage.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <i class="fas fa-tags"></i>
            <div class="number"><?php echo $stats['total_codes']; ?></div>
            <div class="label">Total Codes</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-check-circle"></i>
            <div class="number"><?php echo $stats['active_codes']; ?></div>
            <div class="label">Active Codes</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-check-double"></i>
            <div class="number"><?php echo $stats['used_codes']; ?></div>
            <div class="label">Used Codes</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-hourglass-end"></i>
            <div class="number"><?php echo $stats['expired_codes']; ?></div>
            <div class="label">Expired Codes</div>
        </div>
    </div>
    
    <!-- Add Discount Form -->
    <div class="form-section">
        <div class="form-card">
            <h3><i class="fas fa-plus-circle"></i> Create New Discount Code</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-code"></i> Discount Code</label>
                        <input type="text" name="code" placeholder="e.g., STAFFSUMMER20" required 
                               pattern="[A-Za-z0-9]+" title="Only letters and numbers">
                        <div class="info-text">Use uppercase letters and numbers only. Will be automatically converted to uppercase.</div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-percent"></i> Discount Percentage (%)</label>
                        <input type="number" name="discount_percent" min="1" max="100" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Expiry Date</label>
                        <input type="date" name="expiry_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user-tie"></i> Assign to Specific Staff (Optional)</label>
                        <select name="generated_for">
                            <option value="">-- All Staff --</option>
                            <?php foreach ($staff_users as $staff): ?>
                                <option value="<?php echo $staff['user_id']; ?>">
                                    <?php echo htmlspecialchars($staff['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="info-text">If assigned, only this staff member can use this code.</div>
                    </div>
                </div>
                
                <button type="submit" name="add_discount" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Discount Code
                </button>
            </form>
        </div>
    </div>
    
    <!-- Messages -->
    <?php if (isset($success_message)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Discount Codes Table -->
    <div class="discounts-section">
        <h2 style="margin-bottom: 20px;"><i class="fas fa-list"></i> All Discount Codes</h2>
        <div class="discounts-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Status</th>
                        <th>Generated For</th>
                        <th>Generated By</th>
                        <th>Generated At</th>
                        <th>Expiry Date</th>
                        <th>Used By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($discounts as $discount): ?>
                    <tr>
                        <td><?php echo $discount['code_id']; ?></td>
                        <td><span class="code-display"><?php echo htmlspecialchars($discount['code']); ?></span></td>
                        <td><span class="discount-percent"><?php echo $discount['discount_percent']; ?>%</span></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="code_id" value="<?php echo $discount['code_id']; ?>">
                                <select name="status" class="status-badge" style="background: transparent;" onchange="this.form.submit()">
                                    <option value="active" <?php echo $discount['status'] == 'active' ? 'selected' : ''; ?> class="status-active">Active</option>
                                    <option value="used" <?php echo $discount['status'] == 'used' ? 'selected' : ''; ?> class="status-used">Used</option>
                                    <option value="expired" <?php echo $discount['status'] == 'expired' ? 'selected' : ''; ?> class="status-expired">Expired</option>
                                </select>
                                <input type="hidden" name="update_status">
                            </form>
                        </td>
                        <td><?php echo $discount['generated_for'] ? htmlspecialchars($discount['generated_for_name']) : '<em>All Staff</em>'; ?></td>
                        <td><?php echo htmlspecialchars($discount['generated_by_name'] ?? 'System'); ?></td>
                        <td><?php echo date('d M Y', strtotime($discount['generated_at'])); ?></td>
                        <td style="color: <?php echo strtotime($discount['expiry_date']) < time() ? '#f44336' : '#4CAF50'; ?>">
                            <?php echo date('d M Y', strtotime($discount['expiry_date'])); ?>
                        </td>
                        <td><?php echo $discount['used_by'] ? htmlspecialchars($discount['used_by_name']) : '-'; ?></td>
                        <td class="action-icons">
                            <?php if ($role === 'admin'): ?>
                            <button onclick="confirmDelete(<?php echo $discount['code_id']; ?>, '<?php echo htmlspecialchars($discount['code']); ?>')" class="delete" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php else: ?>
                            <span style="opacity: 0.5;" title="Only admins can delete"><i class="fas fa-lock"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($discounts)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px;">
                            <i class="fas fa-tags" style="font-size: 48px; opacity: 0.5;"></i>
                            <p>No discount codes found. Create your first one above!</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
            <p>Are you sure you want to delete discount code "<span id="deleteCodeTitle"></span>"?</p>
            <p style="color: #f44336; font-size: 14px; margin-top: 10px;">This action cannot be undone.</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="code_id" id="deleteCodeId">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="delete_code" class="btn btn-danger">Delete Code</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function confirmDelete(codeId, codeTitle) {
            document.getElementById('deleteCodeId').value = codeId;
            document.getElementById('deleteCodeTitle').textContent = codeTitle;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>S