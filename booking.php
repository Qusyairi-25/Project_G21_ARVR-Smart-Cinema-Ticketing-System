<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include 'db.php';
include 'early_access.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// ========== BADGE DISCOUNT FUNCTIONS ==========

// Function to get user's earned badges from database
function getUserEarnedBadges($conn, $user_id) {
    $stats = [];
    
    // Get reviews count
    $review_sql = "SELECT COUNT(*) as count FROM movie_feedback WHERE user_id = ?";
    $review_stmt = $conn->prepare($review_sql);
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $stats['total_reviews'] = $review_stmt->get_result()->fetch_assoc()['count'];
    
    // Get ALL bookings count (including cancelled, pending, confirmed)
    $booking_sql = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ?";
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("i", $user_id);
    $booking_stmt->execute();
    $stats['total_bookings'] = $booking_stmt->get_result()->fetch_assoc()['count'];
    
    // Get total spent (only confirmed payments should count for spending)
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
    
    // Get genre counts from watched movies (only confirmed bookings for genre badges)
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
    
    // Get all active badges from database
    $badges_sql = "SELECT * FROM badges WHERE is_active = 1 ORDER BY requirement_value ASC";
    $badges_result = $conn->query($badges_sql);
    $all_badges = $badges_result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate earned badges based on user stats
    $earned_badges = [];
    foreach ($all_badges as $badge) {
        $earned = false;
        
        switch ($badge['requirement_type']) {
            case 'reviews':
                if ($stats['total_reviews'] >= $badge['requirement_value']) $earned = true;
                break;
            case 'bookings':
                if ($stats['total_bookings'] >= $badge['requirement_value']) $earned = true;
                break;
            case 'wishlist':
                if ($stats['total_wishlist'] >= $badge['requirement_value']) $earned = true;
                break;
            case 'spending':
                if ($stats['total_spent'] >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_horror':
                if (($stats['genre_counts']['horror'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_action':
                if (($stats['genre_counts']['action'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_romance':
                if (($stats['genre_counts']['romance'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_comedy':
                if (($stats['genre_counts']['comedy'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
        }
        
        if ($earned) {
            $earned_badges[] = $badge;
        }
    }
    
    return $earned_badges;
}

// Function to calculate discount from a badge
function calculateDiscountFromBadge($badge, $ticket_price, $seat_count = 1) {
    $total_original = $ticket_price * $seat_count;
    
    if ($badge['discount_type'] == 'percentage') {
        $discount_amount = $total_original * ($badge['discount_value'] / 100);
        $final_price = $total_original - $discount_amount;
        $price_per_ticket = $final_price / $seat_count;
    } else {
        $discount_amount = min($badge['discount_value'] * $seat_count, $total_original);
        $final_price = $total_original - $discount_amount;
        $price_per_ticket = $final_price / $seat_count;
    }
    
    return [
        'discount_amount' => $discount_amount,
        'final_price' => $final_price,
        'price_per_ticket' => $price_per_ticket
    ];
}

// ========== END BADGE DISCOUNT FUNCTIONS ==========

// Get movie ID from URL
$movie_id = isset($_GET['movie_id']) ? (int)$_GET['movie_id'] : (isset($_POST['movie_id']) ? (int)$_POST['movie_id'] : 0);

if ($movie_id == 0) {
    header("Location: homepage.php");
    exit();
}

// Check if user can book this movie (early access check from early_access.php)
if (!canBookMovie($conn, $movie_id, $_SESSION['role'], $_SESSION['user_id'])) {
    $early_message = getEarlyAccessMessage($conn, $movie_id, $_SESSION['role']);
    echo "<div class='error-container' style='text-align: center; padding: 50px;'>$early_message</div>";
    echo "<a href='homepage.php' class='btn' style='display: block; text-align: center;'>Back to Home</a>";
    exit();
}

// Fetch movie details
$movie_sql = "SELECT * FROM movies WHERE movie_id = ?";
$movie_stmt = $conn->prepare($movie_sql);
$movie_stmt->bind_param("i", $movie_id);
$movie_stmt->execute();
$movie_result = $movie_stmt->get_result();
$movie = $movie_result->fetch_assoc();

if (!$movie) {
    die("Movie not found");
}

// ========== FETCH SHOWTIMES FROM DATABASE ==========
$showtimes_sql = "SELECT show_time, status 
                  FROM showtimes 
                  WHERE movie_id = ? AND status = 'active'
                  ORDER BY FIELD(show_time, '10:00 AM', '4:00 PM', '7:30 PM', '10:30 PM')";
$showtimes_stmt = $conn->prepare($showtimes_sql);
$showtimes_stmt->bind_param("i", $movie_id);
$showtimes_stmt->execute();
$showtimes_result = $showtimes_stmt->get_result();

$available_showtimes = [];
while ($row = $showtimes_result->fetch_assoc()) {
    $available_showtimes[] = $row['show_time'];
}

// If no showtimes found, show error
if (empty($available_showtimes)) {
    echo "<div style='text-align: center; padding: 50px;'>
            <i class='fas fa-clock' style='font-size: 50px; margin-bottom: 20px; color: #ff9800;'></i>
            <h2>No Showtimes Available</h2>
            <p>This movie doesn't have any active showtimes at the moment.</p>
            <a href='homepage.php' style='display: inline-block; margin-top: 20px; background: #ff4d6d; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px;'>Back to Home</a>
          </div>";
    exit();
}

// Set default time to first available showtime
$default_time = $available_showtimes[0];

// ========== EARLY ACCESS CHECK FOR COMING SOON MOVIES ==========
if ($movie['status'] == 'coming_soon') {
    // Function to check if user has early access badge
    $early_access_check_sql = "SELECT COUNT(*) as has_access 
                               FROM badges b
                               WHERE b.is_active = 1 
                               AND b.allows_early_access = 1
                               AND EXISTS (
                                   SELECT 1 FROM (
                                       SELECT 
                                           (SELECT COUNT(*) FROM bookings WHERE user_id = ?) as total_bookings,
                                           (SELECT IFNULL(SUM(total_price), 0) FROM bookings WHERE user_id = ? AND status = 'confirmed') as total_spent,
                                           (SELECT COUNT(*) FROM movie_feedback WHERE user_id = ?) as total_reviews,
                                           (SELECT COUNT(*) FROM wishlist WHERE user_id = ?) as total_wishlist
                                   ) as stats
                                   WHERE 
                                       CASE b.requirement_type
                                           WHEN 'bookings' THEN stats.total_bookings >= b.requirement_value
                                           WHEN 'spending' THEN stats.total_spent >= b.requirement_value
                                           WHEN 'reviews' THEN stats.total_reviews >= b.requirement_value
                                           WHEN 'wishlist' THEN stats.total_wishlist >= b.requirement_value
                                           ELSE FALSE
                                       END = 1
                               )";
    
    $early_stmt = $conn->prepare($early_access_check_sql);
    $early_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $early_stmt->execute();
    $has_early_access = $early_stmt->get_result()->fetch_assoc()['has_access'] > 0;
    
    if (!$has_early_access) {
        $error_message = "🔒 This movie requires VIP Early Access to book. ";
        $error_message .= "Earn badges like 'Big Spender' or 'Movie Addict' to unlock coming soon movies!";
        echo "<div style='text-align: center; padding: 50px;'>
                <i class='fas fa-lock' style='font-size: 50px; margin-bottom: 20px; color: #ff9800;'></i>
                <h2>Early Access Required</h2>
                <p>$error_message</p>
                <a href='movie-details.php?id=$movie_id' style='display: inline-block; margin-top: 20px; background: #ff4d6d; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px;'>Back to Movie</a>
                <a href='badges.php' style='display: inline-block; margin-top: 20px; margin-left: 10px; background: #ff9800; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px;'>View Badges</a>
              </div>";
        exit();
    }
}
// ========== END EARLY ACCESS CHECK ==========

// Get all earned badges for this user
$earned_badges = getUserEarnedBadges($conn, $user_id);

// Handle badge selection/deselection
$selected_badge_id = isset($_POST['selected_badge_id']) ? $_POST['selected_badge_id'] : (isset($_SESSION['selected_badge_id']) ? $_SESSION['selected_badge_id'] : '');

// Handle apply badge button
if (isset($_POST['apply_badge'])) {
    $new_badge_id = $_POST['selected_badge_id'];
    
    if (!empty($new_badge_id)) {
        // Verify the badge is actually earned
        $badge_exists = false;
        foreach ($earned_badges as $badge) {
            if ($badge['badge_id'] == $new_badge_id) {
                $badge_exists = true;
                break;
            }
        }
        
        if ($badge_exists) {
            $selected_badge_id = $new_badge_id;
            $_SESSION['selected_badge_id'] = $selected_badge_id;
            $_SESSION['badge_message'] = "✅ Badge discount applied!";
        } else {
            $_SESSION['badge_message'] = "❌ You haven't earned that badge yet!";
        }
    } else {
        $_SESSION['badge_message'] = "⚠️ Please select a badge first";
    }
    
    header("Location: booking.php?movie_id=" . $movie_id);
    exit();
}

// Handle clear badge button
if (isset($_POST['clear_badge'])) {
    unset($_SESSION['selected_badge_id']);
    $selected_badge_id = '';
    $_SESSION['badge_message'] = "✅ Badge discount removed.";
    header("Location: booking.php?movie_id=" . $movie_id);
    exit();
}

// Get the selected badge details
$selected_badge = null;
if (!empty($selected_badge_id)) {
    foreach ($earned_badges as $badge) {
        if ($badge['badge_id'] == $selected_badge_id) {
            $selected_badge = $badge;
            break;
        }
    }
    // If badge not found in earned badges, clear it
    if (!$selected_badge) {
        unset($_SESSION['selected_badge_id']);
        $selected_badge_id = '';
    }
}

// Calculate ticket prices
$ticket_price = (float)$movie['price'];
$discounted_price = $ticket_price;

if ($selected_badge) {
    $calc = calculateDiscountFromBadge($selected_badge, $ticket_price, 1);
    $discounted_price = $calc['price_per_ticket'];
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book') {
    $selected_time = $_POST['time'];
    $selected_seats = explode(',', $_POST['seats']);
    $applied_badge_id = isset($_POST['applied_badge_id']) ? $_POST['applied_badge_id'] : '';
    
    // Calculate the correct total based on badge discount
    $total_original_price = $ticket_price * count($selected_seats);
    $final_total = $total_original_price;
    $price_per_seat = $ticket_price;
    $applied_badge_info = null;
    
    if (!empty($applied_badge_id)) {
        // Find the applied badge
        foreach ($earned_badges as $badge) {
            if ($badge['badge_id'] == $applied_badge_id) {
                $applied_badge_info = $badge;
                break;
            }
        }
        
        if ($applied_badge_info) {
            $calc = calculateDiscountFromBadge($applied_badge_info, $ticket_price, count($selected_seats));
            $final_total = $calc['final_price'];
            $price_per_seat = $calc['price_per_ticket'];
            
            $_SESSION['badge_discount_applied'] = [
                'badge_name' => $applied_badge_info['badge_name'],
                'discount_type' => $applied_badge_info['discount_type'],
                'discount_value' => $applied_badge_info['discount_value'],
                'discount_amount' => $total_original_price - $final_total
            ];
            
            // Clear the selected badge from session after use
            unset($_SESSION['selected_badge_id']);
        }
    }
    
    // Check if seats are still available
    $occupied_sql = "SELECT seat_number FROM bookings WHERE movie_id = ? AND show_time = ? AND booking_date = CURDATE() AND status IN ('pending', 'confirmed')";
    $occupied_stmt = $conn->prepare($occupied_sql);
    $occupied_stmt->bind_param("is", $movie_id, $selected_time);
    $occupied_stmt->execute();
    $occupied_result = $occupied_stmt->get_result();
    
    $occupied_seats_check = [];
    while ($row = $occupied_result->fetch_assoc()) {
        $occupied_seats_check[] = $row['seat_number'];
    }
    
    $still_available = true;
    foreach ($selected_seats as $seat) {
        if (in_array($seat, $occupied_seats_check)) {
            $still_available = false;
            $error_message = "Some seats are no longer available. Please select different seats.";
            break;
        }
    }
    
    if ($still_available) {
        $booking_ids = [];
        
        $booking_sql = "INSERT INTO bookings (user_id, movie_id, show_time, seat_number, total_price, booking_date, status) 
                        VALUES (?, ?, ?, ?, ?, NOW(), 'pending')";
        $booking_stmt = $conn->prepare($booking_sql);
        
        foreach ($selected_seats as $seat) {
            $booking_stmt->bind_param("iissd", $user_id, $movie_id, $selected_time, $seat, $price_per_seat);
            $booking_stmt->execute();
            $booking_ids[] = $conn->insert_id;
        }
        
        $_SESSION['booking_ids'] = $booking_ids;
        $_SESSION['total_price'] = $final_total;
        
        header("Location: payment.php?booking_ids=" . implode(',', $booking_ids));
        exit();
    }
}

// Get occupied seats for current showtime
$current_time = isset($_GET['time']) ? $_GET['time'] : $default_time;

$occupied_sql = "SELECT seat_number FROM bookings WHERE movie_id = ? AND show_time = ? AND booking_date = CURDATE() AND status IN ('pending', 'confirmed')";
$occupied_stmt = $conn->prepare($occupied_sql);
$occupied_stmt->bind_param("is", $movie_id, $current_time);
$occupied_stmt->execute();
$occupied_result = $occupied_stmt->get_result();

$occupied_seats = [];
while ($row = $occupied_result->fetch_assoc()) {
    $occupied_seats[] = $row['seat_number'];
}

// Format duration
$hours = floor($movie['duration'] / 60);
$minutes = $movie['duration'] % 60;
$formatted_duration = $hours . 'h ' . $minutes . 'm';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Tickets - <?php echo htmlspecialchars($movie['title']); ?></title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #140005;
            color: white;
        }

        .back {
            padding: 20px 40px;
        }
        .back a {
            color: #ff4d6d;
            text-decoration: none;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 0 40px 20px;
        }

        .header img {
            width: 80px;
            border-radius: 10px;
        }

        .header h1 {
            font-family: 'Playfair Display', serif;
        }

        .container {
            display: flex;
            gap: 40px;
            padding: 40px;
            flex-wrap: wrap;
        }

        .left {
            flex: 2;
            min-width: 300px;
        }

        .right {
            flex: 1;
            min-width: 280px;
        }

        .showtimes {
            margin-bottom: 30px;
        }

        .times {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .time {
            padding: 10px 20px;
            border-radius: 10px;
            background: #2b0a14;
            cursor: pointer;
            transition: 0.3s;
        }

        .time:hover {
            background: #ff4d6d;
        }

        .time.active {
            background: #ff4d6d;
        }

        .screen {
            text-align: center;
            margin: 20px 0;
            padding: 12px;
            background: #2b0a14;
            border-radius: 10px;
            font-size: 14px;
            letter-spacing: 2px;
        }

        .seats {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 10px;
            justify-items: center;
            margin-top: 20px;
        }

        .seat {
            width: 40px;
            height: 40px;
            background: #2d2d2d;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            color: white;
        }

        .seat:hover {
            background: #ff4d6d;
            transform: scale(1.05);
        }

        .seat.selected {
            background: #4CAF50 !important;
            box-shadow: 0 0 10px rgba(76,175,80,0.5);
        }

        .seat.occupied {
            background: #dc3545 !important;
            cursor: not-allowed !important;
            text-decoration: line-through;
        }

        .seat.occupied:hover {
            transform: none;
            background: #dc3545 !important;
        }

        .seat-legend {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            padding: 15px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            justify-content: center;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
        }

        .legend-box {
            width: 25px;
            height: 25px;
            border-radius: 6px;
        }

        .legend-box.available { background: #2d2d2d; }
        .legend-box.selected { background: #4CAF50; }
        .legend-box.occupied { background: #dc3545; }

        .summary-card {
            background: #1a0a12;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .summary-card h2 {
            color: #ff4d6d;
            font-size: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            opacity: 0.7;
            font-size: 14px;
        }

        .summary-value {
            font-weight: bold;
            color: #ffd700;
        }

        .discount-badge {
            background: linear-gradient(135deg, #9C27B0, #6A1B9A);
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            margin: 10px 0;
        }

        .total-row {
            margin-top: 15px;
            padding-top: 15px;
            font-size: 20px;
            font-weight: bold;
            color: #ff4d6d;
            border-top: 2px solid #ff4d6d;
            display: flex;
            justify-content: space-between;
        }

        .book-btn {
            width: 100%;
            margin-top: 20px;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(45deg, #800020, #ff4d6d);
            color: white;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: 0.3s;
        }

        .book-btn:hover {
            transform: scale(1.02);
            opacity: 0.9;
        }

        .book-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .badge-card {
            background: #1a0a12;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .badge-card h3 {
            color: #ff4d6d;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .active-badge-info {
            background: rgba(76,175,80,0.15);
            border: 1px solid #4CAF50;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .active-badge-name {
            font-weight: bold;
            color: #4CAF50;
        }

        .clear-badge-btn {
            background: #dc3545;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: 12px;
        }

        .badge-list {
            max-height: 350px;
            overflow-y: auto;
            margin-bottom: 15px;
        }

        .badge-item {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: 0.2s;
            border: 2px solid transparent;
        }

        .badge-item:hover {
            background: rgba(255,77,109,0.15);
        }

        .badge-item.selected {
            border-color: #4CAF50;
            background: rgba(76,175,80,0.1);
        }

        .badge-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .badge-name {
            font-weight: bold;
            font-size: 14px;
        }

        .badge-discount {
            font-size: 12px;
            color: #ffd700;
        }

        .badge-desc {
            font-size: 11px;
            opacity: 0.6;
        }

        .apply-badge-btn {
            width: 100%;
            padding: 12px;
            background: #9C27B0;
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
        }

        .apply-badge-btn:hover {
            background: #7B1FA2;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .message.success {
            background: rgba(76,175,80,0.2);
            border-left: 4px solid #4CAF50;
        }

        .message.warning {
            background: rgba(255,193,7,0.2);
            border-left: 4px solid #ffc107;
        }

        .original-price {
            text-decoration: line-through;
            color: #888;
            font-size: 14px;
        }

        /* Hide the actual radio buttons */
        .badge-radio {
            display: none;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
                flex-direction: column;
            }
            .seat {
                width: 30px;
                height: 30px;
                font-size: 9px;
            }
            .badge-list {
                max-height: 200px;
            }
        }
    </style>
</head>
<body>

<div class="back">
    <a href="movie-details.php?id=<?php echo $movie_id; ?>">← Back to Movie</a>
</div>

<div class="header">
    <img src="<?php echo htmlspecialchars($movie['poster']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
    <div>
        <h1><?php echo htmlspecialchars($movie['title']); ?></h1>
        <p>⭐ <?php echo $movie['rating'] ? $movie['rating'] : 'N/A'; ?> | <?php echo $formatted_duration; ?> | 🎬 <?php echo htmlspecialchars($movie['genre']); ?></p>
        <?php if ($selected_badge): ?>
            <p>
                <span class="original-price">💰 RM <?php echo number_format($ticket_price, 2); ?></span> 
                → <span style="color: #28a745; font-weight: bold;">RM <?php echo number_format($discounted_price, 2); ?></span> 
                <span style="background: #9C27B0; padding: 3px 10px; border-radius: 20px; font-size: 12px; margin-left: 10px;">
                    🎖️ <?php echo htmlspecialchars($selected_badge['badge_name']); ?>
                </span>
            </p>
        <?php else: ?>
            <p>💰 RM <?php echo number_format($ticket_price, 2); ?> per ticket</p>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="left">
        <?php if (isset($error_message)): ?>
            <div class="message warning">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['badge_message'])): ?>
            <div class="message success">
                <i class="fas fa-info-circle"></i> 
                <?php 
                    echo $_SESSION['badge_message']; 
                    unset($_SESSION['badge_message']); 
                ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="bookingForm">
            <input type="hidden" name="action" value="book">
            <input type="hidden" name="movie_id" value="<?php echo $movie_id; ?>">
            <input type="hidden" name="time" id="timeInput" value="<?php echo $default_time; ?>">
            <input type="hidden" name="seats" id="seatsInput" value="">
            <input type="hidden" name="total" id="totalInput" value="0">
            <input type="hidden" name="applied_badge_id" id="appliedBadgeId" value="<?php echo htmlspecialchars($selected_badge_id); ?>">
            
            <div class="showtimes">
                <h2><i class="fas fa-clock"></i> Select Showtime</h2>
                <div class="times">
                    <?php foreach ($available_showtimes as $index => $time): ?>
                        <div class="time <?php echo ($index == 0) ? 'active' : ''; ?>" data-time="<?php echo $time; ?>">
                            <?php echo $time; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($available_showtimes) == 0): ?>
                    <p style="color: #ff9800; margin-top: 10px;">
                        <i class="fas fa-exclamation-triangle"></i> No showtimes available for this movie.
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="screen">
                <i class="fas fa-tv"></i> SCREEN <i class="fas fa-tv"></i>
            </div>
            
            <div class="seats" id="seats"></div>
            
            <div class="seat-legend">
                <div class="legend-item">
                    <div class="legend-box available"></div>
                    <span>Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box selected"></div>
                    <span>Selected</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box occupied"></div>
                    <span>Booked</span>
                </div>
            </div>
        </form>
    </div>

    <div class="right">
        <?php if (!empty($earned_badges)): ?>
        <div class="badge-card">
            <h3><i class="fas fa-medal"></i> Your Badge Discounts (<?php echo count($earned_badges); ?> available)</h3>
            
            <?php if ($selected_badge): ?>
                <div class="active-badge-info">
                    <div>
                        <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
                        <span class="active-badge-name"><?php echo htmlspecialchars($selected_badge['badge_name']); ?></span> applied
                        <div style="font-size: 11px; margin-top: 4px;">
                            <?php if ($selected_badge['discount_type'] == 'percentage'): ?>
                                🎫 <?php echo $selected_badge['discount_value']; ?>% OFF
                            <?php else: ?>
                                🎫 RM<?php echo number_format($selected_badge['discount_value'], 2); ?> OFF per ticket
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="clear_badge" class="clear-badge-btn">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="badgeSelectForm">
                <div class="badge-list" id="badgeList">
                    <?php foreach ($earned_badges as $index => $badge): ?>
                        <div class="badge-item <?php echo ($selected_badge_id == $badge['badge_id']) ? 'selected' : ''; ?>" data-badge-id="<?php echo htmlspecialchars($badge['badge_id']); ?>">
                            <div class="badge-item-header">
                                <span class="badge-name">
                                    <i class="fas fa-medal"></i> <?php echo htmlspecialchars($badge['badge_name']); ?>
                                </span>
                                <span class="badge-discount">
                                    <?php if ($badge['discount_type'] == 'percentage'): ?>
                                        🎫 <?php echo $badge['discount_value']; ?>% OFF
                                    <?php else: ?>
                                        🎫 RM<?php echo number_format($badge['discount_value'], 2); ?> OFF
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="badge-desc"><?php echo htmlspecialchars($badge['badge_description']); ?></div>
                        </div>
                        <input type="radio" name="selected_badge_id" value="<?php echo htmlspecialchars($badge['badge_id']); ?>" 
                               id="badge_<?php echo $index; ?>" 
                               class="badge-radio"
                               <?php echo ($selected_badge_id == $badge['badge_id']) ? 'checked' : ''; ?>>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="apply_badge" class="apply-badge-btn">
                    <i class="fas fa-check"></i> Apply Selected Discount
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="badge-card">
            <h3><i class="fas fa-medal"></i> Your Badge Discounts</h3>
            <div style="text-align: center; padding: 20px; opacity: 0.6;">
                <i class="fas fa-star" style="font-size: 30px; margin-bottom: 10px;"></i>
                <p>No badges earned yet!</p>
                <p style="font-size: 12px;">Watch movies, write reviews, and add to wishlist to earn badges.</p>
                <a href="badges.php" style="color: #ff4d6d; font-size: 12px;">View all badges →</a>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="summary-card">
            <h2><i class="fas fa-receipt"></i> Booking Summary</h2>
            
            <div class="summary-row">
                <span class="summary-label">Movie</span>
                <span class="summary-value"><?php echo htmlspecialchars($movie['title']); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Showtime</span>
                <span class="summary-value" id="selectedTimeDisplay"><?php echo $default_time; ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Seats</span>
                <span class="summary-value" id="selectedSeats">0</span>
            </div>
            
            <?php if ($selected_badge): ?>
                <div class="summary-row">
                    <span class="summary-label">Original Price</span>
                    <span class="summary-value">RM <?php echo number_format($ticket_price, 2); ?></span>
                </div>
                <div class="discount-badge">
                    <i class="fas fa-medal"></i> <?php echo htmlspecialchars($selected_badge['badge_name']); ?>: 
                    <?php if ($selected_badge['discount_type'] == 'percentage'): ?>
                        <?php echo $selected_badge['discount_value']; ?>% OFF
                    <?php else: ?>
                        RM<?php echo number_format($selected_badge['discount_value'], 2); ?> OFF per ticket
                    <?php endif; ?>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Discounted Price</span>
                    <span class="summary-value" style="color: #28a745;">RM <?php echo number_format($discounted_price, 2); ?></span>
                </div>
            <?php else: ?>
                <div class="summary-row">
                    <span class="summary-label">Price per ticket</span>
                    <span class="summary-value">RM <?php echo number_format($ticket_price, 2); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="total-row">
                <span>Total</span>
                <span id="total">RM 0.00</span>
            </div>
            
            <button type="button" class="book-btn" id="confirmBtn">
                <i class="fas fa-credit-card"></i> Proceed to Payment
            </button>
        </div>
    </div>
</div>

<script>
let hasActiveBadge = <?php echo $selected_badge ? 'true' : 'false'; ?>;
let badgeDiscountType = '<?php echo $selected_badge ? $selected_badge['discount_type'] : ''; ?>';
let badgeDiscountValue = <?php echo $selected_badge ? ($selected_badge['discount_type'] == 'percentage' ? $selected_badge['discount_value'] : $selected_badge['discount_value']) : 0; ?>;
let originalTicketPrice = <?php echo $ticket_price; ?>;

const movieId = <?php echo $movie_id; ?>;
const occupiedSeatsFromPHP = <?php echo json_encode($occupied_seats); ?>;

// Handle badge item click to select the corresponding radio button
document.querySelectorAll('.badge-item').forEach(item => {
    item.addEventListener('click', function(e) {
        const badgeId = this.getAttribute('data-badge-id');
        const radioButton = document.querySelector(`input[name="selected_badge_id"][value="${badgeId}"]`);
        
        if (radioButton) {
            radioButton.checked = true;
            
            document.querySelectorAll('.badge-item').forEach(badge => {
                badge.classList.remove('selected');
            });
            
            this.classList.add('selected');
        }
    });
});

// Generate seats
const seatContainer = document.getElementById("seats");
const rowLetters = ['A', 'B', 'C', 'D', 'E', 'F'];

for (let row = 0; row < rowLetters.length; row++) {
    for (let seatNum = 1; seatNum <= 10; seatNum++) {
        const seat = document.createElement("div");
        const seatNumber = rowLetters[row] + seatNum;
        seat.classList.add("seat");
        seat.textContent = seatNumber;
        seat.setAttribute("data-seat", seatNumber);
        
        if (occupiedSeatsFromPHP.includes(seatNumber)) {
            seat.classList.add("occupied");
        }
        
        seatContainer.appendChild(seat);
    }
}

function updateOccupiedSeats(occupiedArray) {
    document.querySelectorAll(".seat").forEach(seat => {
        seat.classList.remove("occupied");
    });
    
    if (occupiedArray && occupiedArray.length > 0) {
        occupiedArray.forEach(seatNumber => {
            const seatElement = document.querySelector(`.seat[data-seat="${seatNumber}"]`);
            if (seatElement) {
                seatElement.classList.add("occupied");
            }
        });
    }
    
    document.querySelectorAll(".seat.selected").forEach(seat => {
        seat.classList.remove("selected");
    });
    
    updateSummary();
}

function loadOccupiedSeats(showTime) {
    fetch(`get_occupied_seats.php?movie_id=${movieId}&time=${encodeURIComponent(showTime)}`)
        .then(response => response.json())
        .then(data => {
            if (data.occupied) {
                updateOccupiedSeats(data.occupied);
            } else {
                updateOccupiedSeats([]);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            updateOccupiedSeats([]);
        });
}

const times = document.querySelectorAll(".time");
const selectedTimeDisplay = document.getElementById("selectedTimeDisplay");
const timeInput = document.getElementById("timeInput");

times.forEach(t => {
    t.addEventListener("click", () => {
        const selectedTime = t.getAttribute("data-time");
        
        times.forEach(x => x.classList.remove("active"));
        t.classList.add("active");
        selectedTimeDisplay.innerText = selectedTime;
        timeInput.value = selectedTime;
        
        loadOccupiedSeats(selectedTime);
    });
});

function updateSummary() {
    const selectedSeats = document.querySelectorAll(".seat.selected");
    const selectedCount = selectedSeats.length;
    
    let totalPrice;
    
    if (hasActiveBadge) {
        const originalTotal = originalTicketPrice * selectedCount;
        
        if (badgeDiscountType === 'percentage') {
            totalPrice = originalTotal * (1 - badgeDiscountValue / 100);
        } else {
            const discountTotal = badgeDiscountValue * selectedCount;
            totalPrice = Math.max(0, originalTotal - discountTotal);
        }
    } else {
        totalPrice = originalTicketPrice * selectedCount;
    }
    
    document.getElementById("selectedSeats").innerText = selectedCount;
    document.getElementById("total").innerHTML = `RM ${totalPrice.toFixed(2)}`;
    document.getElementById("totalInput").value = totalPrice.toFixed(2);
    
    const seatNumbers = Array.from(selectedSeats).map(seat => seat.getAttribute("data-seat"));
    document.getElementById("seatsInput").value = seatNumbers.join(',');
    document.getElementById("confirmBtn").disabled = selectedCount === 0;
}

document.querySelectorAll(".seat").forEach(seat => {
    seat.addEventListener("click", () => {
        if (seat.classList.contains("occupied")) {
            alert(`Seat ${seat.getAttribute("data-seat")} is already booked!`);
            return;
        }
        seat.classList.toggle("selected");
        updateSummary();
    });
});

document.getElementById("confirmBtn").addEventListener("click", () => {
    const selectedSeatsList = document.querySelectorAll(".seat.selected");
    if (selectedSeatsList.length === 0) {
        alert("Please select at least one seat");
        return;
    }
    
    if (confirm(`Confirm booking for ${selectedSeatsList.length} seat(s) with total RM ${document.getElementById("totalInput").value}?`)) {
        document.getElementById("bookingForm").submit();
    }
});

updateSummary();
setTimeout(() => {
    loadOccupiedSeats('<?php echo $default_time; ?>');
}, 500);
</script>

</body>
</html>