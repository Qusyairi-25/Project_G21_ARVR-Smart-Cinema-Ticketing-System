<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_discount'])) {
    $code = trim($_POST['discount_code']);
    $user_id = $_SESSION['user_id'];
    $movie_id = isset($_POST['movie_id']) ? (int)$_POST['movie_id'] : 0;
    
    // Check if code is valid - MAKE SURE TO SELECT code_id
    $check_sql = "SELECT code_id, code, discount_percent FROM discount_codes 
                  WHERE code = ? AND status = 'active' 
                  AND (generated_for = ? OR generated_for IS NULL)
                  AND (expiry_date IS NULL OR expiry_date >= CURDATE())";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("si", $code, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $discount = $result->fetch_assoc();
        
        // Store discount in session with ALL data including code_id
        $_SESSION['staff_discount'] = [
            'code' => $discount['code'],
            'percent' => $discount['discount_percent'],
            'code_id' => $discount['code_id']  // THIS IS CRITICAL!
        ];
        
        $_SESSION['discount_message'] = "Discount code applied! You get {$discount['discount_percent']}% off!";
    } else {
        $_SESSION['discount_error'] = "Invalid or expired discount code.";
    }
    $stmt->close();
}

// Redirect back
if ($movie_id > 0) {
    header("Location: booking.php?movie_id=" . $movie_id);
} else {
    header("Location: " . $_SERVER['HTTP_REFERER']);
}
exit();
?>