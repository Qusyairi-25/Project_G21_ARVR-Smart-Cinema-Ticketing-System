<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Test database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

$success_message = '';
$error_message = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if add_movie button was clicked
    if (isset($_POST['add_movie'])) {
        
        // Get form data
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $genre = trim($_POST['genre'] ?? '');
        $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : 0;
        $rating = !empty($_POST['rating']) ? $_POST['rating'] : null;
        $release_date = !empty($_POST['release_date']) ? $_POST['release_date'] : null;
        $poster = trim($_POST['poster'] ?? '');
        $price = !empty($_POST['price']) ? (float)$_POST['price'] : 0;
        $status = $_POST['status'] ?? 'draft';
        $updated_by = $user_id;
        
        // Simple validation
        $errors = [];
        
        if (empty($title)) $errors[] = "Title is required";
        if (empty($genre)) $errors[] = "Genre is required";
        if ($duration <= 0) $errors[] = "Valid duration is required";
        if ($price <= 0) $errors[] = "Valid price is required";
        if (empty($poster)) $errors[] = "Poster URL is required";
        
        // Convert date format if needed
        if (!empty($release_date)) {
            $timestamp = strtotime($release_date);
            if ($timestamp !== false) {
                $release_date = date('Y-m-d', $timestamp);
            }
        } else {
            $release_date = null;
        }
        
        if (empty($errors)) {
            // Prepare SQL statement
            $sql = "INSERT INTO movies (title, description, genre, duration, rating, release_date, poster, price, status, updated_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                // Bind parameters - rating as string for varchar column
                $stmt->bind_param("sssisssdsi", 
                    $title, $description, $genre, $duration, 
                    $rating, $release_date, $poster, $price, 
                    $status, $updated_by);
                
                if ($stmt->execute()) {
                    $movie_id = $conn->insert_id;
                    
                    // Set published_at if status is published
                    if ($status == 'published') {
                        $conn->query("UPDATE movies SET published_at = NOW() WHERE movie_id = $movie_id");
                    }
                    // Set coming_soon_date if status is coming_soon
                    elseif ($status == 'coming_soon') {
                        $conn->query("UPDATE movies SET coming_soon_date = NOW() WHERE movie_id = $movie_id");
                    }
                    
                    $success_message = "Movie '" . htmlspecialchars($title) . "' added successfully! (ID: $movie_id)";
                    
                    // Clear POST data to reset form
                    $_POST = array();
                } else {
                    $error_message = "Database error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = "Prepare failed: " . $conn->error;
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
}

// Predefined genre list
$genres = ['Action', 'Horror', 'Comedy', 'Romance', 'Sci-Fi', 'Fantasy', 'Thriller'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Movie - Staff Panel</title>
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
            margin-top: 10px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .staff-badge {
            background: #ff4d6d;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 40px;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 5px;
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
        
        .form-group label .required {
            color: #ff4d6d;
            margin-left: 3px;
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
            transition: 0.3s;
        }
        
        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>');
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px;
        }
        
        .form-group select option {
            background: #1a1a1a;
            color: white;
            padding: 10px;
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
            box-shadow: 0 0 10px rgba(255,77,109,0.2);
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            font-size: 11px;
            opacity: 0.6;
        }
        
        .form-group .hint {
            color: #ff9800;
            font-size: 11px;
            margin-top: 5px;
        }
        
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
        
        .poster-preview .preview-label {
            margin-top: 10px;
            font-size: 12px;
            opacity: 0.7;
        }
        
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
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #800020, #ff4d6d);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,77,109,0.3);
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        .info-box {
            background: rgba(33, 150, 243, 0.1);
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box i {
            color: #2196F3;
            margin-right: 10px;
        }
        
        .info-box p {
            font-size: 13px;
            color: rgba(255,255,255,0.8);
        }
        
        @media (max-width: 768px) {
            .admin-header {
                padding: 15px 20px;
            }
            
            .container {
                padding: 20px;
            }
            
            .card {
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
        
        .loading {
            animation: pulse 1s infinite;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="logo">
            <h1>🎬 Add New Movie</h1>
            <a href="staff_homepage.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        <div class="user-info">
            <span class="staff-badge">
                <i class="fas fa-shield-alt"></i> <?php echo strtoupper($role); ?>
            </span>
            <span>
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?>
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
        
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <p>Fill in the movie details below. Fields marked with <span style="color:#ff4d6d">*</span> are required.</p>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Movie Information</h2>
            
            <div class="poster-preview">
                <img src="https://via.placeholder.com/200x300?text=Poster+Preview" 
                     alt="Poster Preview" id="posterPreview">
                <div class="preview-label">Poster Preview</div>
            </div>
            
            <form method="POST" id="movieForm" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-film"></i> Movie Title <span class="required">*</span></label>
                        <input type="text" name="title" id="title" required 
                               placeholder="e.g., Zootopia 2"
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                        <small>Enter the full movie title</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Genre <span class="required">*</span></label>
                        <select name="genre" id="genre" required>
                            <option value="">-- Select Genre --</option>
                            <?php foreach ($genres as $g): ?>
                                <option value="<?php echo $g; ?>" <?php echo (isset($_POST['genre']) && $_POST['genre'] == $g) ? 'selected' : ''; ?>>
                                    <?php echo $g; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Select the main genre of the movie</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-hourglass-half"></i> Duration (minutes) <span class="required">*</span></label>
                        <input type="number" name="duration" id="duration" required 
                               min="1" max="300" step="1"
                               placeholder="e.g., 108"
                               value="<?php echo isset($_POST['duration']) ? (int)$_POST['duration'] : ''; ?>">
                        <small>Movie runtime in minutes</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Rating</label>
                        <input type="text" name="rating" id="rating" 
                               placeholder="e.g., 7.7"
                               value="<?php echo isset($_POST['rating']) ? htmlspecialchars($_POST['rating']) : ''; ?>">
                        <small>Optional rating (number or text)</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Release Date</label>
                        <input type="text" name="release_date" id="release_date"
                               placeholder="YYYY-MM-DD (e.g., 2025-11-26)"
                               value="<?php echo isset($_POST['release_date']) ? htmlspecialchars($_POST['release_date']) : ''; ?>">
                        <small>Use YYYY-MM-DD format</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-ticket-alt"></i> Price (RM) <span class="required">*</span></label>
                        <input type="number" name="price" id="price" required 
                               min="0" step="0.01"
                               placeholder="e.g., 15.00"
                               value="<?php echo isset($_POST['price']) ? (float)$_POST['price'] : ''; ?>">
                        <small>Ticket price in Malaysian Ringgit</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Status <span class="required">*</span></label>
                        <select name="status" id="status">
                            <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : ''; ?>>
                                📝 Draft (Hidden from public)
                            </option>
                            <option value="coming_soon" <?php echo (isset($_POST['status']) && $_POST['status'] == 'coming_soon') ? 'selected' : ''; ?>>
                                ⏰ Coming Soon (Visible, no booking)
                            </option>
                            <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : 'selected'; ?>>
                                ✅ Published (Fully available)
                            </option>
                        </select>
                        <small>Select the visibility status of the movie</small>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-image"></i> Poster URL <span class="required">*</span></label>
                        <input type="url" name="poster" id="posterUrl" required
                               placeholder="https://image.tmdb.org/t/p/w500/xxxxxx.jpg"
                               value="<?php echo isset($_POST['poster']) ? htmlspecialchars($_POST['poster']) : ''; ?>"
                               onchange="updatePosterPreview()">
                        <small>Enter a valid image URL for the movie poster</small>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" id="description" 
                                  placeholder="Enter a detailed description of the movie..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <small>Movie synopsis and details (optional)</small>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="fas fa-undo"></i> Clear Form
                    </button>
                    <button type="submit" name="add_movie" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Movie
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function updatePosterPreview() {
            const posterUrl = document.getElementById('posterUrl').value;
            const previewImg = document.getElementById('posterPreview');
            
            if (posterUrl) {
                previewImg.classList.add('loading');
                const testImg = new Image();
                testImg.onload = function() {
                    previewImg.src = posterUrl;
                    previewImg.classList.remove('loading');
                };
                testImg.onerror = function() {
                    previewImg.src = 'https://via.placeholder.com/200x300?text=Invalid+Image+URL';
                    previewImg.classList.remove('loading');
                };
                testImg.src = posterUrl;
            } else {
                previewImg.src = 'https://via.placeholder.com/200x300?text=Poster+Preview';
                previewImg.classList.remove('loading');
            }
        }
        
        function resetForm() {
            if (confirm('Are you sure you want to clear all form fields?')) {
                document.getElementById('movieForm').reset();
                document.getElementById('posterPreview').src = 'https://via.placeholder.com/200x300?text=Poster+Preview';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const existingUrl = document.getElementById('posterUrl').value;
            if (existingUrl) {
                updatePosterPreview();
            }
        });
    </script>
</body>
</html>