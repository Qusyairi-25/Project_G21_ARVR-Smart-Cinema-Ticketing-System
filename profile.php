<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Handle profile update
if (isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // First verify current password
    $check_sql = "SELECT password FROM users WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $user_data = $result->fetch_assoc();
    
    if ($current_password == $user_data['password']) {
        // Update basic info
        $update_sql = "UPDATE users SET username = ?, email = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $new_username, $new_email, $user_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['username'] = $new_username;
            $success_message = "Profile updated successfully!";
            
            // Update password if provided
            if (!empty($new_password)) {
                if ($new_password == $confirm_password) {
                    $update_pass_sql = "UPDATE users SET password = ? WHERE user_id = ?";
                    $update_pass_stmt = $conn->prepare($update_pass_sql);
                    $update_pass_stmt->bind_param("si", $new_password, $user_id);
                    if ($update_pass_stmt->execute()) {
                        $success_message = "Profile and password updated successfully!";
                    }
                } else {
                    $error_message = "New passwords do not match!";
                }
            }
        } else {
            $error_message = "Error updating profile.";
        }
    } else {
        $error_message = "Current password is incorrect!";
    }
}

// Get user details
$user_sql = "SELECT * FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Get user statistics and badge data for all roles
function getUserStatsForBadges($conn, $user_id) {
    $stats = [];
    
    // Get reviews count
    $review_sql = "SELECT COUNT(*) as count FROM movie_feedback WHERE user_id = ?";
    $review_stmt = $conn->prepare($review_sql);
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $stats['total_reviews'] = $review_stmt->get_result()->fetch_assoc()['count'];
    
    // Get bookings count
    $booking_sql = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ?";
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("i", $user_id);
    $booking_stmt->execute();
    $stats['total_bookings'] = $booking_stmt->get_result()->fetch_assoc()['count'];
    
    // Get total spent (only completed bookings)
    $spent_sql = "SELECT SUM(total_price) as total FROM bookings WHERE user_id = ? AND status = 'confirmed'";
    $spent_stmt = $conn->prepare($spent_sql);
    $spent_stmt->bind_param("i", $user_id);
    $spent_stmt->execute();
    $result = $spent_stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['total_spent'] = $row['total'] ?? 0;
    
    // Get wishlist count
    $wishlist_sql = "SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?";
    $wishlist_stmt = $conn->prepare($wishlist_sql);
    $wishlist_stmt->bind_param("i", $user_id);
    $wishlist_stmt->execute();
    $stats['total_wishlist'] = $wishlist_stmt->get_result()->fetch_assoc()['count'];
    
    // Get genre counts from watched movies (via bookings)
    $genre_sql = "SELECT DISTINCT m.genre, COUNT(*) as count 
                  FROM bookings b
                  JOIN movies m ON b.movie_id = m.movie_id 
                  WHERE b.user_id = ? AND b.status = 'confirmed'
                  GROUP BY m.genre";
    $genre_stmt = $conn->prepare($genre_sql);
    $genre_stmt->bind_param("i", $user_id);
    $genre_stmt->execute();
    $genre_result = $genre_stmt->get_result();
    $stats['genre_counts'] = [];
    while ($row = $genre_result->fetch_assoc()) {
        $stats['genre_counts'][strtolower($row['genre'])] = $row['count'];
    }
    
    return $stats;
}

// Get user statistics based on role for display
if ($role == 'user') {
    // Get user's booking history
    $booking_sql = "SELECT COUNT(*) as total_bookings FROM bookings WHERE user_id = ?";
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("i", $user_id);
    $booking_stmt->execute();
    $booking_count = $booking_stmt->get_result()->fetch_assoc()['total_bookings'];
    
    // Get user's reviews
    $review_sql = "SELECT COUNT(*) as total_reviews FROM movie_feedback WHERE user_id = ?";
    $review_stmt = $conn->prepare($review_sql);
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $review_count = $review_stmt->get_result()->fetch_assoc()['total_reviews'];
    
    // Get wishlist count
    $wishlist_sql = "SELECT COUNT(*) as total_wishlist FROM wishlist WHERE user_id = ?";
    $wishlist_stmt = $conn->prepare($wishlist_sql);
    $wishlist_stmt->bind_param("i", $user_id);
    $wishlist_stmt->execute();
    $wishlist_count = $wishlist_stmt->get_result()->fetch_assoc()['total_wishlist'];
    
    // Get badge data
    $user_stats = getUserStatsForBadges($conn, $user_id);
    
    // Get all badges from database
    $badges_sql = "SELECT * FROM badges WHERE is_active = TRUE ORDER BY requirement_value ASC";
    $badges_result = $conn->query($badges_sql);
    $all_badges = $badges_result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate which badges are earned
    $earned_badges = [];
    foreach ($all_badges as $badge) {
        $earned = false;
        
        switch ($badge['requirement_type']) {
            case 'reviews':
                if ($user_stats['total_reviews'] >= $badge['requirement_value']) $earned = true;
                break;
            case 'bookings':
                if ($user_stats['total_bookings'] >= $badge['requirement_value']) $earned = true;
                break;
            case 'wishlist':
                if ($user_stats['total_wishlist'] >= $badge['requirement_value']) $earned = true;
                break;
            case 'spending':
                if ($user_stats['total_spent'] >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_horror':
                if (($user_stats['genre_counts']['horror'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_action':
                if (($user_stats['genre_counts']['action'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_romance':
                if (($user_stats['genre_counts']['romance'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_comedy':
                if (($user_stats['genre_counts']['comedy'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
        }
        
        if ($earned) {
            $earned_badges[] = $badge;
        }
    }
    
} elseif ($role == 'staff') {
    // Staff statistics
    $total_movies_sql = "SELECT COUNT(*) as total FROM movies";
    $total_movies = $conn->query($total_movies_sql)->fetch_assoc()['total'];
    
    $total_users_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
    $total_users = $conn->query($total_users_sql)->fetch_assoc()['total'];
    
    $total_reviews_sql = "SELECT COUNT(*) as total FROM movie_feedback";
    $total_reviews = $conn->query($total_reviews_sql)->fetch_assoc()['total'];
} elseif ($role == 'admin') {
    // Admin statistics
    $total_movies_sql = "SELECT COUNT(*) as total FROM movies";
    $total_movies = $conn->query($total_movies_sql)->fetch_assoc()['total'];
    
    $total_users_sql = "SELECT COUNT(*) as total FROM users";
    $total_users = $conn->query($total_users_sql)->fetch_assoc()['total'];
    
    $total_staff_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'staff'";
    $total_staff = $conn->query($total_staff_sql)->fetch_assoc()['total'];
    
    $total_reviews_sql = "SELECT COUNT(*) as total FROM movie_feedback";
    $total_reviews = $conn->query($total_reviews_sql)->fetch_assoc()['total'];
    
    $total_bookings_sql = "SELECT COUNT(*) as total FROM bookings";
    $total_bookings = $conn->query($total_bookings_sql)->fetch_assoc()['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - <?php echo ucfirst($role); ?></title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    
    <!-- FONTS -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- FONTAWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS FILES -->
    <link rel="stylesheet" href="base.css">
    <link rel="stylesheet" href="sidebar.css">
    
    <style>
        /* Additional styles for profile page */
        .main {
            margin-left: 220px;
            padding: 30px;
            min-height: 100vh;
        }

        /* Top Bar */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .topbar input {
            width: 300px;
            padding: 10px 15px;
            border-radius: 25px;
            border: none;
            background: rgba(255,255,255,0.1);
            color: white;
            font-family: 'Poppins', sans-serif;
        }

        .topbar input::placeholder {
            color: rgba(255,255,255,0.5);
        }

        .user-welcome {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-btn {
            background: none;
            border: none;
            cursor: pointer;
        }

        .profile-btn img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }

        .logout-btn {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: #800020;
            border-radius: 5px;
            transition: 0.3s;
        }

        .logout-btn:hover {
            background: #ff4d6d;
        }

        .role-badge {
            background: #ff4d6d;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        /* Profile Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        
        /* Profile Card */
        .profile-card {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            position: sticky;
            top: 20px;
        }
        
        .avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(45deg, #ff4d6d, #ff8c00);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .avatar i {
            font-size: 50px;
            color: white;
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .profile-email {
            color: #ff4d6d;
            margin-bottom: 15px;
        }
        
        .member-since {
            font-size: 12px;
            opacity: 0.7;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: rgba(255,255,255,0.05);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-box i {
            font-size: 30px;
            color: #ff4d6d;
            margin-bottom: 10px;
        }
        
        .stat-box .number {
            font-size: 28px;
            font-weight: 700;
        }
        
        .stat-box .label {
            font-size: 12px;
            opacity: 0.7;
        }
        
        /* Badges Section */
        .badges-section {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .badges-section h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        .earned-badges-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .earned-badge-item {
            background: linear-gradient(135deg, rgba(76,175,80,0.15), rgba(76,175,80,0.05));
            border: 1px solid rgba(76,175,80,0.3);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            min-width: 120px;
            flex: 1;
            transition: 0.3s;
            cursor: pointer;
        }
        
        .earned-badge-item:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, rgba(76,175,80,0.25), rgba(76,175,80,0.1));
        }
        
        .earned-badge-icon {
            width: 50px;
            height: 50px;
            background: rgba(76,175,80,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
        }
        
        .earned-badge-icon i {
            font-size: 25px;
            color: #4CAF50;
        }
        
        .earned-badge-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .earned-badge-discount {
            font-size: 11px;
            color: #FFC107;
        }
        
        .no-badges {
            text-align: center;
            padding: 30px;
            color: rgba(255,255,255,0.5);
        }
        
        .no-badges i {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .view-all-badges {
            display: inline-block;
            margin-top: 15px;
            color: #ff4d6d;
            text-decoration: none;
            font-size: 13px;
        }
        
        .view-all-badges:hover {
            text-decoration: underline;
        }
        
        /* Form */
        .edit-form {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 30px;
        }
        
        .edit-form h2 {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: white;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #ff4d6d;
        }
        
        .form-group input[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #800020, #ff4d6d);
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
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
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
        
        hr {
            margin: 20px 0;
            border: none;
            height: 1px;
            background: rgba(255,255,255,0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                padding: 20px;
            }
            
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .topbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .topbar input {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .earned-badges-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="dashboard">
    <!-- SIDEBAR - Same for all roles -->
    <div class="sidebar">
        <h2 class="logo-text">ARVR</h2>
        <ul class="menu">
            <li><a href="homepage.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist</a></li>
            <li><a href="genres.php"><i class="fas fa-tags"></i> Genres</a></li>
            <li><a href="coming-soon.php"><i class="fas fa-clock"></i> Coming Soon</a></li>
            <li><a href="badges.php"><i class="fas fa-medal"></i> Badges</a></li>
            <li><a href="ticket.php"><i class="fas fa-ticket-alt"></i> My Tickets</a></li>
            <li class="active"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
        </ul>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main">

        <!-- TOP BAR -->
        <div class="topbar">
            <form action="search.php" method="GET" style="margin: 0;">
                
            </form>
            
            <div class="user-welcome">
                <span style="color: #ffffff;">
                    Welcome, <?php echo htmlspecialchars($username); ?>!
                    <?php if ($role == 'staff'): ?>
                        <span class="role-badge">Staff Member</span>
                    <?php elseif ($role == 'admin'): ?>
                        <span class="role-badge" style="background: #ffd700; color: #333;">Administrator</span>
                    <?php else: ?>
                        <span class="role-badge">Member</span>
                    <?php endif; ?>
                </span>
                
                <button class="profile-btn" onclick="location.href='profile.php'">
                    <img src="profileIcon.jpg" alt="Profile">
                </button>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

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
        
        <div class="profile-grid">
            <!-- Left Column - Profile Card -->
            <div class="profile-card">
                <div class="avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h2 class="profile-name"><?php echo htmlspecialchars($user['username']); ?></h2>
                <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
                <span class="role-badge" style="<?php echo $role == 'admin' ? 'background: #ffd700; color: #333;' : ($role == 'staff' ? 'background: #4a5568;' : 'background: #2196F3;'); ?>">
                    <i class="fas fa-shield-alt"></i> <?php echo strtoupper($role); ?>
                </span>
                <div class="member-since">
                    <i class="fas fa-calendar-alt"></i> Member since<br>
                    <?php echo date('F j, Y', strtotime($user['user_id'] + 1000000)); ?>
                </div>
            </div>
            
            <!-- Right Column - Content -->
            <div>
                <!-- Statistics Section based on role -->
                <?php if ($role == 'user'): ?>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <i class="fas fa-ticket-alt"></i>
                            <div class="number"><?php echo $booking_count ?? 0; ?></div>
                            <div class="label">Bookings</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-star"></i>
                            <div class="number"><?php echo $review_count ?? 0; ?></div>
                            <div class="label">Reviews</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-heart"></i>
                            <div class="number"><?php echo $wishlist_count ?? 0; ?></div>
                            <div class="label">Wishlist</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-medal"></i>
                            <div class="number"><?php echo count($earned_badges); ?></div>
                            <div class="label">Badges Earned</div>
                        </div>
                    </div>
                    
                    <!-- Earned Badges Section -->
                    <div class="badges-section">
                        <h2>
                            <i class="fas fa-medal" style="color: #ff4d6d;"></i> 
                            My Earned Badges
                        </h2>
                        
                        <?php if (!empty($earned_badges)): ?>
                            <div class="earned-badges-container">
                                <?php foreach ($earned_badges as $badge): ?>
                                    <div class="earned-badge-item" onclick="location.href='badges.php'" style="cursor: pointer;">
                                        <div class="earned-badge-icon">
                                            <i class="fas <?php echo $badge['badge_icon']; ?>"></i>
                                        </div>
                                        <div class="earned-badge-name"><?php echo htmlspecialchars($badge['badge_name']); ?></div>
                                        <div class="earned-badge-discount">
                                            <?php if ($badge['discount_type'] == 'percentage'): ?>
                                                🎫 <?php echo $badge['discount_value']; ?>% OFF
                                            <?php else: ?>
                                                🎫 RM<?php echo $badge['discount_value']; ?> OFF
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="badges.php" class="view-all-badges">
                                View all badges <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <div class="no-badges">
                                <i class="fas fa-medal"></i>
                                <p>No badges earned yet!</p>
                                <a href="badges.php" class="view-all-badges">
                                    See how to earn badges <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                <?php elseif ($role == 'staff'): ?>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <i class="fas fa-film"></i>
                            <div class="number"><?php echo $total_movies ?? 0; ?></div>
                            <div class="label">Total Movies</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-users"></i>
                            <div class="number"><?php echo $total_users ?? 0; ?></div>
                            <div class="label">Active Users</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-star"></i>
                            <div class="number"><?php echo $total_reviews ?? 0; ?></div>
                            <div class="label">Total Reviews</div>
                        </div>
                    </div>
                <?php elseif ($role == 'admin'): ?>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <i class="fas fa-film"></i>
                            <div class="number"><?php echo $total_movies ?? 0; ?></div>
                            <div class="label">Movies</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-users"></i>
                            <div class="number"><?php echo $total_users ?? 0; ?></div>
                            <div class="label">Total Users</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-user-tie"></i>
                            <div class="number"><?php echo $total_staff ?? 0; ?></div>
                            <div class="label">Staff Members</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-star"></i>
                            <div class="number"><?php echo $total_reviews ?? 0; ?></div>
                            <div class="label">Reviews</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-ticket-alt"></i>
                            <div class="number"><?php echo $total_bookings ?? 0; ?></div>
                            <div class="label">Total Bookings</div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Edit Profile Form -->
                <div class="edit-form">
                    <h2><i class="fas fa-edit"></i> Edit Profile</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <hr>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Current Password</label>
                            <input type="password" name="current_password" placeholder="Enter current password to make changes" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password (optional)</label>
                            <input type="password" name="new_password" placeholder="Leave blank to keep current password">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-check"></i> Confirm New Password</label>
                            <input type="password" name="confirm_password" placeholder="Re-enter new password">
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
                
                <!-- Role-specific additional features -->
                <?php if ($role == 'admin'): ?>
                    <div class="edit-form" style="margin-top: 20px;">
                        <h2><i class="fas fa-shield-alt"></i> Admin Actions</h2>
                        <div class="action-buttons">
                            <a href="staff_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-tachometer-alt"></i> Staff Dashboard
                            </a>
                            <a href="add_movie.php" class="btn btn-secondary">
                                <i class="fas fa-plus"></i> Add Movie
                            </a>
                            <a href="discount_management.php" class="btn btn-secondary">
                                <i class="fas fa-tags"></i> Discount Codes
                            </a>
                            <a href="view_feedback_all.php" class="btn btn-secondary">
                                <i class="fas fa-comments"></i> View All Feedback
                            </a>
                        </div>
                    </div>
                <?php elseif ($role == 'staff'): ?>
                    <div class="edit-form" style="margin-top: 20px;">
                        <h2><i class="fas fa-tasks"></i> Staff Actions</h2>
                        <div class="action-buttons">
                            <a href="staff_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-tachometer-alt"></i> Staff Dashboard
                            </a>
                            <a href="add_movie.php" class="btn btn-secondary">
                                <i class="fas fa-plus"></i> Add Movie
                            </a>
                            <a href="view_feedback_all.php" class="btn btn-secondary">
                                <i class="fas fa-comments"></i> View Feedback
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="edit-form" style="margin-top: 20px;">
                        <h2><i class="fas fa-heart"></i> My Activity</h2>
                        <div class="action-buttons">
                            <a href="ticket.php" class="btn btn-secondary">
                                <i class="fas fa-ticket-alt"></i> My Bookings
                            </a>
                            <a href="wishlist.php" class="btn btn-secondary">
                                <i class="fas fa-heart"></i> My Wishlist
                            </a>
                            <a href="badges.php" class="btn btn-secondary">
                                <i class="fas fa-medal"></i> My Badges
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Search functionality
document.querySelector('.topbar input')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    // Add search functionality if needed
});
</script>

</body>
</html>