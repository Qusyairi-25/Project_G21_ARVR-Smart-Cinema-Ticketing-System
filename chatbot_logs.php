<?php
session_start();
include 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo "Access Denied. Admin only.";
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Handle clear all logs
$delete_message = '';
$delete_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear_all'])) {
    $delete_sql = "DELETE FROM chatbot_logs";
    if ($conn->query($delete_sql)) {
        $delete_message = "All chatbot logs have been cleared!";
    } else {
        $delete_error = "Error clearing logs.";
    }
}

// Filter search
$search = "";
$sql = "SELECT chatbot_logs.*, users.username, users.role
        FROM chatbot_logs
        LEFT JOIN users ON chatbot_logs.user_id = users.user_id";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $sql .= " WHERE users.username LIKE '%$search%'
              OR chatbot_logs.user_message LIKE '%$search%'
              OR chatbot_logs.bot_reply LIKE '%$search%'";
}

$sql .= " ORDER BY chatbot_logs.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chatbot Logs - ARVR Cinema Admin</title>
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
        
        .logo p {
            font-size: 14px;
            opacity: 0.7;
        }
        
        .admin-badge {
            background: #f44336;
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
            cursor: pointer;
            border: none;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #ff4d6d;
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
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .logs-section {
            padding: 20px 40px;
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .logs-header h2 {
            font-size: 24px;
        }
        
        .logs-header h2 i {
            color: #ff4d6d;
            margin-right: 10px;
        }
        
        .search-area {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05);
            color: white;
            font-family: 'Poppins', sans-serif;
            width: 250px;
        }
        
        .search-box input::placeholder {
            color: rgba(255,255,255,0.5);
        }
        
        .logs-table {
            width: 100%;
            overflow-x: auto;
            background: rgba(255,255,255,0.03);
            border-radius: 15px;
            padding: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        th {
            background: rgba(255,77,109,0.2);
            font-weight: 600;
        }
        
        tr:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .message-cell {
            max-width: 300px;
            word-wrap: break-word;
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
        .role-null { background: #666; }
        
        .user-msg {
            color: #00d4ff;
        }
        
        .bot-msg {
            color: #7CFC00;
        }
        
        .timestamp {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
        }
        
        .view-message {
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: 0.3s;
        }
        
        .view-message:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .truncate {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            word-break: break-word;
        }
        
        .message {
            padding: 15px 40px;
            margin: 0 40px 20px 40px;
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
        
        .modal-content h3 {
            margin-bottom: 20px;
        }
        
        .modal-message {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        
        .clear-all-btn {
            background: #f44336;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }
        
        .clear-all-btn:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .admin-header {
                padding: 20px;
            }
            
            .logs-section {
                padding: 20px;
            }
            
            .logs-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-area {
                width: 100%;
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            .search-box input {
                flex: 1;
            }
            
            th, td {
                padding: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="logo">
            <h1><i class="fas fa-robot"></i> Chatbot Logs</h1>
            <p>View and manage chatbot conversations</p>
        </div>
        <div class="user-info">
            <span class="admin-badge"><i class="fas fa-crown"></i> ADMIN</span>
            <span class="username"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
            <a href="staff_homepage.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- Messages -->
    <?php if ($delete_message): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo $delete_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($delete_error): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $delete_error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Logs Table -->
    <div class="logs-section">
        <div class="logs-header">
            <h2><i class="fas fa-history"></i> Conversation Logs</h2>
            <div class="search-area">
                <form method="GET" class="search-box" action="">
                    <input type="text" name="search" placeholder="Search by user, message, or reply..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="chatbot_logs.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
                
                <!-- Clear All Button -->
                <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ WARNING: This will delete ALL chatbot logs. This action cannot be undone. Are you sure?');">
                    <button type="submit" name="clear_all" class="clear-all-btn">
                        <i class="fas fa-database"></i> Clear All Logs
                    </button>
                </form>
            </div>
        </div>
        
        <div class="logs-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>User Message</th>
                        <th>Bot Reply</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $clean_bot_reply = strip_tags($row['bot_reply']);
                            $clean_bot_reply = html_entity_decode($clean_bot_reply);
                        ?>
                            <tr>
                                <td><?php echo $row['log_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['username'] ?? 'Deleted User'); ?></strong><br>
                                    <span class="role-badge role-<?php echo $row['role'] ?? 'null'; ?>">
                                        <?php echo strtoupper($row['role'] ?? 'GUEST'); ?>
                                    </span>
                                </td>
                                <td class="message-cell">
                                    <div class="view-message" onclick="viewMessage('user', <?php echo htmlspecialchars(json_encode($row['user_message'])); ?>)">
                                        <i class="fas fa-user user-msg"></i>
                                        <div class="truncate user-msg"><?php echo htmlspecialchars(substr($row['user_message'], 0, 100)); ?></div>
                                    </div>
                                </td>
                                <td class="message-cell">
                                    <div class="view-message" onclick="viewMessage('bot', <?php echo htmlspecialchars(json_encode($row['bot_reply'])); ?>)">
                                        <i class="fas fa-robot bot-msg"></i>
                                        <div class="truncate bot-msg"><?php echo htmlspecialchars(substr($clean_bot_reply, 0, 100)); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="timestamp">
                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($row['created_at'])); ?><br>
                                        <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 50px;">
                                <i class="fas fa-comment-slash" style="font-size: 50px; opacity: 0.3;"></i>
                                <p style="margin-top: 20px;">No chatbot logs found</p>
                                <p style="font-size: 14px; opacity: 0.6;">When users interact with the chatbot, their conversations will appear here.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Message View Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle"><i class="fas fa-comment"></i> Message Details</h3>
            <div id="modalMessage" class="modal-message"></div>
            <div class="modal-buttons">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        function viewMessage(type, message) {
            const modal = document.getElementById('messageModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalMessage');
            
            if (type === 'user') {
                title.innerHTML = '<i class="fas fa-user"></i> User Message';
            } else {
                title.innerHTML = '<i class="fas fa-robot"></i> Bot Response';
            }
            
            if (type === 'bot') {
                content.innerHTML = message;
            } else {
                content.innerHTML = message.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
            }
            modal.style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('messageModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('messageModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>