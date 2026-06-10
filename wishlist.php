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
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Handle remove from wishlist
if (isset($_POST['remove'])) {
    $movie_id = (int)$_POST['movie_id'];
    $delete_sql = "DELETE FROM wishlist WHERE user_id = ? AND movie_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $user_id, $movie_id);
    $delete_stmt->execute();
}

// Fetch user's wishlist movies (without added_date)
$sql = "SELECT m.* 
        FROM wishlist w 
        JOIN movies m ON w.movie_id = m.movie_id 
        WHERE w.user_id = ?";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Handle search if present
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search_query)) {
    $search_sql = "SELECT m.* 
                   FROM wishlist w 
                   JOIN movies m ON w.movie_id = m.movie_id 
                   WHERE w.user_id = ? AND (m.title LIKE ? OR m.genre LIKE ?)";
    $search_stmt = $conn->prepare($search_sql);
    $search_param = "%$search_query%";
    $search_stmt->bind_param("iss", $user_id, $search_param, $search_param);
    $search_stmt->execute();
    $result = $search_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Wishlist - ARVR Cinema</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- External CSS files -->
    <link rel="stylesheet" href="base.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="homepage.css">
    
    <!-- Page-specific styles (only what's not in external files) -->
    <style>
        /* Wishlist specific styles */
        .wishlist-title {
            margin: 20px 0;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .wishlist-title i {
            color: #ff4d6d;
        }
        
        .wishlist-stats {
            margin-bottom: 20px;
            padding: 10px 0;
            color: rgba(255,255,255,0.7);
            font-size: 14px;
        }
        
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .wishlist-card {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .wishlist-card:hover {
            transform: translateY(-5px);
        }
        
        .wishlist-card img {
            width: 100%;
            height: 260px;
            object-fit: cover;
            border-radius: 15px;
        }
        
        .wishlist-card-info {
            padding: 10px;
            text-align: center;
        }
        
        .wishlist-card-info h3 {
            font-size: 14px;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .wishlist-card-info p {
            font-size: 12px;
            color: #ff4d6d;
        }
        
        .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            border: none;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
            z-index: 10;
        }
        
        .remove-btn:hover {
            background: #ff4d6d;
            transform: scale(1.1);
        }
        
        .empty-wishlist {
            text-align: center;
            padding: 80px 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
            margin-top: 50px;
        }
        
        .empty-wishlist i {
            font-size: 64px;
            color: #ff4d6d;
            margin-bottom: 20px;
        }
        
        .empty-wishlist h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .empty-wishlist p {
            color: rgba(255,255,255,0.7);
            margin-bottom: 20px;
        }
        
        .browse-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(45deg, #800020, #ff4d6d);
            color: white;
            cursor: pointer;
            font-size: 14px;
            transition: 0.3s;
        }
        
        .browse-btn:hover {
            transform: scale(1.05);
        }
        
        .search-container {
            display: flex;
            gap: 10px;
        }
        
        .search-container input {
            flex: 1;
            padding: 10px 15px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
            background: #2b0a14;
            color: white;
        }
        
        .search-container input:focus {
            outline: none;
            border-color: #ff4d6d;
        }
        
        .search-container button {
            padding: 10px 20px;
            border-radius: 20px;
            border: none;
            background: #ff4d6d;
            color: white;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .search-container button:hover {
            background: #800020;
        }
        
        .clear-search {
            color: #ff4d6d;
            text-decoration: none;
            font-size: 14px;
        }
        
        .clear-search:hover {
            text-decoration: underline;
        }
        
        /* Confirm dialog styling */
        .confirm-dialog {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #2b0a14;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            z-index: 1000;
            text-align: center;
            min-width: 300px;
        }
        
        .confirm-dialog.active {
            display: block;
        }
        
        .confirm-dialog h3 {
            margin-bottom: 10px;
        }
        
        .confirm-dialog p {
            margin-bottom: 20px;
            color: rgba(255,255,255,0.8);
        }
        
        .confirm-dialog button {
            padding: 8px 20px;
            margin: 0 5px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .confirm-yes {
            background: #ff4d6d;
            color: white;
        }
        
        .confirm-no {
            background: #333;
            color: white;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 999;
        }
        
        .overlay.active {
            display: block;
        }
        
        .role-badge {
            background: #ff4d6d;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
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
        
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .user-welcome {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .topbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .wishlist-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }
            
            .wishlist-title {
                font-size: 24px;
            }
            
            .user-welcome {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="dashboard">

    <!-- SIDEBAR - Updated with icons and proper active state -->
    <div class="sidebar">
        <h2 class="logo-text">ARVR</h2>
        <ul class="menu">
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'homepage.php' ? 'active' : ''; ?>">
                <a href="homepage.php"><i class="fas fa-home"></i> Home</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'wishlist.php' ? 'active' : ''; ?>">
                <a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'genres.php' ? 'active' : ''; ?>">
                <a href="genres.php"><i class="fas fa-tags"></i> Genres</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'coming-soon.php' ? 'active' : ''; ?>">
                <a href="coming-soon.php"><i class="fas fa-clock"></i> Coming Soon</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'badges.php' ? 'active' : ''; ?>">
                <a href="badges.php"><i class="fas fa-medal"></i> Badges</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' ? 'active' : ''; ?>">
                <a href="ticket.php"><i class="fas fa-ticket-alt"></i> My Tickets</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            </li>
        </ul>
    </div>

    <!-- MAIN -->
    <div class="main">

        <!-- TOP BAR -->
        <div class="topbar">
            <form class="search-container" action="wishlist.php" method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 400px;">
                <input type="text" name="search" placeholder="Search wishlist by title or genre..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
                <?php if (!empty($search_query)): ?>
                    <a href="wishlist.php" class="clear-search">Clear</a>
                <?php endif; ?>
            </form>
            
            <div class="user-welcome">
                <span style="color: #ffffff;">
                    Welcome, <?php echo htmlspecialchars($username); ?>!
                    <?php if ($role == 'staff'): ?>
                        <span class="role-badge">Staff Member</span>
                    <?php elseif ($role == 'admin'): ?>
                        <span class="role-badge" style="background: #ffd700; color: #333;">Administrator</span>
                    <?php else: ?>
                        <span class="role-badge">Member</span>
                    <?php endif; ?>
                </span>
                
                <button class="profile-btn" onclick="location.href='profile.php'">
                    <img src="profileIcon.jpg" alt="Profile">
                </button>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="wishlist-title">
            <i class="fas fa-heart"></i> My Wishlist
        </div>
        
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="wishlist-stats">
                <i class="fas fa-film"></i> <?php echo $result->num_rows; ?> movie<?php echo $result->num_rows > 1 ? 's' : ''; ?> in your wishlist
            </div>
            
            <div class="wishlist-grid">
                <?php while ($movie = $result->fetch_assoc()): ?>
                    <div class="wishlist-card" data-movie-id="<?php echo $movie['movie_id']; ?>">
                        <button class="remove-btn" onclick="confirmRemove(<?php echo $movie['movie_id']; ?>, '<?php echo addslashes($movie['title']); ?>')">
                            <i class="fas fa-times"></i>
                        </button>
                        <img src="<?php echo htmlspecialchars($movie['poster']); ?>" 
                             alt="<?php echo htmlspecialchars($movie['title']); ?>"
                             onclick="location.href='movie-details.php?id=<?php echo $movie['movie_id']; ?>'"
                             onerror="this.src='https://via.placeholder.com/180x260?text=No+Poster'">
                        <div class="wishlist-card-info" onclick="location.href='movie-details.php?id=<?php echo $movie['movie_id']; ?>'">
                            <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                            <p>RM <?php echo number_format($movie['price'], 2); ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-wishlist">
                <i class="fas fa-heart-broken"></i>
                <h3>Your wishlist is empty</h3>
                <p>Start adding movies you want to watch!</p>
                <button class="browse-btn" onclick="location.href='homepage.php'">
                    <i class="fas fa-film"></i> Browse Movies
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Confirm Remove Dialog -->
<div class="overlay" id="overlay"></div>
<div class="confirm-dialog" id="confirmDialog">
    <h3>Remove from Wishlist?</h3>
    <p id="removeMovieTitle"></p>
    <form method="POST" action="wishlist.php" id="removeForm">
        <input type="hidden" name="movie_id" id="removeMovieId">
        <button type="button" class="confirm-no" onclick="hideConfirm()">Cancel</button>
        <button type="submit" name="remove" class="confirm-yes">Remove</button>
    </form>
</div>

<script>
    function confirmRemove(movieId, movieTitle) {
        document.getElementById('removeMovieId').value = movieId;
        document.getElementById('removeMovieTitle').innerHTML = `"${movieTitle}"`;
        document.getElementById('overlay').classList.add('active');
        document.getElementById('confirmDialog').classList.add('active');
    }
    
    function hideConfirm() {
        document.getElementById('overlay').classList.remove('active');
        document.getElementById('confirmDialog').classList.remove('active');
    }
    
    // Close dialog when clicking overlay
    document.getElementById('overlay').addEventListener('click', hideConfirm);
</script>

</body>
</html>