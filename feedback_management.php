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

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Add status column if not exists
$conn->query("ALTER TABLE movie_feedback ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending'");

// Handle Approve Feedback
if (isset($_GET['approve'])) {
    $feedback_id = (int)$_GET['approve'];
    
    $update_sql = "UPDATE movie_feedback SET status = 'approved' WHERE feedback_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $feedback_id);
    
    if ($stmt->execute()) {
        $success_message = "Feedback approved successfully!";
    } else {
        $error_message = "Error approving feedback.";
    }
    $stmt->close();
    
    header("Location: feedback_management.php");
    exit();
}

// Handle Delete Feedback
if (isset($_GET['delete'])) {
    $feedback_id = (int)$_GET['delete'];
    
    $delete_sql = "DELETE FROM movie_feedback WHERE feedback_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $feedback_id);
    
    if ($stmt->execute()) {
        $success_message = "Feedback deleted successfully!";
    } else {
        $error_message = "Error deleting feedback.";
    }
    $stmt->close();
    
    header("Location: feedback_management.php");
    exit();
}

// Handle Filter
$status_filter = $_GET['status'] ?? 'all';

// Fetch all feedback with user and movie details
if ($status_filter !== 'all') {
    $feedback_sql = "SELECT mf.*, u.username, u.email, mo.title as movie_title, mo.poster
                     FROM movie_feedback mf
                     JOIN users u ON mf.user_id = u.user_id
                     JOIN movies mo ON mf.movie_id = mo.movie_id
                     WHERE mf.status = ?
                     ORDER BY mf.created_at DESC";
    $stmt = $conn->prepare($feedback_sql);
    $stmt->bind_param("s", $status_filter);
    $stmt->execute();
    $feedback_result = $stmt->get_result();
} else {
    $feedback_sql = "SELECT mf.*, u.username, u.email, mo.title as movie_title, mo.poster
                     FROM movie_feedback mf
                     JOIN users u ON mf.user_id = u.user_id
                     JOIN movies mo ON mf.movie_id = mo.movie_id
                     ORDER BY mf.created_at DESC";
    $feedback_result = $conn->query($feedback_sql);
}
$feedbacks = $feedback_result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as avg_rating,
                COUNT(DISTINCT user_id) as unique_reviewers,
                COUNT(DISTINCT movie_id) as movies_reviewed,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_reviews,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_reviews
              FROM movie_feedback";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feedback Management - ARVR Cinema</title>
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
        
        .btn {
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .btn-primary {
            background: #ff4d6d;
            color: white;
        }
        
        .btn-primary:hover {
            background: #ff6b8a;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-success:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        
        .filter-container {
            padding: 20px 40px;
        }
        
        .filter-box {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        
        .filter-group label {
            font-size: 12px;
            opacity: 0.7;
            display: block;
            margin-bottom: 5px;
        }
        
        .filter-group select {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.5);
            color: white;
            font-family: 'Poppins', sans-serif;
        }
        
        .feedback-section {
            padding: 20px 40px;
        }
        
        .feedback-grid {
            display: grid;
            gap: 20px;
        }
        
        .feedback-card {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 20px;
            transition: 0.3s;
        }
        
        .feedback-card:hover {
            background: rgba(255,255,255,0.08);
            transform: translateX(5px);
        }
        
        .feedback-card.pending {
            border-left: 4px solid #ffc107;
        }
        
        .feedback-card.approved {
            border-left: 4px solid #4CAF50;
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .user-info-card {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, #ff4d6d, #ff8c00);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .user-details h4 {
            margin-bottom: 5px;
        }
        
        .user-details small {
            opacity: 0.7;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .status-badge.approved {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        
        .movie-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(0,0,0,0.3);
            border-radius: 10px;
        }
        
        .movie-info img {
            width: 40px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .rating {
            color: #FFD700;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .comment {
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .feedback-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            opacity: 0.6;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
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
        
        .no-feedback {
            text-align: center;
            padding: 50px;
            opacity: 0.7;
        }
        
        @media (max-width: 768px) {
            .staff-header {
                padding: 15px 20px;
            }
            
            .stats-container,
            .feedback-section,
            .filter-container {
                padding: 15px 20px;
            }
            
            .filter-box {
                flex-direction: column;
            }
            
            .feedback-header {
                flex-direction: column;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="staff-header">
        <div class="logo">
            <h1>💬 Feedback Management</h1>
            <p>View, approve, and manage user movie reviews</p>
        </div>
        <div class="user-info">
            <span class="staff-badge"><?php echo strtoupper($role); ?></span>
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
            <a href="staff_homepage.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="stats-container">
        <div class="stat-card">
            <i class="fas fa-star"></i>
            <div class="number"><?php echo $stats['total_reviews']; ?></div>
            <div>Total Reviews</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-chart-line"></i>
            <div class="number"><?php echo number_format($stats['avg_rating'], 1); ?></div>
            <div>Average Rating</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-clock"></i>
            <div class="number"><?php echo $stats['pending_reviews']; ?></div>
            <div>Pending Approval</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-check-circle"></i>
            <div class="number"><?php echo $stats['approved_reviews']; ?></div>
            <div>Approved</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <div class="number"><?php echo $stats['unique_reviewers']; ?></div>
            <div>Reviewers</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-film"></i>
            <div class="number"><?php echo $stats['movies_reviewed']; ?></div>
            <div>Movies Reviewed</div>
        </div>
    </div>
    
    <!-- Filter -->
    <div class="filter-container">
        <div class="filter-box">
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> Filter by Status</label>
                <select name="status" id="statusFilter" onchange="window.location.href='?status='+this.value">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Reviews</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="feedback_management.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Reset Filter
                </a>
            </div>
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
    
    <!-- Feedback List -->
    <div class="feedback-section">
        <div class="feedback-grid">
            <?php if (count($feedbacks) > 0): ?>
                <?php foreach ($feedbacks as $feedback): ?>
                <div class="feedback-card <?php echo $feedback['status']; ?>">
                    <div class="feedback-header">
                        <div class="user-info-card">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="user-details">
                                <h4><?php echo htmlspecialchars($feedback['username']); ?></h4>
                                <small><?php echo htmlspecialchars($feedback['email']); ?></small>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <span class="status-badge <?php echo $feedback['status']; ?>">
                                <i class="fas <?php echo $feedback['status'] == 'pending' ? 'fa-clock' : 'fa-check-circle'; ?>"></i>
                                <?php echo ucfirst($feedback['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="movie-info">
                        <img src="<?php echo htmlspecialchars($feedback['poster']); ?>" 
                             onerror="this.src='https://via.placeholder.com/40x60'">
                        <div>
                            <strong>Movie:</strong> <?php echo htmlspecialchars($feedback['movie_title']); ?>
                        </div>
                    </div>
                    
                    <div class="rating">
                        <?php 
                        $rating = $feedback['rating'];
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $rating) {
                                echo '<i class="fas fa-star"></i>';
                            } elseif ($i - 0.5 <= $rating) {
                                echo '<i class="fas fa-star-half-alt"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
                        <span style="color: white; margin-left: 10px;">(<?php echo $rating; ?>/5)</span>
                    </div>
                    
                    <div class="comment">
                                        <?php echo nl2br(htmlspecialchars($feedback['comment'])); ?>
                    </div>
                    
                    <div class="feedback-meta">
                        <span><i class="fas fa-calendar"></i> <?php echo date('F j, Y, g:i a', strtotime($feedback['created_at'])); ?></span>
                        <div class="action-buttons">
                            <?php if ($feedback['status'] == 'pending'): ?>
                                <a href="?approve=<?php echo $feedback['feedback_id']; ?>&status=<?php echo $status_filter; ?>" 
                                   class="btn btn-success" 
                                   onclick="return confirm('Approve this feedback?')">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $feedback['feedback_id']; ?>&status=<?php echo $status_filter; ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Delete this feedback? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-feedback">
                    <i class="fas fa-comment-slash" style="font-size: 48px; margin-bottom: 20px;"></i>
                    <h3>No feedback found</h3>
                    <p>No reviews match your current filter criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>