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

// Get booking ID from URL
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

// Fetch confirmed booking details
$booking_sql = "SELECT b.*, m.title, m.poster, m.duration, m.rating, m.genre 
                FROM bookings b 
                JOIN movies m ON b.movie_id = m.movie_id 
                WHERE b.booking_id = ? AND b.user_id = ? AND b.status = 'confirmed'";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();
$booking = $booking_result->fetch_assoc();

if (!$booking) {
    // If no confirmed booking found, redirect to my tickets page
    header("Location: ticket.php");
    exit();
}

// Format duration
$hours = floor($booking['duration'] / 60);
$minutes = $booking['duration'] % 60;
$formatted_duration = $hours . 'h ' . $minutes . 'm';

// Generate a dummy hall number based on movie_id
$hall_number = 'Hall ' . (($booking['movie_id'] % 5) + 1);

// Format booking ID with prefix
$formatted_booking_id = '#ARVR' . str_pad($booking_id, 5, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Ticket - ARVR Cinema</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">   
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- External CSS files -->
    <link rel="stylesheet" href="base.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="homepage.css">

    <style>
        /* ================= TICKET CONTAINER ================= */
        .ticket-container {
            display: flex;
            justify-content: center;
            padding: 20px;
        }

        /* ================= TICKET CARD ================= */
        .ticket {
            width: 900px;
            background: linear-gradient(135deg, #2b0a14, #1a0007);
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
            transition: transform 0.3s;
        }

        .ticket:hover {
            transform: scale(1.02);
        }

        /* LEFT SIDE */
        .ticket-left {
            flex: 2;
            padding: 30px;
        }

        /* RIGHT SIDE */
        .ticket-right {
            flex: 1;
            background: rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-left: 2px dashed #ff4d6d;
        }

        /* ===== MOVIE INFO ===== */
        .movie-title {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .meta {
            margin: 10px 0;
            opacity: 0.8;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        /* ===== DETAILS ===== */
        .details {
            margin-top: 20px;
        }

        .details p {
            margin: 12px 0;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed rgba(255,255,255,0.05);
            padding-bottom: 8px;
        }

        .details p strong {
            color: #ffd700;
        }

        /* ===== PRICE ===== */
        .price {
            margin-top: 20px;
            padding-top: 15px;
            font-size: 22px;
            font-weight: bold;
            color: #ff4d6d;
            border-top: 2px solid #ff4d6d;
        }

        /* ===== QR CODE (SIMULATED) ===== */
        .qr-simulated {
            width: 140px;
            height: 140px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .qr-simulated i {
            font-size: 80px;
            color: #000;
        }

        /* ===== BUTTONS ===== */
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: 0.3s;
        }

        .btn.cancel {
            background: #444;
            color: white;
        }

        .btn.cancel:hover {
            background: #f44336;
        }

        .btn.receipt {
            background: linear-gradient(45deg, #800020, #ff4d6d);
            color: white;
        }

        .btn.receipt:hover {
            transform: scale(1.05);
        }

        .btn.download {
            background: transparent;
            border: 1px solid #ff4d6d;
            color: #ff4d6d;
        }

        .btn.download:hover {
            background: #ff4d6d;
            color: white;
        }
        
        .btn.back {
            background: transparent;
            border: 1px solid #2196F3;
            color: #2196F3;
        }
        
        .btn.back:hover {
            background: #2196F3;
            color: white;
        }

        /* ===== SUCCESS MESSAGE ===== */
        .success-banner {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4CAF50;
            border-radius: 10px;
            padding: 12px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4CAF50;
        }

        /* ===== MODAL STYLES ===== */
        .modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #2b0a14;
            padding: 30px;
            border-radius: 15px;
            z-index: 1000;
            min-width: 300px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        
        .modal.active {
            display: block;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 999;
        }
        
        .modal-overlay.active {
            display: block;
        }
        
        .modal h3 {
            margin-bottom: 15px;
        }
        
        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .modal-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .modal-btn-no {
            background: #444;
            color: white;
        }
        
        .modal-btn-yes {
            background: #f44336;
            color: white;
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
            .ticket {
                flex-direction: column;
                width: 100%;
            }
            
            .ticket-right {
                padding: 20px;
                border-left: none;
                border-top: 2px dashed #ff4d6d;
            }
            
            .movie-title {
                font-size: 24px;
            }
            
            .details p {
                flex-direction: column;
            }
            
            .details p strong {
                margin-bottom: 5px;
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
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' || basename($_SERVER['PHP_SELF']) == 'payment_success.php' ? 'active' : ''; ?>">
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
                    <img src="profileIcon.jpg" alt="Profile">
                </button>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="ticket-container">
            <div class="ticket">
                <!-- LEFT SIDE -->
                <div class="ticket-left">
                    <div class="success-banner">
                        <i class="fas fa-check-circle"></i>
                        <span>Payment Successful! Your ticket is confirmed.</span>
                    </div>

                    <h1 class="movie-title"><?php echo htmlspecialchars($booking['title']); ?></h1>

                    <div class="meta">
                        ⭐ <?php echo $booking['rating'] ? $booking['rating'] : 'N/A'; ?> | 
                        ⏱ <?php echo $formatted_duration; ?> | 
                        🎬 <?php echo htmlspecialchars($booking['genre']); ?>
                    </div>

                    <div class="details">
                        <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></p>
                        <p><strong>Time:</strong> <?php echo htmlspecialchars($booking['show_time']); ?></p>
                        <p><strong>Seats:</strong> <?php echo htmlspecialchars($booking['seat_number']); ?></p>
                        <p><strong>Hall:</strong> <?php echo $hall_number; ?></p>
                        <p><strong>Booking ID:</strong> <?php echo $formatted_booking_id; ?></p>
                        <p><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($booking['payment_method']))); ?></p>
                    </div>

                    <div class="price">
                        Total Paid: RM <?php echo number_format($booking['total_price'], 2); ?>
                    </div>

                    <!-- ACTIONS -->
                    <div class="actions">

                        <button class="btn cancel" onclick="cancelTicket(<?php echo $booking_id; ?>)">
                            <i class="fas fa-times"></i> Cancel Ticket
                        </button>
                        <button class="btn receipt" onclick="printTicket()">
                            <i class="fas fa-print"></i> Print Ticket
                        </button>
                        <button class="btn download" onclick="downloadTicket()">
                            <i class="fas fa-download"></i> Save as PDF
                        </button>
                                                <button class="btn back" onclick="location.href='ticket.php'">
                            <i class="fas fa-arrow-left"></i> Back to My Tickets
                        </button>
                    </div>
                </div>

                <!-- RIGHT SIDE -->
                <div class="ticket-right">
                    <div class="qr-simulated">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <p style="margin-top: 10px; font-size: 12px;">Scan at entrance</p>
                    <p style="font-size: 10px; opacity: 0.6; margin-top: 5px;"><?php echo $formatted_booking_id; ?></p>
                </div>
            </div>
        </div>

        <!-- Cancel Confirmation Modal -->
        <div id="cancelModal" class="modal">
            <h3><i class="fas fa-exclamation-triangle"></i> Cancel Ticket?</h3>
            <p style="margin: 15px 0;">Are you sure you want to cancel this ticket?</p>
            <p style="color: #f44336; font-size: 14px;"><i class="fas fa-ban"></i> This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-no" onclick="closeModal()">No, Go Back</button>
                <button class="modal-btn modal-btn-yes" id="confirmCancelBtn">Yes, Cancel Ticket</button>
            </div>
        </div>
        <div id="modalOverlay" class="modal-overlay"></div>
    </div>
</div>

<script>
    let currentBookingId = <?php echo $booking_id; ?>;
    
    function printTicket() {
        window.print();
    }
    
    function downloadTicket() {
        alert("Ticket saved as PDF (Demo Mode)\nIn a real app, this would generate a PDF file.");
    }
    
    function cancelTicket(bookingId) {
        // Show modal
        document.getElementById('cancelModal').classList.add('active');
        document.getElementById('modalOverlay').classList.add('active');
        
        // Set the confirm button action
        document.getElementById('confirmCancelBtn').onclick = function() {
            window.location.href = 'cancel_booking.php?booking_id=' + bookingId;
        };
    }
    
    function closeModal() {
        document.getElementById('cancelModal').classList.remove('active');
        document.getElementById('modalOverlay').classList.remove('active');
    }
    
    // Close modal when clicking overlay
    document.getElementById('modalOverlay').addEventListener('click', closeModal);
</script>

<style>
    @media print {
        .sidebar, .topbar, .actions, .success-banner, .profile-btn, .modal, .modal-overlay {
            display: none !important;
        }
        .ticket {
            box-shadow: none;
            margin: 0;
            padding: 0;
        }
        .main {
            padding: 0;
        }
        .ticket-container {
            padding: 0;
        }
    }
</style>

</body>
</html>