<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    header('Location: homepage.php');
    exit();
}

include 'db.php';

$username = $_SESSION['username'];
$role = $_SESSION['role'];

$success_message = '';
$error_message = '';

// Cancel booking
if (isset($_POST['cancel_booking'])) {
    $booking_id = (int)$_POST['booking_id'];

    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ? AND status != 'cancelled'");
    $stmt->bind_param('i', $booking_id);

    if ($stmt->execute()) {
        $success_message = 'Booking cancelled successfully!';
    } else {
        $error_message = 'Failed to cancel booking.';
    }
}

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT b.*, u.username, m.title 
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN movies m ON b.movie_id = m.movie_id
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (u.username LIKE ? OR m.title LIKE ? OR b.seat_number LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $sql .= " AND b.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$sql .= " ORDER BY b.booking_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_bookings,
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
              FROM bookings";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Management - ARVR Cinema</title>
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

        .booking-section {
            padding: 20px 40px;
        }

        .filter-container {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .filter-box {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            font-size: 12px;
            opacity: 0.7;
            display: block;
            margin-bottom: 5px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.5);
            color: white;
            font-family: 'Poppins', sans-serif;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #ff4d6d;
        }

        .search-btn {
            background: #ff4d6d;
            color: white;
            padding: 10px 25px;
        }

        .search-btn:hover {
            background: #ff6b8a;
            transform: translateY(-2px);
        }

        .reset-btn {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .reset-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 15px;
            background: rgba(255,255,255,0.05);
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        th {
            background: rgba(255,255,255,0.05);
            font-weight: 600;
            color: #ff4d6d;
        }

        tr:hover {
            background: rgba(255,255,255,0.03);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.confirmed {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }

        .status-badge.pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .status-badge.cancelled {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }

        .cancel-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: 0.3s;
        }

        .cancel-btn:hover {
            background: #c82333;
            transform: scale(1.05);
        }

        .no-data {
            text-align: center;
            padding: 50px;
            opacity: 0.7;
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

        @media (max-width: 768px) {
            .staff-header {
                padding: 15px 20px;
            }

            .stats-container,
            .booking-section {
                padding: 15px 20px;
            }

            .filter-box {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            th, td {
                padding: 10px 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="staff-header">
        <div class="logo">
            <h1>🎟️ Booking Management</h1>
            <p>View and manage all customer bookings</p>
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
            <i class="fas fa-ticket-alt"></i>
            <div class="number"><?php echo $stats['total_bookings']; ?></div>
            <div>Total Bookings</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-check-circle"></i>
            <div class="number"><?php echo $stats['confirmed']; ?></div>
            <div>Confirmed</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-clock"></i>
            <div class="number"><?php echo $stats['pending']; ?></div>
            <div>Pending</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-ban"></i>
            <div class="number"><?php echo $stats['cancelled']; ?></div>
            <div>Cancelled</div>
        </div>
    </div>

    <!-- Messages -->
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

    <!-- Booking Management -->
    <div class="booking-section">
        <div class="filter-container">
            <form method="GET" class="filter-box">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" placeholder="Username, movie, or seat..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="confirmed" <?php if($status_filter=='confirmed') echo 'selected'; ?>>Confirmed</option>
                        <option value="pending" <?php if($status_filter=='pending') echo 'selected'; ?>>Pending</option>
                        <option value="cancelled" <?php if($status_filter=='cancelled') echo 'selected'; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>&nbsp;</label>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn search-btn">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="booking_management.php" class="btn reset-btn">
                            <i class="fas fa-sync-alt"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-wrapper">
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Movie</th>
                            <th>Seat</th>
                            <th>Show Time</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Booking Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['booking_id']; ?></td>
                                <td>
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($row['username']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['seat_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['show_time']); ?></td>
                                <td>RM <?php echo number_format($row['total_price'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($row['booking_date'])); ?></td>
                                <td>
                                    <?php if($row['status'] != 'cancelled'): ?>
                                        <form method="POST" onsubmit="return confirm('Cancel this booking?')">
                                            <input type="hidden" name="booking_id" value="<?php echo $row['booking_id']; ?>">
                                            <button type="submit" name="cancel_booking" class="cancel-btn">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="opacity: 0.5;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 20px;"></i>
                    <h3>No bookings found</h3>
                    <p>Try adjusting your search or filter criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>