<?php
session_start();

// Check if user is logged in and has staff/admin privileges
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    header("Location: homepage.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$movie_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($movie_id == 0) {
    header("Location: staff_homepage.php");
    exit();
}

// Fetch movie details
$sql = "SELECT * FROM movies WHERE movie_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $movie_id);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();

if (!$movie) {
    die("Movie not found.");
}

// Handle form submission for updating movie
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_movie'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $genre = $_POST['genre'];
    $duration = (int)$_POST['duration'];
    $rating = $_POST['rating'] ?: null;
    $release_date = $_POST['release_date'] ?: null;
    $poster = $_POST['poster'];
    $price = (float)$_POST['price'];
    $status = $_POST['status'];
    
    $update_sql = "UPDATE movies SET 
                   title = ?, description = ?, genre = ?, duration = ?, 
                   rating = ?, release_date = ?, poster = ?, price = ?, 
                   status = ?, updated_by = ? 
                   WHERE movie_id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssisssdsii", 
        $title, $description, $genre, $duration, 
        $rating, $release_date, $poster, $price, 
        $status, $user_id, $movie_id);
    
    if ($update_stmt->execute()) {
        // If publishing, set published_at timestamp
        if ($status == 'published' && ($movie['status'] != 'published')) {
            $pub_sql = "UPDATE movies SET published_at = NOW() WHERE movie_id = ?";
            $pub_stmt = $conn->prepare($pub_sql);
            $pub_stmt->bind_param("i", $movie_id);
            $pub_stmt->execute();
        }
        $success_message = "Movie updated successfully!";
        // Refresh movie data
        $stmt->execute();
        $movie = $stmt->get_result()->fetch_assoc();
    } else {
        $error_message = "Error updating movie: " . $conn->error;
    }
}

// Format duration for display
$hours = floor($movie['duration'] / 60);
$minutes = $movie['duration'] % 60;
$formatted_duration = $hours . 'h ' . $minutes . 'm';

// Get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'published': return 'status-published';
        case 'coming_soon': return 'status-coming';
        case 'draft': return 'status-draft';
        default: return 'status-draft';
    }
}

function getStatusName($status) {
    switch ($status) {
        case 'published': return 'Published';
        case 'coming_soon': return 'Coming Soon';
        case 'draft': return 'Draft';
        default: return ucfirst($status);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Movie - <?php echo htmlspecialchars($movie['title']); ?></title>
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
        
        /* Header */
        .admin-header {
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
        
        .back-link {
            color: #ff4d6d;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        /* Container */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 40px;
        }
        
        /* Messages */
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
        
        /* Card */
        .card {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .card h2 {
            color: #ff4d6d;
            font-size: 22px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #ccc;
        }
        
        .form-group input, 
        .form-group textarea, 
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.5);
            color: white;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-group input:focus, 
        .form-group textarea:focus, 
        .form-group select:focus {
            outline: none;
            border-color: #ff4d6d;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            font-size: 11px;
            opacity: 0.6;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .status-published { background: #4CAF50; }
        .status-coming { background: #2196F3; }
        .status-draft { background: #FF9800; color: #000; }
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
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
            background: #dc3545;
            color: white;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        /* Poster Preview */
        .poster-preview {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .poster-preview img {
            max-width: 180px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }
        
        /* Danger Zone */
        .danger-zone {
            border: 1px solid rgba(220,53,69,0.3);
            text-align: center;
        }
        
        .danger-zone h2 {
            color: #dc3545;
            justify-content: center;
        }
        
        .danger-zone p {
            margin-bottom: 20px;
            font-size: 14px;
            opacity: 0.8;
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
            max-width: 450px;
            width: 90%;
        }
        
        .modal-content h3 {
            margin-bottom: 15px;
            color: #dc3545;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="logo">
            <h1>🎬 Edit Movie</h1>
            <a href="staff_homepage.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        <div>
            <span class="status-badge <?php echo getStatusBadgeClass($movie['status']); ?>">
                <?php echo getStatusName($movie['status']); ?>
            </span>
            <span style="margin-left: 15px;">
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?>
                (<?php echo strtoupper($role); ?>)
            </span>
        </div>
    </div>
    
    <div class="container">
        <?php if ($success_message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Edit Movie Form -->
        <div class="card">
            <h2><i class="fas fa-pen-to-square"></i> Edit Movie Details</h2>
            
            <div class="poster-preview">
                <img src="<?php echo htmlspecialchars($movie['poster'] ?: 'https://via.placeholder.com/200x300?text=No+Poster'); ?>" 
                     alt="Poster" id="posterPreview">
            </div>
            
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-film"></i> Title *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($movie['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Genre *</label>
                        <input type="text" name="genre" value="<?php echo htmlspecialchars($movie['genre']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-hourglass-half"></i> Duration (minutes) *</label>
                        <input type="number" name="duration" value="<?php echo $movie['duration']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Rating (0-10)</label>
                        <input type="number" step="0.1" name="rating" value="<?php echo $movie['rating']; ?>" min="0" max="10">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Release Date</label>
                        <input type="date" name="release_date" value="<?php echo $movie['release_date']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-ticket-alt"></i> Price (RM) *</label>
                        <input type="number" step="0.01" name="price" value="<?php echo $movie['price']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Status *</label>
                        <select name="status">
                            <option value="draft" <?php echo $movie['status'] == 'draft' ? 'selected' : ''; ?>>
                                📝 Draft (Hidden from public)
                            </option>
                            <option value="coming_soon" <?php echo $movie['status'] == 'coming_soon' ? 'selected' : ''; ?>>
                                ⏰ Coming Soon (Visible, no booking)
                            </option>
                            <option value="published" <?php echo $movie['status'] == 'published' ? 'selected' : ''; ?>>
                                ✅ Published (Fully available)
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-image"></i> Poster URL</label>
                        <input type="text" name="poster" value="<?php echo htmlspecialchars($movie['poster']); ?>" 
                               id="posterUrl" onchange="updatePosterPreview()">
                        <small>Enter image URL (JPG, PNG, etc.)</small>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" placeholder="Movie description..."><?php echo htmlspecialchars($movie['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="button-group">
                    <a href="movie-details.php?id=<?php echo $movie_id; ?>" class="btn btn-secondary" target="_blank">
                        <i class="fas fa-eye"></i> View on Site
                    </a>
                    <button type="submit" name="update_movie" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Danger Zone -->
        <div class="card danger-zone">
            <h2><i class="fas fa-exclamation-triangle"></i> Danger Zone</h2>
            <p>Once you delete a movie, all related bookings, reviews, and wishlist entries will be permanently removed.</p>
            <button onclick="openDeleteModal()" class="btn btn-danger">
                <i class="fas fa-trash"></i> Delete Movie Permanently
            </button>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Delete Movie</h3>
            <p>Are you sure you want to delete <strong>"<?php echo htmlspecialchars($movie['title']); ?>"</strong>?</p>
            <p style="color: #f44336; font-size: 14px; margin-top: 10px;">
                This action cannot be undone.
            </p>
            <form method="POST">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="delete_movie" class="btn btn-danger">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function updatePosterPreview() {
            const posterUrl = document.getElementById('posterUrl').value;
            const previewImg = document.getElementById('posterPreview');
            if (posterUrl) {
                previewImg.src = posterUrl;
            } else {
                previewImg.src = 'https://via.placeholder.com/200x300?text=No+Poster';
            }
        }
        
        function openDeleteModal() {
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Confirm before status change that removes public visibility
        const statusSelect = document.querySelector('select[name="status"]');
        const originalStatus = '<?php echo $movie['status']; ?>';
        
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                if (originalStatus === 'published' && this.value !== 'published') {
                    if (!confirm('Warning: Changing from Published to ' + this.options[this.selectedIndex].text + ' will hide this movie from users. Continue?')) {
                        this.value = originalStatus;
                    }
                }
            });
        }
    </script>
</body>
</html>