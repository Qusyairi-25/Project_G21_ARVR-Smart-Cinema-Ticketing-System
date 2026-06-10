<?php
session_start();

// Show cancellation message if exists
if (isset($_SESSION['cancel_message'])) {
    echo '<div style="background: rgba(76, 175, 80, 0.2); border: 1px solid #4CAF50; border-radius: 10px; padding: 12px 20px; margin-bottom: 20px; color: #4CAF50;">
            <i class="fas fa-check-circle"></i> ' . $_SESSION['cancel_message'] . '
          </div>';
    unset($_SESSION['cancel_message']);
}

if (isset($_SESSION['cancel_error'])) {
    echo '<div style="background: rgba(244, 67, 54, 0.2); border: 1px solid #f44336; border-radius: 10px; padding: 12px 20px; margin-bottom: 20px; color: #f44336;">
            <i class="fas fa-exclamation-triangle"></i> ' . $_SESSION['cancel_error'] . '
          </div>';
    unset($_SESSION['cancel_error']);
}

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

// Fetch all confirmed bookings for this user
$tickets_sql = "SELECT b.*, m.title, m.poster, m.duration, m.rating 
                FROM bookings b 
                JOIN movies m ON b.movie_id = m.movie_id 
                WHERE b.user_id = ? AND b.status = 'confirmed'
                ORDER BY b.booking_date DESC";
$tickets_stmt = $conn->prepare($tickets_sql);
$tickets_stmt->bind_param("i", $user_id);
$tickets_stmt->execute();
$tickets_result = $tickets_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Tickets - ARVR Cinema</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="base.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="homepage.css">

    <style>
        .tickets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .ticket-card {
            background: linear-gradient(135deg, #2b0a14, #1a0007);
            border-radius: 15px;
            overflow: hidden;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .ticket-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255,77,109,0.3);
        }
        
        .ticket-header {
            padding: 15px;
            background: rgba(0,0,0,0.3);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .ticket-header h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .ticket-body {
            padding: 15px;
        }
        
        .ticket-body p {
            margin: 8px 0;
            font-size: 13px;
        }
        
        .ticket-body p strong {
            color: #ffd700;
        }
        
        .empty-tickets {
            text-align: center;
            padding: 80px;
            background: rgba(255,255,255,0.03);
            border-radius: 20px;
            margin-top: 50px;
        }
        
        .empty-tickets i {
            font-size: 64px;
            color: #ff4d6d;
            opacity: 0.5;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 28px;
            margin-bottom: 10px;
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
            .tickets-grid {
                grid-template-columns: 1fr;
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
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' || basename($_SERVER['PHP_SELF']) == 'my_tickets.php' ? 'active' : ''; ?>">
                <a href="ticket.php"><i class="fas fa-ticket-alt"></i> My Tickets</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            </li>
        </ul>
    </div>

    <div class="main">
        <!-- TOP BAR -->
        <div class="topbar">
            <div></div>
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
                    <img src="profileIcon.jpg" alt="Profile" onerror="this.src='https://via.placeholder.com/35'">
                </button>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <h1 class="section-title"><i class="fas fa-ticket-alt"></i> My Tickets</h1>
        <p>Here are all your confirmed bookings</p>

        <?php if ($tickets_result->num_rows > 0): ?>
            <div class="tickets-grid">
                <?php while ($ticket = $tickets_result->fetch_assoc()): 
                    $hours = floor($ticket['duration'] / 60);
                    $minutes = $ticket['duration'] % 60;
                    $formatted_duration = $hours . 'h ' . $minutes . 'm';
                ?>
                    <div class="ticket-card" onclick="location.href='payment_success.php?booking_id=<?php echo $ticket['booking_id']; ?>'">
                        <div class="ticket-header">
                            <h3><?php echo htmlspecialchars($ticket['title']); ?></h3>
                            <span style="font-size: 11px; opacity: 0.7;"><?php echo date('F j, Y', strtotime($ticket['booking_date'])); ?></span>
                        </div>
                        <div class="ticket-body">
                            <p><strong>Showtime:</strong> <?php echo htmlspecialchars($ticket['show_time']); ?></p>
                            <p><strong>Seats:</strong> <?php echo htmlspecialchars($ticket['seat_number']); ?></p>
                            <p><strong>Total:</strong> RM <?php echo number_format($ticket['total_price'], 2); ?></p>
                            <p><strong>Booking ID:</strong> #ARVR<?php echo str_pad($ticket['booking_id'], 5, '0', STR_PAD_LEFT); ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-tickets">
                <i class="fas fa-ticket-alt"></i>
                <h3>No Tickets Yet</h3>
                <p>You haven't booked any tickets yet.</p>
                <button onclick="location.href='homepage.php'" style="margin-top: 15px; padding: 10px 25px; background: #ff4d6d; border: none; border-radius: 8px; color: white; cursor: pointer;">Browse Movies</button>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>