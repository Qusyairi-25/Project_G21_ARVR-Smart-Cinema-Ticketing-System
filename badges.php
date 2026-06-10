<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Function to get user's stats
function getUserStats($conn, $user_id) {
    $stats = [];
    
    // Get reviews count
    $review_sql = "SELECT COUNT(*) as count FROM movie_feedback WHERE user_id = ?";
    $review_stmt = $conn->prepare($review_sql);
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $stats['total_reviews'] = $review_stmt->get_result()->fetch_assoc()['count'];
    
    // Get ALL bookings count (including cancelled - for badge progress)
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
    
    // Get genre counts from bookings (confirmed)
    $genre_sql = "SELECT m.genre, COUNT(*) as count 
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
        $stats['genre_counts'][strtolower(trim($row['genre']))] = $row['count'];
    }
    
    return $stats;
}

// Handle discount usage
if (isset($_POST['use_discount'])) {
    $discount_name = $_POST['discount_name'];
    $discount_type = $_POST['discount_type'];
    $discount_value = $_POST['discount_value'];
    
    $_SESSION['active_discount'] = [
        'badge_name' => $discount_name,
        'discount_type' => $discount_type,
        'discount_value' => $discount_value,
        'used_at' => date('Y-m-d H:i:s')
    ];
    
    $success_message = "Discount applied! " . $discount_name . " is ready for checkout.";
}

// Get user stats
$user_stats = getUserStats($conn, $user_id);

// Get all badges from database (including allows_early_access column)
$badges_sql = "SELECT * FROM badges WHERE is_active = TRUE ORDER BY requirement_value ASC";
$badges_result = $conn->query($badges_sql);
$all_badges = $badges_result->fetch_all(MYSQLI_ASSOC);

// Calculate which badges are earned based on current stats
$earned_badges = [];
$has_early_access = false;
$early_access_badges = [];

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
        // Check if this badge grants early access
        if (isset($badge['allows_early_access']) && $badge['allows_early_access'] == 1) {
            $has_early_access = true;
            $early_access_badges[] = $badge['badge_name'];
        }
    }
}

// Get badge IDs that are earned
$earned_ids = array_column($earned_badges, 'badge_id');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Badges & Rewards - ARVR Cinema</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">

    <!-- FONTS -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    
    <!-- FONTAWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS FILES -->
    <link rel="stylesheet" href="base.css">
    <link rel="stylesheet" href="sidebar.css">
    
    <style>
        /* Additional styles for badges page */
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

        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
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
            font-size: 35px;
            color: #ff4d6d;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 28px;
            font-weight: 700;
        }

        .stat-card .label {
            font-size: 12px;
            opacity: 0.7;
            margin-top: 5px;
        }

        /* Section Titles */
        .section-title {
            font-size: 24px;
            margin: 30px 0 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #ff4d6d;
        }

        /* Badges Grid */
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .badge-card {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 25px;
            transition: 0.3s;
            position: relative;
        }

        .badge-card.earned {
            border: 2px solid #4CAF50;
            background: linear-gradient(135deg, rgba(76,175,80,0.1), rgba(255,77,109,0.05));
        }

        .badge-card.locked {
            opacity: 0.6;
        }

        .badge-card:hover {
            transform: translateY(-5px);
        }

        .badge-icon {
            width: 70px;
            height: 70px;
            background: rgba(255,77,109,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .badge-icon i {
            font-size: 35px;
            color: #ff4d6d;
        }

        .badge-card.earned .badge-icon {
            background: rgba(76, 175, 80, 0.2);
            box-shadow: 0 0 15px rgba(76, 175, 80, 0.3);
        }

        .badge-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .badge-description {
            font-size: 13px;
            opacity: 0.7;
            margin-bottom: 15px;
        }

        .discount-box {
            background: rgba(255,193,7,0.2);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            margin: 15px 0;
        }

        .discount-value {
            font-size: 20px;
            font-weight: 700;
            color: #FFC107;
        }

        .early-access-badge {
            background: rgba(33,150,243,0.3);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            text-align: center;
            margin-top: 10px;
            color: #2196F3;
        }

        .early-access-badge i {
            font-size: 11px;
            margin-right: 5px;
        }

        .requirement {
            font-size: 12px;
            color: #ff4d6d;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            margin-top: 12px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff4d6d, #ff8c00);
            border-radius: 4px;
            transition: width 0.3s;
        }

        .use-btn {
            width: 100%;
            margin-top: 15px;
            padding: 12px;
            background: linear-gradient(45deg, #4CAF50, #45a049);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            transition: 0.3s;
        }

        .use-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .notification {
            background: linear-gradient(135deg, #ff4d6d, #ff8c00);
            padding: 18px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .active-discount {
            background: rgba(255,193,7,0.2);
            border: 1px solid #FFC107;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .message {
            padding: 15px 20px;
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

        .earned-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #4CAF50;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: 0.3s;
        }

        .back-btn:hover {
            background: #ff4d6d;
        }

        .early-access-notification {
            background: rgba(33,150,243,0.2);
            border-left: 4px solid #2196F3;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .badges-grid {
                grid-template-columns: 1fr;
            }
            
            .topbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .topbar input {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="dashboard">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <h2 class="logo-text">ARVR</h2>
        <ul class="menu">
            <li><a href="homepage.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist</a></li>
            <li><a href="genres.php"><i class="fas fa-tags"></i> Genres</a></li>
            <li><a href="coming-soon.php"><i class="fas fa-clock"></i> Coming Soon</a></li>
            <li class="active"><a href="badges.php"><i class="fas fa-medal"></i> Badges</a></li>
            <li><a href="ticket.php"><i class="fas fa-ticket-alt"></i> My Tickets</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
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
                    <?php endif; ?>
                </span>
                
                <button class="profile-btn" onclick="location.href='profile.php'">
                    <img src="profileIcon.jpg" alt="Profile">
                </button>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Back to Home Button -->
        <a href="homepage.php" class="back-btn">
            ← Back to Home
        </a>

        <!-- Early Access Notification -->
        <?php if ($has_early_access): ?>
            <div class="early-access-notification">
                <i class="fas fa-crown" style="font-size: 24px; color: #ffd700;"></i>
                <div>
                    <strong>🎟️ VIP Early Access Unlocked!</strong><br>
                    You have early access to book "Coming Soon" movies before official release!
                    Badges granting access: <?php echo implode(', ', $early_access_badges); ?>
                </div>
            </div>
        <?php endif; ?>

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

        <?php if (isset($_SESSION['active_discount'])): ?>
            <div class="active-discount">
                <div>
                    🎫 <strong>Active Discount:</strong> <?php echo $_SESSION['active_discount']['badge_name']; ?> - 
                    <?php if ($_SESSION['active_discount']['discount_type'] == 'percentage'): ?>
                        <?php echo $_SESSION['active_discount']['discount_value']; ?>% OFF
                    <?php else: ?>
                        RM<?php echo $_SESSION['active_discount']['discount_value']; ?> OFF
                    <?php endif; ?>
                </div>
                <small>Valid for your next booking</small>
            </div>
        <?php endif; ?>

        <!-- Stats Summary -->
        <div class="stats-container">
            <div class="stat-card">
                <i class="fas fa-star"></i>
                <div class="number"><?php echo $user_stats['total_reviews']; ?></div>
                <div class="label">Reviews Written</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-ticket-alt"></i>
                <div class="number"><?php echo $user_stats['total_bookings']; ?></div>
                <div class="label">Movies Watched</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-medal"></i>
                <div class="number"><?php echo count($earned_badges); ?></div>
                <div class="label">Badges Earned</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-heart"></i>
                <div class="number"><?php echo $user_stats['total_wishlist']; ?></div>
                <div class="label">Wishlist Items</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-money-bill-wave"></i>
                <div class="number">RM<?php echo number_format($user_stats['total_spent'], 0); ?></div>
                <div class="label">Total Spent</div>
            </div>
        </div>

        <!-- Earned Badges Section -->
        <?php if (!empty($earned_badges)): ?>
            <h2 class="section-title">
                <i class="fas fa-trophy"></i> My Earned Badges & Discounts
            </h2>
            <div class="badges-grid">
                <?php foreach ($earned_badges as $badge): ?>
                    <div class="badge-card earned">
                        <div class="earned-badge">✓ EARNED</div>
                        <div class="badge-icon">
                            <i class="fas <?php echo $badge['badge_icon']; ?>"></i>
                        </div>
                        <div class="badge-name"><?php echo $badge['badge_name']; ?></div>
                        <div class="badge-description"><?php echo $badge['badge_description']; ?></div>
                        
                        <?php if (isset($badge['allows_early_access']) && $badge['allows_early_access'] == 1): ?>
                            <div class="early-access-badge">
                                <i class="fas fa-crown"></i> Grants VIP Early Access to Coming Soon movies!
                            </div>
                        <?php endif; ?>
                        
                        <div class="discount-box">
                            <div class="discount-value">
                                💰 
                                <?php if ($badge['discount_type'] == 'percentage'): ?>
                                    <?php echo $badge['discount_value']; ?>% OFF
                                <?php else: ?>
                                    RM<?php echo $badge['discount_value']; ?> OFF
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="discount_name" value="<?php echo $badge['badge_name']; ?>">
                            <input type="hidden" name="discount_type" value="<?php echo $badge['discount_type']; ?>">
                            <input type="hidden" name="discount_value" value="<?php echo $badge['discount_value']; ?>">
                            <button type="submit" name="use_discount" class="use-btn">
                                🎫 Use Discount Now
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="notification" style="background: rgba(255,77,109,0.2);">
                <i class="fas fa-info-circle" style="font-size: 30px;"></i>
                <div>
                    <h3>No badges earned yet!</h3>
                    <p>Keep watching movies, writing reviews, and adding to wishlist to earn badges and discounts!</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Badges to Unlock Section -->
        <?php if (count($all_badges) > count($earned_badges)): ?>
            <h2 class="section-title">
                <i class="fas fa-lock"></i> Badges to Unlock
            </h2>
            <div class="badges-grid">
                <?php 
                foreach ($all_badges as $badge): 
                    $is_earned = in_array($badge['badge_id'], $earned_ids);
                    if ($is_earned) continue;
                    
                    // Calculate progress for this badge
                    $current = 0;
                    $target = $badge['requirement_value'];
                    
                    switch ($badge['requirement_type']) {
                        case 'reviews': $current = $user_stats['total_reviews']; break;
                        case 'bookings': $current = $user_stats['total_bookings']; break;
                        case 'wishlist': $current = $user_stats['total_wishlist']; break;
                        case 'spending': $current = $user_stats['total_spent']; break;
                        case 'genre_horror': $current = $user_stats['genre_counts']['horror'] ?? 0; break;
                        case 'genre_action': $current = $user_stats['genre_counts']['action'] ?? 0; break;
                        case 'genre_romance': $current = $user_stats['genre_counts']['romance'] ?? 0; break;
                        case 'genre_comedy': $current = $user_stats['genre_counts']['comedy'] ?? 0; break;
                    }
                    
                    $progress = min(($current / $target) * 100, 100);
                ?>
                    <div class="badge-card locked">
                        <div class="badge-icon">
                            <i class="fas <?php echo $badge['badge_icon']; ?>"></i>
                        </div>
                        <div class="badge-name"><?php echo $badge['badge_name']; ?></div>
                        <div class="badge-description"><?php echo $badge['badge_description']; ?></div>
                        
                        <?php if (isset($badge['allows_early_access']) && $badge['allows_early_access'] == 1): ?>
                            <div class="early-access-badge">
                                <i class="fas fa-crown"></i> Unlock to get VIP Early Access!
                            </div>
                        <?php endif; ?>
                        
                        <div class="discount-box">
                            <div class="discount-value">
                                🎁 Unlock: <?php echo $badge['discount_type'] == 'percentage' ? $badge['discount_value'] . '% OFF' : 'RM' . $badge['discount_value'] . ' OFF'; ?>
                            </div>
                        </div>
                        
                        <div class="requirement">
                            📊 Progress: <?php echo $current; ?> / <?php echo $target; ?>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- All Badges Unlocked Message -->
        <?php if (count($all_badges) == count($earned_badges) && !empty($earned_badges)): ?>
            <div class="notification" style="background: rgba(76, 175, 80, 0.2); border: 1px solid #4CAF50;">
                <i class="fas fa-crown" style="font-size: 35px;"></i>
                <div>
                    <h3>🎉 Ultimate Collector!</h3>
                    <p>Congratulations! You've unlocked every badge! You're a true ARVR Cinema legend!</p>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
// Search functionality
document.querySelector('.topbar input').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const badges = document.querySelectorAll('.badge-card');
    
    badges.forEach(badge => {
        const name = badge.querySelector('.badge-name')?.textContent.toLowerCase();
        const desc = badge.querySelector('.badge-description')?.textContent.toLowerCase();
        
        if (name?.includes(searchTerm) || desc?.includes(searchTerm)) {
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    });
});
</script>

</body>
</html>