<?php
// File: early_access.php
// Functions for early access and movie availability checking

/**
 * Check if a user can book a specific movie
 * 
 * @param mysqli $conn Database connection
 * @param int $movie_id Movie ID
 * @param string $role User role (admin, staff, user)
 * @param int $user_id User ID
 * @return bool True if user can book, false otherwise
 */
function canBookMovie($conn, $movie_id, $role, $user_id) {
    // Staff and Admin can always book any movie
    if ($role == 'admin' || $role == 'staff') {
        return true;
    }
    
    // Get movie details
    $sql = "SELECT status, release_date FROM movies WHERE movie_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $movie_id);
    $stmt->execute();
    $movie = $stmt->get_result()->fetch_assoc();
    
    if (!$movie) {
        return false;
    }
    
    // If movie is published, anyone can book
    if ($movie['status'] == 'published') {
        return true;
    }
    
    // If movie is coming soon, check for early access badge
    if ($movie['status'] == 'coming_soon') {
        return hasEarlyAccess($conn, $user_id);
    }
    
    // Draft movies cannot be booked by regular users
    if ($movie['status'] == 'draft') {
        return false;
    }
    
    return false;
}

/**
 * Check if user has early access permission (has any badge with allows_early_access = 1)
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return bool True if user has early access, false otherwise
 */
function hasEarlyAccess($conn, $user_id) {
    // Get user stats
    $booking_sql = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ?";
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("i", $user_id);
    $booking_stmt->execute();
    $total_bookings = $booking_stmt->get_result()->fetch_assoc()['count'];
    
    $spent_sql = "SELECT SUM(total_price) as total FROM bookings WHERE user_id = ? AND status = 'confirmed'";
    $spent_stmt = $conn->prepare($spent_sql);
    $spent_stmt->bind_param("i", $user_id);
    $spent_stmt->execute();
    $result = $spent_stmt->get_result();
    $row = $result->fetch_assoc();
    $total_spent = $row['total'] ?? 0;
    
    $review_sql = "SELECT COUNT(*) as count FROM movie_feedback WHERE user_id = ?";
    $review_stmt = $conn->prepare($review_sql);
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $total_reviews = $review_stmt->get_result()->fetch_assoc()['count'];
    
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
    
    // Check if user has any badge with allows_early_access = 1
    $badges_sql = "SELECT * FROM badges WHERE is_active = 1 AND allows_early_access = 1";
    $badges_result = $conn->query($badges_sql);
    $early_access_badges = $badges_result->fetch_all(MYSQLI_ASSOC);
    
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
            return true;
        }
    }
    
    return false;
}

/**
 * Get message explaining why user cannot book a movie
 * 
 * @param mysqli $conn Database connection
 * @param int $movie_id Movie ID
 * @param string $role User role
 * @return string HTML message
 */
function getEarlyAccessMessage($conn, $movie_id, $role) {
    // Get movie details
    $sql = "SELECT status, title FROM movies WHERE movie_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $movie_id);
    $stmt->execute();
    $movie = $stmt->get_result()->fetch_assoc();
    
    if (!$movie) {
        return "Movie not found.";
    }
    
    if ($movie['status'] == 'draft') {
        return "<i class='fas fa-pencil-alt'></i> This movie is currently in draft mode and not available for booking yet.";
    }
    
    if ($movie['status'] == 'coming_soon') {
        return "<i class='fas fa-lock'></i> This movie is coming soon! Only users with VIP Early Access badges can book it before the release date. 
                <br><br>Earn badges like 'Big Spender' or 'Movie Addict' to unlock early access!";
    }
    
    return "This movie is not available for booking at this time.";
}

/**
 * Get earliest date when a user can book a movie
 * 
 * @param mysqli $conn Database connection
 * @param int $movie_id Movie ID
 * @param string $role User role
 * @param int $user_id User ID
 * @return string Date string
 */
function getEarliestBookingDate($conn, $movie_id, $role, $user_id) {
    // Staff and admin can book anytime
    if ($role == 'admin' || $role == 'staff') {
        return date('Y-m-d');
    }
    
    // Get movie release date
    $sql = "SELECT release_date, status FROM movies WHERE movie_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $movie_id);
    $stmt->execute();
    $movie = $stmt->get_result()->fetch_assoc();
    
    if (!$movie) {
        return date('Y-m-d', strtotime('+7 days'));
    }
    
    // If published, available now
    if ($movie['status'] == 'published') {
        return date('Y-m-d');
    }
    
    // If coming soon and has early access, available now
    if ($movie['status'] == 'coming_soon' && hasEarlyAccess($conn, $user_id)) {
        return date('Y-m-d');
    }
    
    // Otherwise, show release date or default
    if ($movie['release_date'] && $movie['release_date'] > date('Y-m-d')) {
        return $movie['release_date'];
    }
    
    return date('Y-m-d', strtotime('+7 days'));
}
?>