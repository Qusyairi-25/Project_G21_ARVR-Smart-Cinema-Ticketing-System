<?php
session_start();
include 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo "Access Denied. Admin only.";
    exit();
}

$username = $_SESSION['username'];

// Handle save settings
$success_message = '';

// Update user early access
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $early_access = $_POST['early_access'] ?? 0;
    $early_access_days = $_POST['early_access_days'] ?: NULL;
    
    $update_sql = "UPDATE users SET early_access = ?, early_access_days = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("iii", $early_access, $early_access_days, $user_id);
    
    if ($update_stmt->execute()) {
        $success_message = "User early access updated!";
    }
}

// Handle role default settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_defaults'])) {
    $_SESSION['staff_early_days'] = $_POST['staff_days'] ?? 0;
    $_SESSION['user_early_days'] = $_POST['user_days'] ?? 0;
    $success_message = "Default early access settings saved!";
}

// Get all users
$users_sql = "SELECT user_id, username, role, early_access, early_access_days FROM users ORDER BY role, username";
$users_result = $conn->query($users_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Early Access Management - ARVR Cinema Admin</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #0a0a0a; color: white; }
        
        .admin-header {
            background: linear-gradient(135deg, #1a0a0f 0%, #0a0a0a 100%);
            padding: 20px 40px;
            border-bottom: 2px solid #ff4d6d;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .logo h1 {
            font-size: 24px;
            background: linear-gradient(45deg, #ff4d6d, #ff8c00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo p { font-size: 14px; opacity: 0.7; }
        .admin-badge { background: #f44336; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .btn { padding: 8px 20px; border-radius: 8px; text-decoration: none; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; font-family: 'Poppins', sans-serif; }
        .btn-primary { background: #ff4d6d; color: white; }
        .btn-secondary { background: rgba(255,255,255,0.1); color: white; }
        .btn-success { background: #4CAF50; color: white; }
        .btn-primary:hover, .btn-secondary:hover, .btn-success:hover { transform: translateY(-2px); }
        
        .settings-section { padding: 40px; max-width: 1200px; margin: 0 auto; }
        .settings-card { background: rgba(255,255,255,0.03); border-radius: 20px; padding: 30px; margin-bottom: 30px; }
        .settings-header { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .settings-header i { font-size: 32px; color: #ff4d6d; }
        .settings-header h2 { font-size: 24px; }
        .settings-header p { font-size: 14px; opacity: 0.7; margin-top: 5px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group select, .form-group input { padding: 10px 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.05); color: white; width: 100%; max-width: 300px; }
        
        .users-table { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(255,77,109,0.2); font-weight: 600; }
        tr:hover { background: rgba(255,255,255,0.05); }
        
        .role-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .role-admin { background: #f44336; }
        .role-staff { background: #FF9800; }
        .role-user { background: #2196F3; }
        
        .early-badge { background: #4CAF50; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .early-badge.no { background: #555; }
        
        .message { padding: 15px 40px; margin: 0 40px 20px 40px; border-radius: 10px; }
        .message.success { background: rgba(76, 175, 80, 0.2); border-left: 4px solid #4CAF50; }
        
        .save-btn { background: #ff4d6d; color: white; padding: 12px 30px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; margin-top: 20px; }
        
        .warning-note { background: rgba(255, 77, 109, 0.15); border-left: 4px solid #ff4d6d; padding: 15px; margin-bottom: 20px; border-radius: 8px; font-size: 13px; }
        
        @media (max-width: 768px) { .settings-section { padding: 20px; } th, td { padding: 10px; font-size: 12px; } }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="logo">
            <h1><i class="fas fa-clock"></i> Early Access Management</h1>
            <p>Control which users can book movies before release date</p>
        </div>
        <div class="user-info">
            <span class="admin-badge"><i class="fas fa-crown"></i> ADMIN</span>
            <span class="username"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
            <a href="staff_homepage.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
            <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <?php if ($success_message): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
    <?php endif; ?>
    

        
        <!-- Manage Individual Users Card -->
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-users"></i>
                <div>
                    <h2>Individual User Early Access</h2>
                    <p>Grant early access to specific users (overrides role defaults)</p>
                </div>
            </div>
            
            <div class="users-table">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Early Access</th>
                            <th>Early Access Days</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><span class="role-badge role-<?php echo $user['role']; ?>"><?php echo strtoupper($user['role']); ?></span></td>
                                    <td>
                                        <select name="early_access">
                                            <option value="0" <?php echo $user['early_access'] == 0 ? 'selected' : ''; ?>>❌ No (Use role default)</option>
                                            <option value="1" <?php echo $user['early_access'] == 1 ? 'selected' : ''; ?>>✅ Yes (Override)</option>
                                        </select>
                                     </td>
                                    <td>
                                        <input type="number" name="early_access_days" value="<?php echo $user['early_access_days'] ?? ''; ?>" placeholder="Days (e.g., 7)" style="width: 100px; padding: 8px; border-radius: 5px; background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,255,255,0.2);">
                                     </td>
                                    <td>
                                        <button type="submit" name="update_user" class="btn btn-success" style="padding: 8px 15px;"><i class="fas fa-save"></i> Save</button>
                                     </td>
                                </tr>
                            </form>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>