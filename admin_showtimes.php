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

// Default showtimes to add
$DEFAULT_SHOWTIMES = ['10:00 AM', '1:00 PM', '4:00 PM', '7:30 PM'];

// Handle Add 4 Default Showtimes for a movie
if (isset($_POST['add_default_showtimes'])) {
    $movie_id = (int)$_POST['movie_id'];
    $added_count = 0;
    $skipped_count = 0;
    
    // First, check if any showtimes already exist for this movie
    $check_sql = "SELECT show_time FROM showtimes WHERE movie_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $movie_id);
    $check_stmt->execute();
    $existing_times_result = $check_stmt->get_result();
    
    $existing_times = [];
    while ($row = $existing_times_result->fetch_assoc()) {
        $existing_times[] = $row['show_time'];
    }
    $check_stmt->close();
    
    // Add each default time if it doesn't already exist
    foreach ($DEFAULT_SHOWTIMES as $show_time) {
        if (!in_array($show_time, $existing_times)) {
            $insert_sql = "INSERT INTO showtimes (movie_id, show_time, status, updated_by) VALUES (?, ?, 'active', ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("isi", $movie_id, $show_time, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $added_count++;
            }
            $stmt->close();
        } else {
            $skipped_count++;
        }
    }
    
    if ($added_count > 0) {
        $success_message = "Added $added_count default showtimes for this movie";
        if ($skipped_count > 0) {
            $success_message .= " ($skipped_count already existed)";
        }
    } else {
        $error_message = "All 4 default showtimes already exist for this movie";
    }
}

// Handle Add All Default Showtimes for movies with no showtimes
if (isset($_POST['add_all_default_showtimes'])) {
    // Get all published movies that have NO showtimes
    $movies_no_showtimes_sql = "SELECT m.movie_id, m.title 
                               FROM movies m 
                               WHERE m.status = 'published' 
                               AND m.movie_id NOT IN (SELECT DISTINCT movie_id FROM showtimes)
                               ORDER BY m.title";
    $movies_no_showtimes_result = $conn->query($movies_no_showtimes_sql);
    $movies_without_showtimes = $movies_no_showtimes_result->fetch_all(MYSQLI_ASSOC);
    
    $total_added = 0;
    $movies_updated = 0;
    
    foreach ($movies_without_showtimes as $movie) {
        $movie_id = $movie['movie_id'];
        $added_this_movie = 0;
        
        foreach ($DEFAULT_SHOWTIMES as $show_time) {
            $insert_sql = "INSERT INTO showtimes (movie_id, show_time, status, updated_by) VALUES (?, ?, 'active', ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("isi", $movie_id, $show_time, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $added_this_movie++;
                $total_added++;
            }
            $stmt->close();
        }
        
        if ($added_this_movie > 0) {
            $movies_updated++;
        }
    }
    
    if ($total_added > 0) {
        $success_message = "Added $total_added default showtimes for $movies_updated movie(s) that had no showtimes";
    } else {
        $error_message = "No movies found without showtimes";
    }
}

// Handle Add Showtime (single)
if (isset($_POST['add_showtime'])) {
    $movie_id = (int)$_POST['movie_id'];
    $show_time = trim($_POST['show_time']);
    
    // Validate time format (basic check)
    if (empty($show_time)) {
        $error_message = "Please enter a showtime";
    } else {
        // Check if this movie+time already exists
        $check_sql = "SELECT * FROM showtimes WHERE movie_id = ? AND show_time = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $movie_id, $show_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "This showtime already exists for this movie!";
        } else {
            $insert_sql = "INSERT INTO showtimes (movie_id, show_time, status, updated_by) VALUES (?, ?, 'active', ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("isi", $movie_id, $show_time, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success_message = "Showtime added successfully!";
            } else {
                $error_message = "Error adding showtime.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle Update Showtime
if (isset($_POST['update_showtime'])) {
    $showtime_id = (int)$_POST['showtime_id'];
    $show_time = trim($_POST['show_time']);
    $status = $_POST['status'];
    
    $update_sql = "UPDATE showtimes SET show_time = ?, status = ?, updated_by = ? WHERE showtime_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssii", $show_time, $status, $_SESSION['user_id'], $showtime_id);
    
    if ($stmt->execute()) {
        $success_message = "Showtime updated successfully!";
    } else {
        $error_message = "Error updating showtime.";
    }
    $stmt->close();
}

// Handle Delete Showtime
if (isset($_POST['delete_showtime'])) {
    $showtime_id = (int)$_POST['showtime_id'];
    
    $delete_sql = "DELETE FROM showtimes WHERE showtime_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $showtime_id);
    
    if ($stmt->execute()) {
        $success_message = "Showtime deleted successfully!";
    } else {
        $error_message = "Error deleting showtime.";
    }
    $stmt->close();
}

// Fetch all movies for dropdown
$movies_sql = "SELECT movie_id, title FROM movies WHERE status = 'published' ORDER BY title";
$movies_result = $conn->query($movies_sql);
$movies = $movies_result->fetch_all(MYSQLI_ASSOC);

// Fetch all showtimes with movie details
$showtimes_sql = "SELECT s.*, m.title, m.poster, m.movie_id as movie_id 
                  FROM showtimes s
                  JOIN movies m ON s.movie_id = m.movie_id
                  ORDER BY m.title, FIELD(s.show_time, '10:00 AM', '1:00 PM', '4:00 PM', '7:30 PM', s.show_time)";
$showtimes_result = $conn->query($showtimes_sql);
$showtimes = $showtimes_result->fetch_all(MYSQLI_ASSOC);

// Group showtimes by movie for better display
$showtimes_by_movie = [];
foreach ($showtimes as $showtime) {
    $showtimes_by_movie[$showtime['title']] = [
        'movie_id' => $showtime['movie_id'],
        'poster' => $showtime['poster'],
        'showtimes' => []
    ];
}

foreach ($showtimes as $showtime) {
    $showtimes_by_movie[$showtime['title']]['showtimes'][] = $showtime;
}

// Get movies that have NO showtimes
$movies_no_showtimes_sql = "SELECT m.movie_id, m.title, m.poster 
                           FROM movies m 
                           WHERE m.status = 'published' 
                           AND m.movie_id NOT IN (SELECT DISTINCT movie_id FROM showtimes)
                           ORDER BY m.title";
$movies_no_showtimes_result = $conn->query($movies_no_showtimes_sql);
$movies_without_showtimes = $movies_no_showtimes_result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_showtimes,
                COUNT(DISTINCT movie_id) as movies_with_showtimes,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_showtimes,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_showtimes
              FROM showtimes";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get count of movies without showtimes
$movies_without_count = count($movies_without_showtimes);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Showtime Management - ARVR Cinema</title>
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
        
        .btn-primary {
            background: #ff4d6d;
            color: white;
        }
        
        .btn-primary:hover {
            background: #ff6b8a;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
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
        
        .alert-bar {
            background: rgba(255, 193, 7, 0.15);
            border-left: 4px solid #ffc107;
            padding: 15px 40px;
            margin: 0 40px 20px 40px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .alert-bar .alert-text {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-bar .alert-text i {
            color: #ffc107;
            font-size: 20px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .showtime-section {
            padding: 20px 40px;
        }
        
        .form-container {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: #ff4d6d;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.5);
            color: white;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #ff4d6d;
        }
        
        .time-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .time-input-group select {
            flex: 1;
        }
        
        .time-input-group span {
            opacity: 0.6;
        }
        
        .time-input-group input {
            flex: 2;
        }
        
        .movies-grid {
            display: grid;
            gap: 30px;
        }
        
        .movie-card {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 20px;
            transition: 0.3s;
        }
        
        .movie-card:hover {
            background: rgba(255,255,255,0.08);
        }
        
        .movie-card.missing-showtimes {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .movie-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-wrap: wrap;
        }
        
        .movie-poster {
            width: 60px;
            height: 90px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .movie-title {
            font-size: 20px;
            font-weight: 600;
        }
        
        .movie-title small {
            font-size: 12px;
            font-weight: normal;
            color: #ffc107;
        }
        
        .times-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .time-badge {
            background: rgba(255,255,255,0.1);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .time-badge.active {
            background: rgba(76, 175, 80, 0.2);
            border-left: 3px solid #4CAF50;
        }
        
        .time-badge.cancelled {
            background: rgba(244, 67, 54, 0.2);
            border-left: 3px solid #f44336;
            text-decoration: line-through;
        }
        
        .time-badge-actions {
            display: inline-flex;
            gap: 5px;
            margin-left: 10px;
        }
        
        .time-badge-actions button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            padding: 2px 5px;
            border-radius: 4px;
        }
        
        .edit-time-btn {
            color: #ffc107;
        }
        
        .delete-time-btn {
            color: #dc3545;
        }
        
        .add-time-form {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .add-time-form select,
        .add-time-form input {
            padding: 8px 12px;
            border-radius: 8px;
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        
        .default-times-btn {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid #28a745;
        }
        
        .default-times-btn:hover {
            background: #28a745;
            color: white;
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
            max-width: 450px;
            width: 90%;
        }
        
        .modal-content h3 {
            margin-bottom: 20px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        
        .no-showtimes {
            text-align: center;
            padding: 50px;
        }
        
        .warning-badge {
            background: #ffc107;
            color: #000;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        @media (max-width: 768px) {
            .staff-header {
                padding: 15px 20px;
            }
            
            .stats-container,
            .showtime-section,
            .alert-bar {
                padding: 15px 20px;
                margin: 0 20px 20px 20px;
            }
            
            .movie-header {
                flex-direction: column;
                text-align: center;
            }
            
            .time-input-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="staff-header">
        <div class="logo">
            <h1>🎬 Showtime Management</h1>
            <p>Manage movie showtimes</p>
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
            <i class="fas fa-clock"></i>
            <div class="number"><?php echo $stats['total_showtimes']; ?></div>
            <div>Total Showtimes</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-film"></i>
            <div class="number"><?php echo $stats['movies_with_showtimes']; ?></div>
            <div>Movies with Showtimes</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-play-circle"></i>
            <div class="number"><?php echo $stats['active_showtimes']; ?></div>
            <div>Active</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-ban"></i>
            <div class="number"><?php echo $stats['cancelled_showtimes']; ?></div>
            <div>Cancelled</div>
        </div>
    </div>
    
    <!-- Alert for movies without showtimes -->
    <?php if ($movies_without_count > 0): ?>
        <div class="alert-bar">
            <div class="alert-text">
                <i class="fas fa-exclamation-triangle"></i>
                <span><strong><?php echo $movies_without_count; ?> movie(s)</strong> don't have any showtimes yet!</span>
            </div>
            <form method="POST">
                <button type="submit" name="add_all_default_showtimes" class="btn btn-success btn-sm">
                    <i class="fas fa-calendar-plus"></i> Add 4 Default Showtimes for All Missing Movies
                </button>
            </form>
        </div>
    <?php endif; ?>
    
    <!-- Global Add Showtime Form -->
    <div class="showtime-section">
        <div class="form-container">
            <h3><i class="fas fa-plus-circle"></i> Add New Showtime</h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-film"></i> Select Movie</label>
                        <select name="movie_id" required>
                            <option value="">-- Choose a movie --</option>
                            <?php foreach ($movies as $movie): ?>
                                <option value="<?php echo $movie['movie_id']; ?>">
                                    <?php echo htmlspecialchars($movie['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Show Time</label>
                        <div class="time-input-group">
                            <select id="preset_time_global">
                                <option value="">Quick select</option>
                                <option value="10:00 AM">10:00 AM</option>
                                <option value="1:00 PM">1:00 PM</option>
                                <option value="4:00 PM">4:00 PM</option>
                                <option value="7:30 PM">7:30 PM</option>
                            </select>
                            <span>or</span>
                            <input type="text" name="show_time" id="custom_time_global" placeholder="Custom (e.g., 2:30 PM, 11:15 AM)" required>
                        </div>
                        <small style="opacity: 0.6;">Examples: 9:30 AM, 1:45 PM, 11:15 PM, 12:00 PM</small>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="add_showtime" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Showtime
                    </button>
                </div>
            </form>
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
    
    <!-- Movies Without Showtimes Section -->
    <?php if (!empty($movies_without_showtimes)): ?>
        <div class="showtime-section">
            <div class="section-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Movies Missing Showtimes <span class="warning-badge">Action Required</span></h3>
                <form method="POST">
                    <button type="submit" name="add_all_default_showtimes" class="btn btn-success">
                        <i class="fas fa-calendar-plus"></i> Add 4 Default Showtimes for All
                    </button>
                </form>
            </div>
            
            <div class="movies-grid">
                <?php foreach ($movies_without_showtimes as $movie): ?>
                    <div class="movie-card missing-showtimes">
                        <div class="movie-header">
                            <img src="<?php echo htmlspecialchars($movie['poster']); ?>" 
                                 onerror="this.src='https://via.placeholder.com/60x90'"
                                 class="movie-poster">
                            <div>
                                <div class="movie-title">
                                    <?php echo htmlspecialchars($movie['title']); ?>
                                    <small><i class="fas fa-clock"></i> No showtimes yet</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="times-container">
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                                <span style="opacity: 0.6;">Add default showtimes:</span>
                                <?php foreach ($DEFAULT_SHOWTIMES as $default_time): ?>
                                    <span class="time-badge" style="background: rgba(40, 167, 69, 0.2); border-left-color: #28a745;">
                                        <?php echo $default_time; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            
                            <form method="POST" style="margin-top: 15px;">
                                <input type="hidden" name="movie_id" value="<?php echo $movie['movie_id']; ?>">
                                <button type="submit" name="add_default_showtimes" class="btn btn-success btn-sm default-times-btn">
                                    <i class="fas fa-plus-circle"></i> Add These 4 Showtimes for <?php echo htmlspecialchars($movie['title']); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Showtime Management -->
    <div class="showtime-section">
        <div class="section-header">
            <h3><i class="fas fa-list"></i> Current Showtimes</h3>
            <small>Click edit ✏️ to change time or status</small>
        </div>
        
        <div class="movies-grid">
            <?php if (!empty($showtimes_by_movie)): ?>
                <?php foreach ($showtimes_by_movie as $movie_title => $movie_data): ?>
                    <div class="movie-card">
                        <div class="movie-header">
                            <img src="<?php echo htmlspecialchars($movie_data['poster']); ?>" 
                                 onerror="this.src='https://via.placeholder.com/60x90'"
                                 class="movie-poster">
                            <div>
                                <div class="movie-title"><?php echo htmlspecialchars($movie_title); ?></div>
                                <small>Showtimes: <?php echo count($movie_data['showtimes']); ?></small>
                            </div>
                        </div>
                        
                        <div class="times-container">
                            <?php foreach ($movie_data['showtimes'] as $showtime): ?>
                                <div class="time-badge <?php echo $showtime['status']; ?>">
                                    <?php echo htmlspecialchars($showtime['show_time']); ?>
                                    <div class="time-badge-actions">
                                        <button class="edit-time-btn" onclick="openEditModal(<?php echo $showtime['showtime_id']; ?>, '<?php echo htmlspecialchars($showtime['show_time']); ?>', '<?php echo $showtime['status']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="delete-time-btn" onclick="openDeleteModal(<?php echo $showtime['showtime_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- Add new showtime for this movie -->
                            <form method="POST" class="add-time-form">
                                <input type="hidden" name="movie_id" value="<?php echo $movie_data['movie_id']; ?>">
                                <div style="display: flex; gap: 8px;">
                                    <select id="preset_time_<?php echo $movie_data['movie_id']; ?>">
                                        <option value="">Quick</option>
                                        <option value="10:00 AM">10:00 AM</option>
                                        <option value="1:00 PM">1:00 PM</option>
                                        <option value="4:00 PM">4:00 PM</option>
                                        <option value="7:30 PM">7:30 PM</option>
                                    </select>
                                    <input type="text" name="show_time" id="custom_time_<?php echo $movie_data['movie_id']; ?>" placeholder="Custom time" style="width: 120px;">
                                    <button type="submit" name="add_showtime" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>
                            </form>
                            
                            <script>
                                // Auto-fill custom time when preset is selected for this movie
                                document.getElementById('preset_time_<?php echo $movie_data['movie_id']; ?>').addEventListener('change', function() {
                                    if (this.value) {
                                        document.getElementById('custom_time_<?php echo $movie_data['movie_id']; ?>').value = this.value;
                                    }
                                });
                            </script>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-showtimes">
                    <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 20px;"></i>
                    <h3>No showtimes found</h3>
                    <p>Add showtimes using the form above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-edit"></i> Edit Showtime</h3>
            <form method="POST">
                <input type="hidden" name="showtime_id" id="edit_showtime_id">
                
                <div class="form-group">
                    <label>Show Time</label>
                    <div class="time-input-group">
                        <select id="edit_preset_time">
                            <option value="">Quick select</option>
                            <option value="10:00 AM">10:00 AM</option>
                            <option value="1:00 PM">1:00 PM</option>
                            <option value="4:00 PM">4:00 PM</option>
                            <option value="7:30 PM">7:30 PM</option>
                        </select>
                        <span>or</span>
                        <input type="text" name="show_time" id="edit_custom_time" placeholder="Custom time" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="active">Active</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" name="update_showtime" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-trash"></i> Delete Showtime</h3>
            <p>Are you sure you want to delete this showtime? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="showtime_id" id="delete_showtime_id">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" name="delete_showtime" class="btn btn-danger">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Global preset time auto-fill
        document.getElementById('preset_time_global').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('custom_time_global').value = this.value;
            }
        });
        
        // Edit modal preset time auto-fill
        document.getElementById('edit_preset_time').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('edit_custom_time').value = this.value;
            }
        });
        
        function openEditModal(id, showTime, status) {
            document.getElementById('edit_showtime_id').value = id;
            document.getElementById('edit_custom_time').value = showTime;
            document.getElementById('edit_status').value = status;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function openDeleteModal(id) {
            document.getElementById('delete_showtime_id').value = id;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>