<?php
header('Content-Type: application/json');
include 'db.php';

$user_id = $_GET['user_id'] ?? 0;
$mode = $_GET['mode'] ?? 'auto';
$fixed_logic = $_GET['fixed_logic'] ?? 'popular';

if (!$user_id) {
    echo json_encode(['error' => 'User ID required']);
    exit;
}

$recommended_movies = [];

// Auto mode: personalized for users with history, popular for new users
if ($mode == 'auto') {
    // Check if user has any confirmed bookings
    $history_check = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND status = 'confirmed'");
    $history_check->bind_param("i", $user_id);
    $history_check->execute();
    $history_result = $history_check->get_result();
    $has_history = $history_result->fetch_assoc()['count'] > 0;
    
    if ($has_history) {
        // Get user's most watched genre
        $genre_sql = "SELECT m.genre, COUNT(*) as count 
                      FROM bookings b
                      JOIN movies m ON b.movie_id = m.movie_id
                      WHERE b.user_id = ? AND b.status = 'confirmed' AND m.genre IS NOT NULL
                      GROUP BY m.genre
                      ORDER BY count DESC
                      LIMIT 1";
        $genre_stmt = $conn->prepare($genre_sql);
        $genre_stmt->bind_param("i", $user_id);
        $genre_stmt->execute();
        $genre_result = $genre_stmt->get_result();
        
        if ($genre_row = $genre_result->fetch_assoc()) {
            $fav_genre = $genre_row['genre'];
            
            // FIXED: Use 'published' instead of 'now_showing'
            $rec_sql = "SELECT m.* 
                        FROM movies m
                        WHERE m.genre LIKE ? 
                          AND m.status = 'published'
                          AND m.movie_id NOT IN (
                              SELECT movie_id FROM bookings 
                              WHERE user_id = ? AND status = 'confirmed'
                          )
                        LIMIT 6";
            $rec_stmt = $conn->prepare($rec_sql);
            $like_genre = "%" . $fav_genre . "%";
            $rec_stmt->bind_param("si", $like_genre, $user_id);
            $rec_stmt->execute();
            $recommended_movies = $rec_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // If no history or no genre movies found, show popular
    if (empty($recommended_movies)) {
        // FIXED: Use 'published' instead of 'now_showing'
        $popular_sql = "SELECT m.*, COUNT(b.booking_id) as booking_count
                        FROM movies m
                        LEFT JOIN bookings b ON m.movie_id = b.movie_id AND b.status = 'confirmed'
                        WHERE m.status = 'published'
                        GROUP BY m.movie_id
                        ORDER BY booking_count DESC
                        LIMIT 6";
        $popular_result = $conn->query($popular_sql);
        $recommended_movies = $popular_result->fetch_all(MYSQLI_ASSOC);
    }
}
// Fixed mode: same logic for everyone
else {
    if ($fixed_logic == 'history') {
        // Get most watched genre across ALL users
        $genre_sql = "SELECT m.genre, COUNT(*) as count 
                      FROM bookings b
                      JOIN movies m ON b.movie_id = m.movie_id
                      WHERE b.status = 'confirmed' AND m.genre IS NOT NULL
                      GROUP BY m.genre
                      ORDER BY count DESC
                      LIMIT 1";
        $genre_result = $conn->query($genre_sql);
        
        if ($genre_row = $genre_result->fetch_assoc()) {
            $fav_genre = $genre_row['genre'];
            
            // FIXED: Use 'published' instead of 'now_showing'
            $rec_sql = "SELECT m.* 
                        FROM movies m
                        WHERE m.genre LIKE ? 
                          AND m.status = 'published'
                          AND m.movie_id NOT IN (
                              SELECT movie_id FROM bookings 
                              WHERE user_id = ? AND status = 'confirmed'
                          )
                        LIMIT 6";
            $rec_stmt = $conn->prepare($rec_sql);
            $like_genre = "%" . $fav_genre . "%";
            $rec_stmt->bind_param("si", $like_genre, $user_id);
            $rec_stmt->execute();
            $recommended_movies = $rec_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Fallback to popular if no history-based results
    if (empty($recommended_movies) || $fixed_logic == 'popular') {
        // FIXED: Use 'published' instead of 'now_showing'
        $popular_sql = "SELECT m.*, COUNT(b.booking_id) as booking_count
                        FROM movies m
                        LEFT JOIN bookings b ON m.movie_id = b.movie_id AND b.status = 'confirmed'
                        WHERE m.status = 'published'
                        GROUP BY m.movie_id
                        ORDER BY booking_count DESC
                        LIMIT 6";
        $popular_result = $conn->query($popular_sql);
        $recommended_movies = $popular_result->fetch_all(MYSQLI_ASSOC);
    }
}

// If still empty, get any published movies as fallback
if (empty($recommended_movies)) {
    $fallback_sql = "SELECT * FROM movies WHERE status = 'published' LIMIT 6";
    $fallback_result = $conn->query($fallback_sql);
    $recommended_movies = $fallback_result->fetch_all(MYSQLI_ASSOC);
}

echo json_encode(['movies' => $recommended_movies]);
?>