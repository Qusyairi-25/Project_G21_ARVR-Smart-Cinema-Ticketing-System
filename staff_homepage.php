<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has staff privileges
if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    header("Location: homepage.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Staff';

// Handle movie deletion
if (isset($_POST['delete_movie'])) {
    $movie_id = (int)$_POST['movie_id'];
    
    // First delete related feedback and wishlist entries
    $conn->begin_transaction();
    try {
        $delete_feedback = $conn->prepare("DELETE FROM movie_feedback WHERE movie_id = ?");
        $delete_feedback->bind_param("i", $movie_id);
        $delete_feedback->execute();
        
        $delete_wishlist = $conn->prepare("DELETE FROM wishlist WHERE movie_id = ?");
        $delete_wishlist->bind_param("i", $movie_id);
        $delete_wishlist->execute();
        
        $delete_movie = $conn->prepare("DELETE FROM movies WHERE movie_id = ?");
        $delete_movie->bind_param("i", $movie_id);
        $delete_movie->execute();
        
        $conn->commit();
        $success_message = "Movie deleted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error deleting movie: " . $e->getMessage();
    }
}

// Handle status update - UPDATED to support draft/published/coming_soon
if (isset($_POST['update_status'])) {
    $movie_id = (int)$_POST['movie_id'];
    $new_status = $_POST['status'];
    $updated_by = $user_id;
    
    $update_sql = "UPDATE movies SET status = ?, updated_by = ? WHERE movie_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sii", $new_status, $updated_by, $movie_id);
    
    if ($update_stmt->execute()) {
        // If publishing, set published_at timestamp
        if ($new_status == 'published') {
            $pub_sql = "UPDATE movies SET published_at = NOW() WHERE movie_id = ? AND published_at IS NULL";
            $pub_stmt = $conn->prepare($pub_sql);
            $pub_stmt->bind_param("i", $movie_id);
            $pub_stmt->execute();
        }
        // If setting to coming_soon, set coming_soon_date if not set
        elseif ($new_status == 'coming_soon') {
            $cs_sql = "UPDATE movies SET coming_soon_date = COALESCE(coming_soon_date, NOW()) WHERE movie_id = ?";
            $cs_stmt = $conn->prepare($cs_sql);
            $cs_stmt->bind_param("i", $movie_id);
            $cs_stmt->execute();
        }
        $success_message = "Movie status updated to " . ucfirst(str_replace('_', ' ', $new_status)) . "!";
    } else {
        $error_message = "Error updating status.";
    }
}

// Get search query if present
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Fetch all movies for staff view - show ALL statuses, with search filter (title only)
if (!empty($search_query)) {
    // Search ONLY by title
    $search_param = "%" . $conn->real_escape_string($search_query) . "%";
    $sql = "SELECT * FROM movies WHERE title LIKE ?
            ORDER BY 
            CASE status 
                WHEN 'draft' THEN 1 
                WHEN 'coming_soon' THEN 2 
                WHEN 'published' THEN 3 
                ELSE 4 
            END, 
            movie_id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM movies ORDER BY 
            CASE status 
                WHEN 'draft' THEN 1 
                WHEN 'coming_soon' THEN 2 
                WHEN 'published' THEN 3 
                ELSE 4 
            END, 
            movie_id DESC";
    $result = $conn->query($sql);
}
$movies = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics - UPDATED for new statuses
$stats_sql = "SELECT 
                COUNT(*) as total_movies,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN status = 'coming_soon' THEN 1 ELSE 0 END) as coming_soon,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
                (SELECT COUNT(*) FROM movie_feedback) as total_reviews
              FROM movies";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard - Movie Management</title>
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
        
        /* Staff Header */
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
        
        .logo p {
            font-size: 12px;
            opacity: 0.7;
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
        
        .user-info .username {
            color: #ff4d6d;
            font-weight: 600;
        }
        
        .logout-btn {
            background: rgba(255,77,109,0.2);
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            transition: 0.3s;
        }
        
        .logout-btn:hover {
            background: #ff4d6d;
        }
        
        /* Stats Cards */
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
            transition: 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.08);
        }
        
        .stat-card i {
            font-size: 40px;
            color: #ff4d6d;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
        }
        
        .stat-card .label {
            font-size: 14px;
            opacity: 0.7;
        }
        
        /* Action Buttons */
        .action-buttons {
            padding: 0 40px 20px 40px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
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
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-info {
            background: #2196F3;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        /* Movies Table */
        .movies-section {
            padding: 20px 40px;
        }
        
        .movies-section h2 {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Search Bar Styles */
        .search-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-box {
            display: flex;
            flex: 1;
            min-width: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.2);
            transition: 0.3s;
        }
        
        .search-box:focus-within {
            border-color: #ff4d6d;
            box-shadow: 0 0 10px rgba(255,77,109,0.3);
        }
        
        .search-box input {
            flex: 1;
            padding: 12px 20px;
            background: transparent;
            border: none;
            color: white;
            font-size: 14px;
            outline: none;
        }
        
        .search-box input::placeholder {
            color: rgba(255,255,255,0.5);
        }
        
        .search-box button {
            background: #ff4d6d;
            border: none;
            padding: 0 25px;
            color: white;
            cursor: pointer;
            font-size: 16px;
            transition: 0.3s;
        }
        
        .search-box button:hover {
            background: #e6395c;
        }
        
        .clear-search {
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 50px;
            text-decoration: none;
            color: white;
            font-size: 13px;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .clear-search:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .search-info {
            font-size: 14px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 15px;
            padding: 8px 15px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            display: inline-block;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 6px 16px;
            border-radius: 20px;
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            font-size: 13px;
            transition: 0.3s;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: #ff4d6d;
        }
        
        .movies-table {
            width: 100%;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            background: rgba(255,255,255,0.03);
            border-radius: 15px;
            overflow: hidden;
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
        
        .poster-thumb {
            width: 50px;
            height: 75px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-published { background: #4CAF50; }
        .status-coming_soon { background: #2196F3; }
        .status-draft { background: #FF9800; color: #000; }
        
        .action-icons {
            display: flex;
            gap: 10px;
        }
        
        .action-icons a, .action-icons button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
            transition: 0.3s;
        }
        
        .action-icons .edit { color: #4CAF50; }
        .action-icons .delete { color: #f44336; }
        .action-icons .view { color: #2196F3; }
        
        .action-icons a:hover, .action-icons button:hover {
            transform: scale(1.1);
        }
        
        /* Status Select */
        .status-select {
            padding: 5px 10px;
            border-radius: 5px;
            background: rgba(0,0,0,0.5);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        /* Messages */
        .message {
            padding: 15px 40px;
            margin: 0 40px 20px 40px;
            border-radius: 10px;
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
        
        /* Modal */
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
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        
        .no-results {
            text-align: center;
            padding: 50px;
            color: rgba(255,255,255,0.6);
        }
        
        @media (max-width: 768px) {
            .staff-header {
                padding: 15px 20px;
            }
            
            .stats-container {
                padding: 20px;
            }
            
            .movies-section {
                padding: 20px;
            }
            
            th, td {
                padding: 10px;
                font-size: 12px;
            }
            
            .search-container {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="staff-header">
        <div class="logo">
            <h1>🎬 
                <?php 
                if ($_SESSION['role'] == 'admin') {
                    echo "Admin Control Panel";
                } else {
                    echo "Staff Control Panel";
                }
                ?>
            </h1>
            <p>Movie Management System</p>
        </div>

        <div class="user-info">
            <span class="staff-badge"><i class="fas fa-shield-alt"></i> 
                <?php 
                if ($_SESSION['role'] == 'admin') {
                    echo 'ADMIN';
                } else {
                    echo 'STAFF';
                }
                ?>
            </span>
            <span class="username"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <i class="fas fa-film"></i>
            <div class="number"><?php echo $stats['total_movies']; ?></div>
            <div class="label">Total Movies</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-check-circle"></i>
            <div class="number"><?php echo $stats['published']; ?></div>
            <div class="label">Published</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-clock"></i>
            <div class="number"><?php echo $stats['coming_soon']; ?></div>
            <div class="label">Coming Soon</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-pencil-alt"></i>
            <div class="number"><?php echo $stats['draft']; ?></div>
            <div class="label">Draft</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <div class="number"><?php echo $stats['total_users']; ?></div>
            <div class="label">Total Users</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-star"></i>
            <div class="number"><?php echo $stats['total_reviews']; ?></div>
            <div class="label">Total Reviews</div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="homepage.php" class="btn btn-secondary"><i class="fas fa-eye"></i> View User Homepage</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="chatbot_logs.php" class="btn btn-secondary"><i class="fas fa-robot"></i> Chatbot Logs</a>
            <a href="admin_recommendation.php" class="btn btn-secondary"><i class="fas fa-thumbs-up"></i> Recommendation Engine</a>
            <a href="admin_early_access.php" class="btn btn-secondary"><i class="fas fa-clock"></i> Early Access</a>
            <a href="admin_showtimes.php" class="btn btn-secondary"><i class="fas fa-calendar-alt"></i> Showtime Management</a>
        <?php endif; ?>
        <a href="feedback_management.php" class="btn btn-secondary"><i class="fas fa-comments"></i> View All Feedback</a>
        <a href="user_management.php" class="btn btn-secondary"><i class="fas fa-users-cog"></i> User Management</a>
        <a href="booking_management.php" class="btn btn-secondary"><i class="fas fa-ticket-alt"></i> Booking Management</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="discount_management.php" class="btn btn-primary" style="background: linear-gradient(45deg, #6a1b9a, #9c27b0);"><i class="fas fa-tags"></i> Discount Management</a>
        <?php endif; ?>
        <a href="add_movie.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Movie</a>
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
    
    <!-- Movies Table -->
    <div class="movies-section">
        <h2><i class="fas fa-list"></i> Movie Management</h2>
        
        <!-- Search Bar - Search by Title Only -->
        <div class="search-container">
            <form method="GET" style="flex: 1; display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="search-box">
                    <input type="text" name="search" placeholder="🔍 Search by movie title..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>
                <?php if (!empty($search_query)): ?>
                    <a href="?filter=<?php echo $filter; ?>" class="clear-search"><i class="fas fa-times"></i> Clear Search</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Search Info -->
        <?php if (!empty($search_query)): ?>
            <div class="search-info">
                <i class="fas fa-search"></i> Search results for title: "<strong><?php echo htmlspecialchars($search_query); ?></strong>" 
                (<?php echo count($movies); ?> movies found)
            </div>
        <?php endif; ?>
        
        <!-- Status Filter -->
        <div class="filter-buttons">
            <a href="?<?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>filter=all" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?<?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>filter=published" class="filter-btn <?php echo $filter == 'published' ? 'active' : ''; ?>">Published</a>
            <a href="?<?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>filter=coming_soon" class="filter-btn <?php echo $filter == 'coming_soon' ? 'active' : ''; ?>">Coming Soon</a>
            <a href="?<?php echo !empty($search_query) ? 'search=' . urlencode($search_query) . '&' : ''; ?>filter=draft" class="filter-btn <?php echo $filter == 'draft' ? 'active' : ''; ?>">Draft</a>
        </div>
        
        <div class="movies-table">
            <?php if (count($movies) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Poster</th>
                            <th>Title</th>
                            <th>Genre</th>
                            <th>Duration</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Apply filter if selected
                        foreach ($movies as $movie):
                            if ($filter != 'all' && $movie['status'] != $filter) continue;
                        ?>
                        <tr>
                            <td><?php echo $movie['movie_id']; ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($movie['poster']); ?>" 
                                     class="poster-thumb" 
                                     onerror="this.src='https://via.placeholder.com/50x75'">
                            </td>
                            <td>
                                <?php echo htmlspecialchars($movie['title']); ?>
                                <?php if ($movie['status'] == 'draft'): ?>
                                    <span class="status-badge status-draft" style="margin-left: 8px;">DRAFT</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($movie['genre']); ?></td>
                            <td><?php echo floor($movie['duration']/60) . 'h ' . ($movie['duration']%60) . 'm'; ?></td>
                            <td>RM <?php echo number_format($movie['price'], 2); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="movie_id" value="<?php echo $movie['movie_id']; ?>">
                                    <select name="status" class="status-select" onchange="this.form.submit()">
                                        <option value="draft" <?php echo $movie['status'] == 'draft' ? 'selected' : ''; ?>>📝 Draft</option>
                                        <option value="coming_soon" <?php echo $movie['status'] == 'coming_soon' ? 'selected' : ''; ?>>⏰ Coming Soon</option>
                                        <option value="published" <?php echo $movie['status'] == 'published' ? 'selected' : ''; ?>>✅ Published</option>
                                    </select>
                                    <input type="hidden" name="update_status">
                                </form>
                            </td>
                            <td class="action-icons">
                                <a href="edit_movie.php?id=<?php echo $movie['movie_id']; ?>" class="edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="confirmDelete(<?php echo $movie['movie_id']; ?>, '<?php echo htmlspecialchars($movie['title']); ?>')" class="delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-film" style="font-size: 50px; opacity: 0.5; margin-bottom: 20px; display: block;"></i>
                    <?php if (!empty($search_query)): ?>
                        <h3>No movies found with title matching "<?php echo htmlspecialchars($search_query); ?>"</h3>
                        <p>Try searching with different keywords or clear the search to see all movies.</p>
                    <?php else: ?>
                        <h3>No movies available</h3>
                        <p>Click "Add New Movie" to get started.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
            <p>Are you sure you want to delete "<span id="deleteMovieTitle"></span>"?</p>
            <p style="color: #f44336; font-size: 14px; margin-top: 10px;">This action cannot be undone and will also delete all reviews and wishlist entries for this movie.</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="movie_id" id="deleteMovieId">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="delete_movie" class="btn btn-danger">Delete Movie</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function confirmDelete(movieId, movieTitle) {
            document.getElementById('deleteMovieId').value = movieId;
            document.getElementById('deleteMovieTitle').textContent = movieTitle;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>