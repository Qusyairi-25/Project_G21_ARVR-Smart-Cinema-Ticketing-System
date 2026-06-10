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

// Fetch coming soon and upcoming movies
$sql = "SELECT * FROM movies WHERE status = 'coming_soon' OR status = 'upcoming' ORDER BY release_date ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coming Soon - ARVR Cinema</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- External CSS files -->
    <link rel="stylesheet" href="base.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="homepage.css">
    
    <!-- Page-specific styles -->
    <style>
        /* ================= HERO SECTION ================= */
        .coming-soon-hero {
            height: 300px;
            border-radius: 20px;
            background: linear-gradient(135deg, #1a0a10, #2b0a14), url('https://images.unsplash.com/photo-1536440136628-849c177e76a1') center/cover;
            background-blend-mode: overlay;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .coming-soon-hero::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,77,109,0.1), transparent);
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero-content h1 {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            margin-bottom: 15px;
            background: linear-gradient(45deg, #ff4d6d, #ff9a9e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-content p {
            font-size: 18px;
            opacity: 0.8;
        }

        
        
        /* ================= COUNTDOWN BANNER ================= */
        .countdown-banner {
            background: linear-gradient(90deg, #800020, #ff4d6d);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .countdown-banner span {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .countdown-banner h3 {
            font-size: 24px;
            margin-top: 5px;
        }
        
        /* ================= MOVIE GRID ================= */
        .coming-soon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        /* ================= MOVIE CARD ================= */
        .soon-card {
            background: #1a0a12;
            border-radius: 16px;
            overflow: hidden;
            transition: 0.4s ease;
            cursor: pointer;
            position: relative;
        }
        
        .soon-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(255, 77, 109, 0.3);
        }
        
        .soon-poster {
            position: relative;
            height: 380px;
            background-size: cover;
            background-position: center;
            overflow: hidden;
        }
        
        .soon-poster::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9), rgba(0,0,0,0.3), transparent);
            opacity: 0.6;
            transition: 0.3s;
        }
        
        .soon-card:hover .soon-poster::before {
            opacity: 0.8;
        }
        
        /* Coming Soon Badge */
        .coming-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #ff4d6d;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            z-index: 2;
            animation: blink 1.5s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        /* Release Date */
        .release-date {
            position: absolute;
            bottom: 15px;
            left: 15px;
            background: rgba(0,0,0,0.7);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Quick View Button */
        .quick-view-btn {
            position: absolute;
            bottom: 50%;
            left: 50%;
            transform: translate(-50%, 50%);
            background: #ff4d6d;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: bold;
            opacity: 0;
            transition: 0.3s;
            z-index: 2;
            white-space: nowrap;
        }
        
        .soon-card:hover .quick-view-btn {
            opacity: 1;
            bottom: 40%;
        }
        
        /* Movie Info */
        .soon-info {
            padding: 15px;
            text-align: center;
        }
        
        .soon-info h3 {
            font-size: 16px;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .soon-info .genre {
            font-size: 12px;
            color: #ff4d6d;
            margin-bottom: 8px;
        }
        
        .soon-info .expectation {
            font-size: 11px;
            color: #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .expectation i {
            font-size: 10px;
        }
        
        /* ================= EMPTY STATE ================= */
        .empty-coming-soon {
            text-align: center;
            padding: 80px 20px;
            background: rgba(255,255,255,0.03);
            border-radius: 20px;
            margin-top: 50px;
        }
        
        .empty-coming-soon i {
            font-size: 80px;
            color: #ff4d6d;
            opacity: 0.5;
            margin-bottom: 20px;
        }
        
        .empty-coming-soon h3 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .empty-coming-soon p {
            color: rgba(255,255,255,0.6);
        }
        
        .role-badge {
            background: #ff4d6d;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        /* ================= RESPONSIVE ================= */
        @media (max-width: 768px) {
            .coming-soon-hero {
                height: 200px;
            }
            
            .hero-content h1 {
                font-size: 32px;
            }
            
            .coming-soon-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 20px;
            }
            
            .soon-poster {
                height: 320px;
            }
            
            .countdown-banner h3 {
                font-size: 18px;
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
            <form action="coming-soon.php" method="GET" style="margin: 0;">
                <div class="search-container">

                </div>
            </form>
            
            <div class="user-info" style="display: flex; align-items: center; gap: 15px;">
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
                <a href="logout.php" class="logout-btn" style="color: white; text-decoration: none; padding: 8px 15px; background: #800020; border-radius: 5px; transition: 0.3s;">Logout</a>
            </div>
        </div>

        <!-- HERO SECTION -->
        <div class="coming-soon-hero">
            <div class="hero-content">
                <h1><i class="fas fa-calendar-star"></i> Coming Soon</h1>
                <p>Get ready for the most anticipated movies</p>
            </div>
        </div>

        <!-- COUNTDOWN BANNER -->
        <div class="countdown-banner">
            <span><i class="fas fa-clock"></i> NEXT BIG RELEASE</span>
            <h3 id="countdown">Loading...</h3>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="coming-soon-grid">
                <?php while ($movie = $result->fetch_assoc()): 
                    $release_date = strtotime($movie['release_date']);
                ?>
                    <div class="soon-card" onclick="location.href='movie-details.php?id=<?php echo $movie['movie_id']; ?>'">
                        <div class="soon-poster" style="background-image: url('<?php echo htmlspecialchars($movie['poster']); ?>');">
                            <div class="coming-badge">
                                <i class="fas fa-hourglass-half"></i> Coming Soon
                            </div>
                            <div class="release-date">
                                <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', $release_date); ?>
                            </div>
                            <div class="quick-view-btn">
                                <i class="fas fa-eye"></i> Quick View
                            </div>
                        </div>
                        <div class="soon-info">
                            <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                            <div class="genre"><?php echo htmlspecialchars($movie['genre']); ?></div>
                            <div class="expectation">
                                <span><i class="fas fa-star"></i> Highly Anticipated</span>
                                <span><i class="fas fa-clock"></i> <?php echo $movie['duration']; ?> min</span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-coming-soon">
                <i class="fas fa-film"></i>
                <h3>No Upcoming Movies</h3>
                <p>Check back later for new movie announcements!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Countdown to next release
    <?php
    $next_movie_sql = "SELECT release_date FROM movies WHERE status = 'coming_soon' OR status = 'upcoming' ORDER BY release_date ASC LIMIT 1";
    $next_result = $conn->query($next_movie_sql);
    if ($next_result && $next_result->num_rows > 0) {
        $next_movie = $next_result->fetch_assoc();
        $next_release = strtotime($next_movie['release_date']);
    } else {
        $next_release = strtotime('+30 days');
    }
    ?>
    
    function updateCountdown() {
        const targetDate = new Date(<?php echo $next_release * 1000; ?>);
        const now = new Date();
        const diff = targetDate - now;
        
        if (diff <= 0) {
            document.getElementById('countdown').innerHTML = "Available Now!";
            return;
        }
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (86400000)) / (3600000));
        const minutes = Math.floor((diff % 3600000) / 60000);
        
        document.getElementById('countdown').innerHTML = `${days}d ${hours}h ${minutes}m remaining`;
    }
    
    updateCountdown();
    setInterval(updateCountdown, 60000);
</script>

</body>
</html>