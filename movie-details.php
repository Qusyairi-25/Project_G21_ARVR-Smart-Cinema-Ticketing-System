<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include 'db.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Function to get user's stats and early access status
function getUserEarlyAccessStatus($conn, $user_id) {
    // Get all bookings count
    $booking_sql = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ?";
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("i", $user_id);
    $booking_stmt->execute();
    $total_bookings = $booking_stmt->get_result()->fetch_assoc()['count'];
    
    // Get total spent
    $spent_sql = "SELECT SUM(total_price) as total FROM bookings WHERE user_id = ? AND status = 'confirmed'";
    $spent_stmt = $conn->prepare($spent_sql);
    $spent_stmt->bind_param("i", $user_id);
    $spent_stmt->execute();
    $result = $spent_stmt->get_result();
    $row = $result->fetch_assoc();
    $total_spent = $row['total'] ?? 0;
    
    // Get reviews count
    $review_sql = "SELECT COUNT(*) as count FROM movie_feedback WHERE user_id = ?";
    $review_stmt = $conn->prepare($review_sql);
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $total_reviews = $review_stmt->get_result()->fetch_assoc()['count'];
    
    // Get wishlist count
    $wishlist_sql = "SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?";
    $wishlist_stmt = $conn->prepare($wishlist_sql);
    $wishlist_stmt->bind_param("i", $user_id);
    $wishlist_stmt->execute();
    $total_wishlist = $wishlist_stmt->get_result()->fetch_assoc()['count'];
    
    // Get genre counts
    $genre_sql = "SELECT m.genre, COUNT(*) as count 
                  FROM bookings b
                  JOIN movies m ON b.movie_id = m.movie_id 
                  WHERE b.user_id = ? AND b.status = 'confirmed'
                  GROUP BY m.genre";
    $genre_stmt = $conn->prepare($genre_sql);
    $genre_stmt->bind_param("i", $user_id);
    $genre_stmt->execute();
    $genre_result = $genre_stmt->get_result();
    $genre_counts = [];
    while ($row = $genre_result->fetch_assoc()) {
        $genre_counts[strtolower(trim($row['genre']))] = $row['count'];
    }
    
    // Get all badges that grant early access
    $badges_sql = "SELECT * FROM badges WHERE is_active = 1 AND allows_early_access = 1";
    $badges_result = $conn->query($badges_sql);
    $early_access_badges = $badges_result->fetch_all(MYSQLI_ASSOC);
    
    $has_early_access = false;
    $unlocked_early_access_badges = [];
    
    foreach ($early_access_badges as $badge) {
        $earned = false;
        
        switch ($badge['requirement_type']) {
            case 'reviews':
                if ($total_reviews >= $badge['requirement_value']) $earned = true;
                break;
            case 'bookings':
                if ($total_bookings >= $badge['requirement_value']) $earned = true;
                break;
            case 'wishlist':
                if ($total_wishlist >= $badge['requirement_value']) $earned = true;
                break;
            case 'spending':
                if ($total_spent >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_horror':
                if (($genre_counts['horror'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_action':
                if (($genre_counts['action'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_romance':
                if (($genre_counts['romance'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
            case 'genre_comedy':
                if (($genre_counts['comedy'] ?? 0) >= $badge['requirement_value']) $earned = true;
                break;
        }
        
        if ($earned) {
            $has_early_access = true;
            $unlocked_early_access_badges[] = $badge['badge_name'];
        }
    }
    
    return [
        'has_early_access' => $has_early_access,
        'early_access_badges' => $unlocked_early_access_badges,
        'all_early_access_badges' => $early_access_badges,
        'stats' => [
            'total_bookings' => $total_bookings,
            'total_spent' => $total_spent,
            'total_reviews' => $total_reviews,
            'total_wishlist' => $total_wishlist,
            'genre_counts' => $genre_counts
        ]
    ];
}

// Get movie ID from URL parameter
$movie_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch movie details from database
$sql = "SELECT * FROM movies WHERE movie_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $movie_id);
$stmt->execute();
$result = $stmt->get_result();
$movie = $result->fetch_assoc();

// If movie not found, show error
if (!$movie) {
    die('<div class="back"><a href="homepage.php">← Back to Home</a></div>
         <div class="error-message" style="text-align:center;padding:50px;">
             <h2>Movie Not Found</h2>
             <p>The movie you are looking for does not exist.</p>
         </div>');
}

// Check early access for coming soon movies
$is_coming_soon = ($movie['status'] == 'coming_soon');
$can_book_coming_soon = false;
$user_early_access_badges = [];

if ($is_coming_soon) {
    $early_access_data = getUserEarlyAccessStatus($conn, $user_id);
    $can_book_coming_soon = $early_access_data['has_early_access'];
    $user_early_access_badges = $early_access_data['early_access_badges'];
    $all_early_access_badges = $early_access_data['all_early_access_badges'];
    $user_stats = $early_access_data['stats'];
}

// Check if movie is already in user's wishlist
$wishlist_sql = "SELECT * FROM wishlist WHERE user_id = ? AND movie_id = ?";
$wishlist_stmt = $conn->prepare($wishlist_sql);
$wishlist_stmt->bind_param("ii", $user_id, $movie_id);
$wishlist_stmt->execute();
$wishlist_result = $wishlist_stmt->get_result();
$in_wishlist = $wishlist_result->num_rows > 0;

// Handle Add to Wishlist / Remove from Wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_wishlist'])) {
        if ($in_wishlist) {
            $delete_sql = "DELETE FROM wishlist WHERE user_id = ? AND movie_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $user_id, $movie_id);
            if ($delete_stmt->execute()) {
                $in_wishlist = false;
                $wishlist_message = "Removed from wishlist!";
            } else {
                $wishlist_message = "Error removing from wishlist.";
            }
        } else {
            $insert_sql = "INSERT INTO wishlist (user_id, movie_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ii", $user_id, $movie_id);
            if ($insert_stmt->execute()) {
                $in_wishlist = true;
                $wishlist_message = "Added to wishlist!";
            } else {
                $wishlist_message = "Error adding to wishlist.";
            }
        }
    }
    
    // Handle Feedback Submission
    if (isset($_POST['submit_feedback'])) {
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $comment = trim($_POST['comment'] ?? '');
        
        $errors = [];
        if ($rating < 1 || $rating > 5) {
            $errors[] = "Please select a valid rating (1-5 stars).";
        }
        if (empty($comment)) {
            $errors[] = "Please enter your feedback comment.";
        }
        
        if (empty($errors)) {
            $check_sql = "SELECT feedback_id FROM movie_feedback WHERE user_id = ? AND movie_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $user_id, $movie_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $update_sql = "UPDATE movie_feedback SET rating = ?, comment = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND movie_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("isii", $rating, $comment, $user_id, $movie_id);
                if ($update_stmt->execute()) {
                    $feedback_message = "Your review has been updated!";
                    $feedback_message_type = "success";
                } else {
                    $feedback_message = "Error updating your review.";
                    $feedback_message_type = "error";
                }
            } else {
                $insert_sql = "INSERT INTO movie_feedback (movie_id, user_id, rating, comment) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iiis", $movie_id, $user_id, $rating, $comment);
                if ($insert_stmt->execute()) {
                    $feedback_message = "Thank you for your feedback!";
                    $feedback_message_type = "success";
                } else {
                    $feedback_message = "Error submitting your feedback.";
                    $feedback_message_type = "error";
                }
            }
        } else {
            $feedback_message = implode("<br>", $errors);
            $feedback_message_type = "error";
        }
    }
    
    // Handle Delete Feedback
    if (isset($_POST['delete_feedback'])) {
        $delete_sql = "DELETE FROM movie_feedback WHERE user_id = ? AND movie_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $user_id, $movie_id);
        if ($delete_stmt->execute()) {
            $feedback_message = "Your review has been deleted.";
            $feedback_message_type = "success";
        } else {
            $feedback_message = "Error deleting your review.";
            $feedback_message_type = "error";
        }
    }
}

// Fetch user's existing feedback
$user_feedback_sql = "SELECT * FROM movie_feedback WHERE user_id = ? AND movie_id = ?";
$user_feedback_stmt = $conn->prepare($user_feedback_sql);
$user_feedback_stmt->bind_param("ii", $user_id, $movie_id);
$user_feedback_stmt->execute();
$user_feedback_result = $user_feedback_stmt->get_result();
$user_feedback = $user_feedback_result->fetch_assoc();

// Fetch all feedback for this movie
$feedbacks_sql = "SELECT mf.*, u.username 
                  FROM movie_feedback mf 
                  JOIN users u ON mf.user_id = u.user_id 
                  WHERE mf.movie_id = ?  AND mf.status = 'approved'
                  ORDER BY mf.created_at DESC";
$feedbacks_stmt = $conn->prepare($feedbacks_sql);
$feedbacks_stmt->bind_param("i", $movie_id);
$feedbacks_stmt->execute();
$feedbacks_result = $feedbacks_stmt->get_result();
$feedbacks = $feedbacks_result->fetch_all(MYSQLI_ASSOC);

// Calculate average rating
$avg_rating_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM movie_feedback WHERE movie_id = ?";
$avg_rating_stmt = $conn->prepare($avg_rating_sql);
$avg_rating_stmt->bind_param("i", $movie_id);
$avg_rating_stmt->execute();
$avg_rating_result = $avg_rating_stmt->get_result();
$rating_stats = $avg_rating_result->fetch_assoc();
$avg_rating = $rating_stats['avg_rating'] ? round($rating_stats['avg_rating'], 1) : 0;
$total_reviews = $rating_stats['total_reviews'] ?? 0;

// Format duration
$hours = floor($movie['duration'] / 60);
$minutes = $movie['duration'] % 60;
$formatted_duration = $hours . 'h ' . $minutes . 'm';

$movie_rating = $movie['rating'] ? $movie['rating'] : 'N/A';
$release_year = date('Y', strtotime($movie['release_date']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Movie Details - <?php echo htmlspecialchars($movie['title']); ?></title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="base.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="homepage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ===== HERO SECTION ===== */
        .hero {
            height: 400px;
            background: linear-gradient(to right, rgba(0,0,0,0.9), rgba(0,0,0,0.2)), 
                        url('<?php echo htmlspecialchars($movie['poster']); ?>') center/cover;
            display: flex;
            align-items: flex-end;
            padding: 40px;
        }
        
        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
        }
        
        .container {
            display: flex;
            gap: 40px;
            padding: 40px;
        }
        
        .poster {
            width: 100%;
            max-width: 260px;
            aspect-ratio: 2 / 3;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
            object-fit: cover;
        }
        
        .info {
            max-width: 600px;
        }
        
        .info h1 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .meta {
            margin: 15px 0;
            opacity: 0.8;
            font-size: 14px;
        }
        
        .description {
            margin: 20px 0;
            line-height: 1.6;
            opacity: 0.9;
        }
        
        .price-box {
            margin-top: 25px;
            padding: 20px;
            background: #2b0a14;
            border-radius: 12px;
            width: fit-content;
        }
        
        .price-box span {
            font-size: 13px;
            opacity: 0.7;
        }
        
        .price {
            font-size: 26px;
            color: #ff4d6d;
            margin-top: 5px;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .book-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(45deg, #800020, #ff4d6d);
            color: white;
            cursor: pointer;
            font-size: 16px;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .book-btn:hover {
            transform: scale(1.05);
        }
        
        .book-btn.disabled {
            background: #555;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .wishlist-btn {
            padding: 12px 25px;
            border: 2px solid #ff4d6d;
            border-radius: 10px;
            background: transparent;
            color: #ff4d6d;
            cursor: pointer;
            font-size: 16px;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .wishlist-btn.in-wishlist {
            background: #ff4d6d;
            color: white;
        }
        
        .wishlist-btn:hover {
            transform: scale(1.05);
            background: #ff4d6d;
            color: white;
        }
        
        /* Early Access Styles */
        .early-access-success {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, #1a472a, #0d2818);
            border-radius: 15px;
            border: 2px solid #ffd700;
            margin-top: 20px;
        }
        
        .early-access-success i {
            font-size: 50px;
            color: #ffd700;
            margin-bottom: 15px;
        }
        
        .early-access-success h3 {
            font-size: 24px;
            color: #ffd700;
            margin-bottom: 10px;
        }
        
        .coming-soon-locked {
            text-align: center;
            padding: 30px;
            background: #1a0a12;
            border-radius: 15px;
            margin-top: 20px;
        }
        
        .coming-soon-locked i {
            font-size: 50px;
            color: #ff9800;
            margin-bottom: 15px;
        }
        
        .coming-soon-locked h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .required-badges-section {
            margin: 25px 0;
            text-align: left;
        }
        
        .required-badges-section h4 {
            margin-bottom: 15px;
            color: #ffd700;
        }
        
        .required-badges-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .required-badge-card {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 10px;
        }
        
        .required-badge-card > i {
            font-size: 30px;
            margin: 0;
            color: #ffd700;
        }
        
        .required-badge-card .badge-info {
            flex: 1;
        }
        
        .required-badge-card strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .required-badge-card p {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 8px;
        }
        
        .progress-bar {
            height: 6px;
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
            overflow: hidden;
            margin: 8px 0 5px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ffd700, #ff8c00);
            border-radius: 3px;
            transition: width 0.3s;
        }
        
        .progress-text {
            font-size: 11px;
            opacity: 0.6;
        }
        
        .btn-view-badges {
            display: inline-block;
            padding: 12px 24px;
            background: #ff9800;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .unlocked-badges {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        .badge-unlocked {
            background: rgba(255,215,0,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            border: 1px solid #ffd700;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-published { background: #4CAF50; color: white; }
        .status-coming_soon { background: #FF9800; color: white; }
        .status-draft { background: #666; color: white; }
        
        .back {
            padding: 20px 40px;
        }
        
        .back a {
            color: #ff4d6d;
            text-decoration: none;
        }
        
        .feedback-section, .extra {
            padding: 40px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .hero h1 { font-size: 28px; }
            .price-box { margin: 0 auto; }
            .button-group { justify-content: center; }
        }

           .feedback-section {
        background: rgba(255,255,255,0.05);
        border-radius: 20px;
        padding: 30px;
        margin-top: 40px;
    }
    
    .feedback-section h2 {
        color: #ff4d6d;
        margin-bottom: 25px;
        font-size: 24px;
    }
    
    .feedback-section h3 {
        color: #ff8c00;
        margin-bottom: 15px;
        font-size: 18px;
    }
    
    .message {
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
    .message.success {
        background: rgba(76, 175, 80, 0.2);
        border-left: 4px solid #4CAF50;
        color: #4CAF50;
    }
    
    .message.error {
        background: rgba(244, 67, 54, 0.2);
        border-left: 4px solid #f44336;
        color: #f44336;
    }
    
    .feedback-form-container {
        background: rgba(0,0,0,0.3);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .rating-input {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .rating-label {
        font-weight: 600;
    }
    
    .stars-input {
        display: flex;
        flex-direction: row-reverse;
        gap: 5px;
    }
    
    .stars-input input {
        display: none;
    }
    
    .stars-input label {
        font-size: 25px;
        color: #555;
        cursor: pointer;
        transition: 0.2s;
    }
    
    .stars-input label:hover,
    .stars-input label:hover ~ label,
    .stars-input input:checked ~ label {
        color: #FFD700;
    }
    
    .comment-input {
        width: 100%;
        padding: 12px;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.2);
        background: rgba(0,0,0,0.5);
        color: white;
        font-family: 'Poppins', sans-serif;
        resize: vertical;
        margin-bottom: 15px;
    }
    
    .comment-input:focus {
        outline: none;
        border-color: #ff4d6d;
    }
    
    .form-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .btn-submit {
        background: #ff4d6d;
        color: white;
        padding: 10px 25px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: 0.3s;
    }
    
    .btn-submit:hover {
        background: #ff6b8a;
        transform: translateY(-2px);
    }
    
    .btn-delete {
        background: rgba(244, 67, 54, 0.2);
        color: #f44336;
        padding: 10px 25px;
        border: 1px solid #f44336;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: 0.3s;
    }
    
    .btn-delete:hover {
        background: rgba(244, 67, 54, 0.4);
        transform: translateY(-2px);
    }
    
    .feedback-note {
        font-size: 12px;
        opacity: 0.6;
        margin-top: 15px;
        text-align: center;
    }
    
    .reviews-list {
        margin-top: 20px;
    }
    
    .review-card {
        background: rgba(255,255,255,0.03);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 15px;
        transition: 0.3s;
    }
    
    .review-card:hover {
        background: rgba(255,255,255,0.05);
        transform: translateX(5px);
    }
    
    .review-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .reviewer-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .reviewer-info i {
        color: #ff8c00;
        font-size: 18px;
    }
    
    .review-rating {
        color: #FFD700;
        font-size: 14px;
    }
    
    .review-date {
        font-size: 11px;
        opacity: 0.5;
        margin-bottom: 10px;
    }
    
    .review-comment {
        line-height: 1.6;
        font-size: 14px;
    }
    
    .no-reviews {
        text-align: center;
        padding: 40px;
        opacity: 0.6;
    }
    
    .no-reviews i {
        font-size: 48px;
        margin-bottom: 15px;
    }
    
    @media (max-width: 768px) {
        .feedback-section {
            padding: 20px;
        }
        
        .rating-input {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .form-buttons {
            flex-direction: column;
        }
        
        .btn-submit, .btn-delete {
            width: 100%;
            text-align: center;
        }
    }

    </style>
</head>
<body>

<div class="back">
    <a href="homepage.php">← Back to Home</a>
</div>

<div class="hero">
    <h1><?php echo htmlspecialchars($movie['title']); ?></h1>
</div>

<div class="container">
    <img class="poster" src="<?php echo htmlspecialchars($movie['poster']); ?>" 
         alt="<?php echo htmlspecialchars($movie['title']); ?> poster"
         onerror="this.src='https://via.placeholder.com/260x360?text=No+Poster'">
    
    <div class="info">
        <h1>
            <?php echo htmlspecialchars($movie['title']); ?>
            <span class="status-badge status-<?php echo $movie['status']; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $movie['status'])); ?>
            </span>
        </h1>
        
        <div class="meta">
            ⭐ <?php echo $movie_rating; ?> | 
            ⏱ <?php echo $formatted_duration; ?> | 
            🎬 <?php echo htmlspecialchars($movie['genre']); ?> | 
            📅 <?php echo $release_year; ?>
        </div>
        
        <div class="description">
            <?php echo nl2br(htmlspecialchars($movie['description'])); ?>
        </div>
        
        <div class="price-box">
            <span>Ticket Price</span>
            <div class="price">RM <?php echo number_format($movie['price'], 2); ?></div>
        </div>
        
        <!-- ===== BOOKING SECTION WITH EARLY ACCESS CHECK ===== -->
        <div class="button-group">
            <?php if ($movie['status'] == 'coming_soon'): ?>
                <?php if ($can_book_coming_soon): ?>
                    <!-- User has early access - show book button -->
                    <form action="booking.php" method="GET" style="display: inline;">
                        <input type="hidden" name="movie_id" value="<?php echo $movie['movie_id']; ?>">
                        <button type="submit" class="book-btn">
                            <i class="fas fa-crown"></i> Book Early Access
                        </button>
                    </form>
                <?php else: ?>
                    <!-- User does NOT have early access - show locked message -->
                    <button class="book-btn disabled" disabled>
                        <i class="fas fa-lock"></i> Coming Soon - VIP Only
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <!-- Normal published movie -->
                <?php
                include 'early_access.php';
                if (canBookMovie($conn, $movie['movie_id'], $role, $user_id)):
                ?>
                    <form action="booking.php" method="GET" style="display: inline;">
                        <input type="hidden" name="movie_id" value="<?php echo $movie['movie_id']; ?>">
                        <button type="submit" class="book-btn">
                            <i class="fas fa-ticket-alt"></i> Book Now
                        </button>
                    </form>
                <?php else: ?>
                    <button class="book-btn disabled" disabled>
                        <i class="fas fa-clock"></i> Not Available Yet
                    </button>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Wishlist Button -->
            <form method="POST" style="display: inline;">
                <input type="hidden" name="movie_id" value="<?php echo $movie['movie_id']; ?>">
                <button type="submit" name="toggle_wishlist" 
                        class="wishlist-btn <?php echo $in_wishlist ? 'in-wishlist' : ''; ?>">
                    <i class="fas <?php echo $in_wishlist ? 'fa-heart' : 'fa-heart-broken'; ?>"></i>
                    <?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>
                </button>
            </form>
        </div>
        
        <!-- Early Access Info Box for Coming Soon Movies -->
        <?php if ($movie['status'] == 'coming_soon'): ?>
            <?php if ($can_book_coming_soon): ?>
                <div class="early-access-success">
                    <i class="fas fa-crown"></i>
                    <h3>VIP Early Access Granted!</h3>
                    <p>You have early access to book this movie before official release!</p>
                    <?php if (!empty($user_early_access_badges)): ?>
                        <div class="unlocked-badges">
                            <?php foreach ($user_early_access_badges as $badge_name): ?>
                                <span class="badge-unlocked"><i class="fas fa-medal"></i> <?php echo htmlspecialchars($badge_name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="coming-soon-locked">
                    <i class="fas fa-lock"></i>
                    <h3>🔒 Coming Soon - VIP Early Access Only</h3>
                    <p>This movie is available for early access to users who have earned special badges.</p>
                    
                    <div class="required-badges-section">
                        <h4>Earn these badges to unlock early access:</h4>
                        <div class="required-badges-list">
                            <?php foreach ($all_early_access_badges as $badge): 
                                $current = 0;
                                switch ($badge['requirement_type']) {
                                    case 'bookings': $current = $user_stats['total_bookings'] ?? 0; break;
                                    case 'spending': $current = $user_stats['total_spent'] ?? 0; break;
                                    case 'reviews': $current = $user_stats['total_reviews'] ?? 0; break;
                                    case 'wishlist': $current = $user_stats['total_wishlist'] ?? 0; break;
                                    case 'genre_horror': $current = $user_stats['genre_counts']['horror'] ?? 0; break;
                                    case 'genre_action': $current = $user_stats['genre_counts']['action'] ?? 0; break;
                                    case 'genre_romance': $current = $user_stats['genre_counts']['romance'] ?? 0; break;
                                    case 'genre_comedy': $current = $user_stats['genre_counts']['comedy'] ?? 0; break;
                                }
                                $percentage = min(100, ($current / $badge['requirement_value']) * 100);
                            ?>
                                <div class="required-badge-card">
                                    <i class="fas fa-medal"></i>
                                    <div class="badge-info">
                                        <strong><?php echo htmlspecialchars($badge['badge_name']); ?></strong>
                                        <p><?php echo htmlspecialchars($badge['badge_description']); ?></p>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <span class="progress-text"><?php echo $current; ?> / <?php echo $badge['requirement_value']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <a href="badges.php" class="btn-view-badges">
                        <i class="fas fa-medal"></i> View All Badges
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($wishlist_message)): ?>
            <div class="wishlist-message" style="margin-top: 15px; padding: 10px; background: rgba(76,175,80,0.2); border-radius: 8px;">
                <?php echo $wishlist_message; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Feedback Section -->
<div class="feedback-section">
    <h2><i class="fas fa-comments"></i> User Reviews</h2>
    
    <!-- Display success/error message -->
    <?php if (isset($feedback_message)): ?>
        <div class="message <?php echo $feedback_message_type; ?>">
            <i class="fas <?php echo $feedback_message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $feedback_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Feedback Section -->
<div class="feedback-section">
    <h2><i class="fas fa-comments"></i> User Reviews</h2>
    
    <!-- Display success/error message -->
    <?php if (isset($feedback_message)): ?>
        <div class="message <?php echo $feedback_message_type; ?>">
            <i class="fas <?php echo $feedback_message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $feedback_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Feedback Form -->
    <div class="feedback-form-container">
        <h3><?php echo $user_feedback ? 'Edit Your Review' : 'Write a Review'; ?></h3>
        
        <form method="POST" class="feedback-form">
            <div class="rating-input">
                <span class="rating-label">Your Rating:</span>
                <div class="stars-input">
                    <input type="radio" name="rating" value="5" id="star5" <?php echo ($user_feedback && $user_feedback['rating'] == 5) ? 'checked' : ''; ?>>
                    <label for="star5"><i class="fas fa-star"></i></label>
                    
                    <input type="radio" name="rating" value="4" id="star4" <?php echo ($user_feedback && $user_feedback['rating'] == 4) ? 'checked' : ''; ?>>
                    <label for="star4"><i class="fas fa-star"></i></label>
                    
                    <input type="radio" name="rating" value="3" id="star3" <?php echo ($user_feedback && $user_feedback['rating'] == 3) ? 'checked' : ''; ?>>
                    <label for="star3"><i class="fas fa-star"></i></label>
                    
                    <input type="radio" name="rating" value="2" id="star2" <?php echo ($user_feedback && $user_feedback['rating'] == 2) ? 'checked' : ''; ?>>
                    <label for="star2"><i class="fas fa-star"></i></label>
                    
                    <input type="radio" name="rating" value="1" id="star1" <?php echo ($user_feedback && $user_feedback['rating'] == 1) ? 'checked' : ''; ?>>
                    <label for="star1"><i class="fas fa-star"></i></label>
                </div>
            </div>
            
            <textarea name="comment" class="comment-input" rows="4" placeholder="Share your thoughts about this movie..."><?php echo $user_feedback ? htmlspecialchars($user_feedback['comment']) : ''; ?></textarea>
            
            <div class="form-buttons">
                <button type="submit" name="submit_feedback" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> <?php echo $user_feedback ? 'Update Review' : 'Submit Review'; ?>
                </button>
                
                <?php if ($user_feedback): ?>
                    <button type="submit" name="delete_feedback" class="btn-delete" onclick="return confirm('Are you sure you want to delete your review?')">
                        <i class="fas fa-trash"></i> Delete Review
                    </button>
                <?php endif; ?>
            </div>
        </form>
        
        <div class="feedback-note">
            <i class="fas fa-info-circle"></i> Your review will be visible after admin approval.
        </div>
    </div>
    
    <!-- Reviews List - Only Shows Approved Reviews -->
    <div class="reviews-list">
        <h3><i class="fas fa-star"></i> What Others Think</h3>
        
        <?php if (count($feedbacks) > 0): ?>
            <?php foreach ($feedbacks as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <i class="fas fa-user-circle"></i>
                            <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                        </div>
                        <div class="review-rating">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $review['rating']) {
                                    echo '<i class="fas fa-star"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <div class="review-date">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                    </div>
                    <div class="review-comment">
                        <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-reviews">
                <i class="fas fa-comment-slash"></i>
                <p>No reviews yet. Be the first to review this movie!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="extra">
    <h2>More Information</h2>
    <p><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $movie['status'])); ?></p>
    <p><strong>Release Date:</strong> <?php echo date('F j, Y', strtotime($movie['release_date'])); ?></p>
    <p><strong>Duration:</strong> <?php echo $formatted_duration; ?></p>
    <p><strong>Genre:</strong> <?php echo htmlspecialchars($movie['genre']); ?></p>
</div>

</body>
</html>