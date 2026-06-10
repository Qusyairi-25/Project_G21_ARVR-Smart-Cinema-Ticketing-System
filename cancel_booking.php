<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id > 0) {
    // Update booking status to cancelled
    $cancel_sql = "UPDATE bookings SET status = 'cancelled' WHERE booking_id = ? AND user_id = ?";
    $cancel_stmt = $conn->prepare($cancel_sql);
    $cancel_stmt->bind_param("ii", $booking_id, $user_id);
    
    if ($cancel_stmt->execute()) {
        $_SESSION['cancel_message'] = "Ticket cancelled successfully!";
    } else {
        $_SESSION['cancel_error'] = "Failed to cancel ticket.";
    }
    $cancel_stmt->close();
}

// Redirect back to my tickets page
header("Location: ticket.php");
exit();
?>