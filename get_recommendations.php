<?php
// File: get_recommendations.php
// Simple recommendation engine - NO DUPLICATES GUARANTEED

function getRecommendations($conn, $user_id, $limit = 10) {
    $mode = $_SESSION['recommendation_mode'] ?? 'auto';
    $fixed_logic = $_SESSION['fixed_logic'] ?? 'popular';
    
    // Check if user has watch history
    $history_check = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND status = 'confirmed'");
    $history_check->bind_param("i", $user_id);
    $history_check->execute();
    $has_history = $history_check->get_result()->fetch_assoc()['count'] > 0;
    
    if (($mode == 'auto' && $has_history) || ($mode == 'fixed' && $fixed_logic == 'history')) {
        return getPersonalizedRecommendations($conn, $user_id, $limit);
    }
    
    return getPopularMovies($conn, $user_id, $limit);
}

function getPersonalizedRecommendations($conn, $user_id, $limit = 10) {
    // Get user's watched movie IDs
    $watched_ids = [];
    $watched_sql = "SELECT DISTINCT movie_id FROM bookings WHERE user_id = ? AND status = 'confirmed'";
    $watched_stmt = $conn->prepare($watched_sql);
    $watched_stmt->bind_param("i", $user_id);
    $watched_stmt->execute();
    $watched_result = $watched_stmt->get_result();
    while ($row = $watched_result->fetch_assoc()) {
        $watched_ids[] = $row['movie_id'];
    }
    
    // Get user's top genres
    $genre_sql = "SELECT m.genre, COUNT(*) as count 
                  FROM bookings b
                  JOIN movies m ON b.movie_id = m.movie_id
                  WHERE b.user_id = ? AND b.status = 'confirmed'
                  GROUP BY m.genre
                  ORDER BY count DESC
                  LIMIT 3";
    
    $stmt = $conn->prepare($genre_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $genres = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($genres)) {
        return getPopularMovies($conn, $user_id, $limit);
    }
    
    // Use associative array to prevent duplicates by movie_id
    $recommendations = [];
    
    foreach ($genres as $g) {
        $genre = $g['genre'];
        // FIXED: Use 'published' instead of 'now_showing'
        $rec_sql = "SELECT * FROM movies
                    WHERE genre LIKE ? AND status = 'published'
                    LIMIT 20";
        
        $rec_stmt = $conn->prepare($rec_sql);
        $like_genre = "%" . $genre . "%";
        $rec_stmt->bind_param("s", $like_genre);
        $rec_stmt->execute();
        $genre_movies = $rec_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($genre_movies as $movie) {
            // Skip if already watched
            if (in_array($movie['movie_id'], $watched_ids)) {
                continue;
            }
            // Skip if already in recommendations (using movie_id as key)
            if (!isset($recommendations[$movie['movie_id']])) {
                $recommendations[$movie['movie_id']] = $movie;
            }
            if (count($recommendations) >= $limit) {
                break 2;
            }
        }
    }
    
    // If not enough, fill with popular movies
    if (count($recommendations) < $limit) {
        $needed = $limit - count($recommendations);
        $popular = getPopularMovies($conn, $user_id, $needed);
        foreach ($popular as $pop) {
            if (!isset($recommendations[$pop['movie_id']]) && !in_array($pop['movie_id'], $watched_ids)) {
                $recommendations[$pop['movie_id']] = $pop;
            }
            if (count($recommendations) >= $limit) {
                break;
            }
        }
    }
    
    // Convert back to indexed array
    return array_values($recommendations);
}

function getPopularMovies($conn, $user_id = null, $limit = 10) {
    // FIXED: Use 'published' instead of 'now_showing'
    $sql = "SELECT m.*, COUNT(b.booking_id) as bookings
            FROM movies m
            LEFT JOIN bookings b ON m.movie_id = b.movie_id AND b.status = 'confirmed'
            WHERE m.status = 'published'
            GROUP BY m.movie_id
            ORDER BY bookings DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $movies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($movies)) {
        // FIXED: Use 'published' instead of 'now_showing'
        $fallback = $conn->prepare("SELECT * FROM movies WHERE status = 'published' LIMIT ?");
        $fallback->bind_param("i", $limit);
        $fallback->execute();
        return $fallback->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    return $movies;
}
?>