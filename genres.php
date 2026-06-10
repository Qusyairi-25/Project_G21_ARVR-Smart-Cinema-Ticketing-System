<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include 'db.php';

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Define the specific genres you want
$genres = [
    'Action' => 'fa-fist-raised',
    'Horror' => 'fa-ghost',
    'Comedy' => 'fa-laugh-squint',
    'Romance' => 'fa-heart',
    'Sci-Fi' => 'fa-rocket',
    'Fantasy' => 'fa-dragon',
    'Thriller' => 'fa-skull'
];

// Handle search
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Genres - ARVR Cinema</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- External CSS files -->
    <link rel="stylesheet" href="base.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="homepage.css">
    
    <!-- Page-specific styles -->
    <style>
        /* ================= GENRE SECTION ================= */
        .genre-section {
            margin-bottom: 50px;
        }

        .genre-title {
            font-size: 24px;
            margin-bottom: 15px;
            color: #ff4d6d;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ================= MOVIE ROW WRAPPER ================= */
        .movie-row-wrapper {
            position: relative;
        }

        /* ================= MOVIE ROW ================= */
        .movie-row {
            display: flex;
            gap: 18px;
            overflow-x: auto;
            padding: 10px 40px;
            scroll-behavior: smooth;
        }

        .movie-row::-webkit-scrollbar {
            display: none;
        }

        /* ================= CARD ================= */
        .card {
            min-width: 180px;
            height: 260px;
            border-radius: 15px;
            background-size: cover;
            background-position: center;
            flex-shrink: 0;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: 0.4s ease;
        }

        .card:hover {
            transform: scale(1.12);
            z-index: 10;
            box-shadow: 0 20px 40px rgba(255, 77, 109, 0.4);
        }

        .card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            opacity: 0;
            transition: 0.3s;
        }

        .card:hover::before {
            opacity: 1;
        }

        .card::after {
            content: "Book Now";
            position: absolute;
            bottom: 10px;
            width: 100%;
            text-align: center;
            opacity: 0;
            transition: 0.3s;
            font-size: 14px;
            color: white;
            font-weight: bold;
        }

        .card:hover::after {
            opacity: 1;
        }

        /* ================= SCROLL BUTTONS ================= */
        .scroll-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.6);
            border: none;
            color: white;
            font-size: 30px;
            cursor: pointer;
            padding: 10px;
            z-index: 10;
        }

        .scroll-btn.left { left: 0; }
        .scroll-btn.right { right: 0; }

        /* ================= SEARCH SECTION ================= */
        .search-container {
            display: flex;
            gap: 10px;
        }

        .search-container input {
            width: 300px;
            padding: 10px 15px;
            border-radius: 20px;
            border: none;
            background: #2b0a14;
            color: white;
        }

        .search-container input:focus {
            outline: none;
            border: 1px solid #ff4d6d;
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
            margin-left: 10px;
            display: flex;
            align-items: center;
        }

        .clear-search:hover {
            text-decoration: underline;
        }

        .search-results {
            margin: 20px 0;
        }

        .search-results h3 {
            margin-bottom: 20px;
            color: #ff4d6d;
        }

        .search-row {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            padding: 10px 0;
        }

        .no-movies {
            text-align: center;
            padding: 50px;
            color: #999;
        }

        .empty-genre {
            text-align: center;
            padding: 30px;
            color: #999;
            font-style: italic;
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
            .card {
                min-width: 140px;
                height: 210px;
            }
            
            .search-container input {
                width: 200px;
            }
            
            .genre-title {
                font-size: 20px;
            }
            
            .topbar {
                flex-direction: column;
                gap: 15px;
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

        <!-- TOPBAR -->
        <div class="topbar">
            <form action="genres.php" method="GET" style="margin: 0;">
                <div class="search-container">
                    <input type="text" name="search" placeholder="Search movies..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                    <?php if (!empty($search_query)): ?>
                        <a href="genres.php" class="clear-search"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </div>
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

        <?php if (!empty($search_query)): ?>
            <!-- SEARCH RESULTS -->
            <div class="search-results">
                <h3><i class="fas fa-search"></i> Search Results for: "<?php echo htmlspecialchars($search_query); ?>"</h3>
                <?php
                $search_sql = "SELECT * FROM movies WHERE (title LIKE ? OR genre LIKE ?)";
                $search_stmt = $conn->prepare($search_sql);
                $search_param = "%$search_query%";
                $search_stmt->bind_param("ss", $search_param, $search_param);
                $search_stmt->execute();
                $search_result = $search_stmt->get_result();
                
                if ($search_result->num_rows > 0):
                ?>
                    <div class="search-row">
                        <?php while ($movie = $search_result->fetch_assoc()): ?>
                            <div class="card" style="background-image: url('<?php echo htmlspecialchars($movie['poster']); ?>');"
                                 onclick="location.href='movie-details.php?id=<?php echo $movie['movie_id']; ?>'"
                                 title="<?php echo htmlspecialchars($movie['title']); ?>">
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-movies">
                        <i class="fas fa-film"></i>
                        <p>No movies found matching "<?php echo htmlspecialchars($search_query); ?>"</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- GENRE SECTIONS WITH SCROLL BUTTONS -->
            <?php foreach ($genres as $genre => $icon):
                $movie_sql = "SELECT * FROM movies WHERE LOWER(genre) = LOWER(?) ORDER BY rating DESC";
                $movie_stmt = $conn->prepare($movie_sql);
                $movie_stmt->bind_param("s", $genre);
                $movie_stmt->execute();
                $movie_result = $movie_stmt->get_result();
            ?>
                <div class="genre-section">
                    <h2 class="genre-title">
                        <i class="fas <?php echo $icon; ?>"></i> 
                        <?php echo htmlspecialchars($genre); ?>
                    </h2>
                    
                    <?php if ($movie_result->num_rows > 0): ?>
                        <div class="movie-row-wrapper">
                            <button class="scroll-btn left">&#10094;</button>
                            <div class="movie-row">
                                <?php while ($movie = $movie_result->fetch_assoc()): ?>
                                    <div class="card" style="background-image: url('<?php echo htmlspecialchars($movie['poster']); ?>');"
                                         onclick="location.href='movie-details.php?id=<?php echo $movie['movie_id']; ?>'"
                                         title="<?php echo htmlspecialchars($movie['title']); ?> - RM <?php echo number_format($movie['price'], 2); ?>">
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <button class="scroll-btn right">&#10095;</button>
                        </div>
                    <?php else: ?>
                        <div class="empty-genre">
                            <i class="fas fa-film"></i> No movies available in <?php echo htmlspecialchars($genre); ?> yet.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Scroll buttons for each movie row (same as homepage)
    document.querySelectorAll(".movie-row-wrapper").forEach(wrapper => {
        const row = wrapper.querySelector(".movie-row");
        const leftBtn = wrapper.querySelector(".left");
        const rightBtn = wrapper.querySelector(".right");
        
        if (rightBtn) {
            rightBtn.addEventListener("click", () => {
                row.scrollBy({ left: 300, behavior: "smooth" });
            });
        }
        
        if (leftBtn) {
            leftBtn.addEventListener("click", () => {
                row.scrollBy({ left: -300, behavior: "smooth" });
            });
        }
    });
</script>

</body>
</html>