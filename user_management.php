<?php
session_start();

// Check if user is logged in and has staff privileges
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    header("Location: homepage.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Handle user role update (admin only)
$update_message = '';
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user_role']) && $role == 'admin') {
    $target_user_id = (int)$_POST['user_id'];
    $new_role = $_POST['role'];
    
    // Don't allow admin to change their own role
    if ($target_user_id != $user_id) {
        $update_sql = "UPDATE users SET role = ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $new_role, $target_user_id);
        
        if ($stmt->execute()) {
            $update_message = "User role updated successfully!";
        } else {
            $update_error = "Error updating user role.";
        }
        $stmt->close();
    } else {
        $update_error = "You cannot change your own role.";
    }
}

// Handle user deletion (admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user']) && $role == 'admin') {
    $target_user_id = (int)$_POST['user_id'];
    
    // Don't allow admin to delete themselves
    if ($target_user_id != $user_id) {
        // Delete user's related records first
        $conn->begin_transaction();
        try {
            // Delete user's feedback
            $delete_feedback = $conn->prepare("DELETE FROM movie_feedback WHERE user_id = ?");
            $delete_feedback->bind_param("i", $target_user_id);
            $delete_feedback->execute();
            
            // Delete user's wishlist
            $delete_wishlist = $conn->prepare("DELETE FROM wishlist WHERE user_id = ?");
            $delete_wishlist->bind_param("i", $target_user_id);
            $delete_wishlist->execute();
            
            // Delete user's bookings
            $delete_bookings = $conn->prepare("DELETE FROM bookings WHERE user_id = ?");
            $delete_bookings->bind_param("i", $target_user_id);
            $delete_bookings->execute();
            
            // Delete user
            $delete_user = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $delete_user->bind_param("i", $target_user_id);
            $delete_user->execute();
            
            $conn->commit();
            $update_message = "User deleted successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $update_error = "Error deleting user: " . $e->getMessage();
        }
    } else {
        $update_error = "You cannot delete yourself.";
    }
}

// Fetch all users
$users_sql = "SELECT * FROM users ORDER BY user_id DESC";
$users_result = $conn->query($users_sql);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as regular_users,
                SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff_members,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins
              FROM users";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - ARVR Cinema</title>
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
        
        /* Header */
        .staff-header {
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
        
        .staff-badge {
            background: #ff4d6d;
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
            color: #ff4d6d;
            font-weight: 600;
        }
        
        .btn {
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #ff4d6d;
            color: white;
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
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
        }
        
        .stat-card i {
            font-size: 40px;
            color: #ff4d6d;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
        }
        
        .users-section {
            padding: 20px 40px;
        }
        
        .users-table {
            width: 100%;
            overflow-x: auto;
            background: rgba(255,255,255,0.03);
            border-radius: 15px;
            padding: 20px;
        }
        
        table {
            width: 100%;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        th {
            background: rgba(255,77,109,0.2);
        }
        
        tr:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .role-user { background: #2196F3; }
        .role-staff { background: #FF9800; }
        .role-admin { background: #f44336; }
        
        .role-select {
            padding: 5px 10px;
            border-radius: 5px;
            background: rgba(0,0,0,0.5);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .message {
            padding: 15px 40px;
            margin: 20px 40px;
            border-radius: 10px;
        }
        
        .message.success {
            background: rgba(76, 175, 80, 0.2);
            border-left: 4px solid #4CAF50;
        }
        
        .message.error {
            background: rgba(244, 67, 54, 0.2);
            border-left: 4px solid #f44336;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .action-buttons button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
        }
        
        .back-link {
            margin: 20px 40px;
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
            max-width: 400px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
    <div class="staff-header">
        <div class="logo">
            <h1>👥 User Management</h1>
            <p>Manage system users and their roles</p>
        </div>
        <div class="user-info">
            <span class="staff-badge"><?php echo strtoupper($role); ?></span>
            <span class="username"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
            <a href="staff_homepage.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="stats-container">
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <div class="number"><?php echo $stats['total_users']; ?></div>
            <div>Total Users</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-user"></i>
            <div class="number"><?php echo $stats['regular_users']; ?></div>
            <div>Regular Users</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-shield-alt"></i>
            <div class="number"><?php echo $stats['staff_members']; ?></div>
            <div>Staff Members</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-crown"></i>
            <div class="number"><?php echo $stats['admins']; ?></div>
            <div>Admins</div>
        </div>
    </div>
    
    <!-- Messages -->
    <?php if ($update_message): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo $update_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($update_error): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $update_error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Users Table -->
    <div class="users-section">
        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Current Role</th>
                        <?php if ($role == 'admin'): ?>
                            <th>Change Role</th>
                            <th>Actions</th>
                        <?php else: ?>
                            <th>Role</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo strtoupper($user['role']); ?>
                            </span>
                        </td>
                        <?php if ($role == 'admin'): ?>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <select name="role" class="role-select" onchange="this.form.submit()">
                                        <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                                        <option value="staff" <?php echo $user['role'] == 'staff' ? 'selected' : ''; ?>>Staff</option>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                    <input type="hidden" name="update_user_role">
                                </form>
                            </td>
                            <td>
                                <?php if ($user['user_id'] != $user_id): ?>
                                    <button onclick="confirmDelete(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                            style="background: none; border: none; color: #f44336; cursor: pointer;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <span style="opacity: 0.5;"><i class="fas fa-lock"></i></span>
                                <?php endif; ?>
                            </td>
                        <?php else: ?>
                            <td><?php echo ucfirst($user['role']); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
            <p>Are you sure you want to delete user "<span id="deleteUserName"></span>"?</p>
            <p style="color: #f44336; font-size: 14px; margin-top: 10px;">This will delete all their data including feedback, wishlist, and bookings.</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-primary" style="background: #f44336;">Delete User</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function confirmDelete(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
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
</html>