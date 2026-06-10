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

// Debug: Log the session at the start
error_log("=== PAYMENT.PHP START ===");
error_log("Session data: " . print_r($_SESSION, true));

// ========== STAFF DISCOUNT INTEGRATION ==========
// Get discount info from session (store it in variables BEFORE any potential clearing)
$discount_percent = 0;
$discount_amount = 0;
$discount_code = '';
$discount_code_id = 0;

if (isset($_SESSION['staff_discount'])) {
    $discount_percent = $_SESSION['staff_discount']['percent'] ?? 0;
    $discount_code = $_SESSION['staff_discount']['code'] ?? '';
    $discount_code_id = $_SESSION['staff_discount']['code_id'] ?? 0;
    
    error_log("DISCOUNT FOUND - Code: {$discount_code}, Percent: {$discount_percent}, ID: {$discount_code_id}");
} else {
    error_log("NO DISCOUNT found in session");
}
// ========== END STAFF DISCOUNT INTEGRATION ==========

// Get booking IDs from URL
$booking_ids_param = isset($_GET['booking_ids']) ? $_GET['booking_ids'] : '';
$booking_ids = explode(',', $booking_ids_param);

if (empty($booking_ids) || $booking_ids[0] == '') {
    die("Booking not found");
}

// Fetch booking details
$placeholders = implode(',', array_fill(0, count($booking_ids), '?'));
$booking_sql = "SELECT b.*, m.title, m.poster, m.duration, m.rating
                FROM bookings b 
                JOIN movies m ON b.movie_id = m.movie_id 
                WHERE b.booking_id IN ($placeholders) AND b.user_id = ?";

$booking_stmt = $conn->prepare($booking_sql);
$types = str_repeat('i', count($booking_ids)) . 'i';
$params = array_merge($booking_ids, [$user_id]);
$booking_stmt->bind_param($types, ...$params);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

$bookings = [];
$total_price = 0;
$all_seats = [];

while ($booking = $booking_result->fetch_assoc()) {
    $bookings[] = $booking;
    $total_price += $booking['total_price'];
    $all_seats[] = $booking['seat_number'];
}

if (empty($bookings)) {
    die("Booking not found");
}

$booking = $bookings[0];
$booking['seat_number'] = implode(', ', $all_seats);

// Apply discount to total
$original_total = $total_price;
if ($discount_percent > 0) {
    $discount_amount = $total_price * ($discount_percent / 100);
    $total_price = $total_price - $discount_amount;
}
$booking['total_price'] = $total_price;

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'];
    
    error_log("=== PROCESSING PAYMENT ===");
    error_log("Payment method: {$payment_method}");
    error_log("Discount code to mark: {$discount_code}");
    error_log("Discount percent: {$discount_percent}");
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update bookings to confirmed
        $update_sql = "UPDATE bookings SET status = 'confirmed', payment_method = ?, payment_date = NOW() WHERE booking_id IN ($placeholders)";
        $update_stmt = $conn->prepare($update_sql);
        $update_types = 's' . str_repeat('i', count($booking_ids));
        $update_params = array_merge([$payment_method], $booking_ids);
        $update_stmt->bind_param($update_types, ...$update_params);
        $update_stmt->execute();
        $update_stmt->close();
        
        // ========== MARK DISCOUNT CODE AS USED ==========
        // DO THIS BEFORE clearing the session
        if ($discount_percent > 0 && !empty($discount_code)) {
            error_log("=== ATTEMPTING TO MARK DISCOUNT AS USED ===");
            
            // Update the discount code to used
            $update_discount_sql = "UPDATE discount_codes 
                                   SET status = 'used', used_at = NOW(), used_by = ? 
                                   WHERE code = ? AND status = 'active'";
            $discount_stmt = $conn->prepare($update_discount_sql);
            $discount_stmt->bind_param("is", $user_id, $discount_code);
            
            if ($discount_stmt->execute()) {
                $rows_affected = $discount_stmt->affected_rows;
                error_log("DISCOUNT UPDATE - Rows affected: " . $rows_affected);
                
                if ($rows_affected > 0) {
                    error_log("✓ SUCCESS: Discount code {$discount_code} marked as used");
                } else {
                    error_log("✗ WARNING: No rows updated. Code may not exist or already used");
                    
                    // Double check if code exists
                    $check = $conn->query("SELECT status FROM discount_codes WHERE code = '{$discount_code}'");
                    if ($check_row = $check->fetch_assoc()) {
                        error_log("Current status in DB: " . $check_row['status']);
                    } else {
                        error_log("Code {$discount_code} not found in database!");
                    }
                }
            } else {
                error_log("✗ SQL Error: " . $discount_stmt->error);
            }
            $discount_stmt->close();
        } else {
            error_log("No discount to apply - discount_percent: {$discount_percent}, discount_code: {$discount_code}");
        }
        // ========== END DISCOUNT CODE MARKING ==========
        
        // NOW clear discount from session (AFTER the update)
        unset($_SESSION['staff_discount']);
        unset($_SESSION['discount_applied_payment']);
        unset($_SESSION['discount_applied_booking']);
        
        // Commit transaction
        $conn->commit();
        
        error_log("=== PAYMENT COMPLETED SUCCESSFULLY ===");
        
        // Store success message in session
        $_SESSION['payment_success'] = true;
        
        // Redirect to ticket page
        header("Location: ticket.php?booking_ids=" . implode(',', $booking_ids));
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Payment failed: " . $e->getMessage();
        error_log("Payment error: " . $e->getMessage());
    }
}

// Format duration
$hours = floor($booking['duration'] / 60);
$minutes = $booking['duration'] % 60;
$formatted_duration = $hours . 'h ' . $minutes . 'm';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment - ARVR Cinema</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="base.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="homepage.css">
    
    <style>
        .payment-container { display: flex; gap: 40px; padding: 40px; max-width: 1200px; margin: 0 auto; flex-wrap: wrap; }
        .payment-summary { flex: 1; min-width: 300px; background: #1a0a12; border-radius: 20px; padding: 25px; position: sticky; top: 20px; height: fit-content; }
        .payment-summary h2 { color: #ff4d6d; margin-bottom: 20px; font-size: 24px; }
        .movie-preview { display: flex; gap: 15px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .movie-preview img { width: 80px; height: 120px; border-radius: 10px; object-fit: cover; }
        .movie-preview h3 { font-size: 16px; margin-bottom: 5px; }
        .booking-details { margin: 20px 0; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .detail-row .label { opacity: 0.7; }
        .detail-row .value { font-weight: bold; color: #ffd700; }
        .discount-row { background: rgba(40, 167, 69, 0.2); padding: 10px; border-radius: 8px; margin: 10px 0; }
        .discount-row .value { color: #28a745; }
        .total-amount { margin-top: 20px; padding-top: 15px; border-top: 2px solid #ff4d6d; font-size: 20px; font-weight: bold; }
        .payment-form-card { flex: 1.5; min-width: 350px; background: #1a0a12; border-radius: 20px; padding: 25px; }
        .payment-form-card h2 { color: #ff4d6d; margin-bottom: 25px; font-size: 24px; }
        .payment-methods { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .method { flex: 1; padding: 15px; background: #2b0a14; border: 2px solid transparent; border-radius: 12px; cursor: pointer; text-align: center; transition: 0.3s; }
        .method.selected { border-color: #ff4d6d; background: #3a0a1a; }
        .method i { font-size: 28px; margin-bottom: 8px; display: block; }
        .mock-payment-info { background: rgba(255, 77, 109, 0.1); border: 1px solid #ff4d6d; border-radius: 12px; padding: 20px; margin-bottom: 25px; }
        .mock-payment-info h3 { color: #ff4d6d; font-size: 16px; margin-bottom: 12px; }
        .pay-btn { width: 100%; padding: 15px; background: linear-gradient(45deg, #800020, #ff4d6d); border: none; border-radius: 10px; color: white; font-size: 18px; font-weight: bold; cursor: pointer; margin-top: 20px; transition: 0.3s; }
        .pay-btn:hover { transform: scale(1.02); opacity: 0.9; }
        .back-link { display: inline-block; margin: 20px 40px; color: #ff4d6d; text-decoration: none; }
        .error-message { background: rgba(244, 67, 54, 0.2); border: 1px solid #f44336; padding: 10px; border-radius: 8px; margin-bottom: 15px; color: #f44336; font-size: 14px; }
        .loader { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,.3); border-radius: 50%; border-top-color: white; animation: spin 0.8s linear infinite; margin-right: 8px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .debug-info { background: #333; padding: 10px; margin: 10px; border-radius: 5px; font-family: monospace; font-size: 12px; display: none; }
    </style>
</head>
<body>

<a href="booking.php?movie_id=<?php echo $booking['movie_id']; ?>" class="back-link">
    <i class="fas fa-arrow-left"></i> Back to Booking
</a>

<div class="payment-container">
    <div class="payment-summary">
        <h2><i class="fas fa-receipt"></i> Payment Summary</h2>
        
        <div class="movie-preview">
            <img src="<?php echo htmlspecialchars($booking['poster']); ?>">
            <div>
                <h3><?php echo htmlspecialchars($booking['title']); ?></h3>
                <p>⭐ <?php echo $booking['rating'] ?? 'N/A'; ?> | <?php echo $formatted_duration; ?></p>
            </div>
        </div>
        
        <div class="booking-details">
            <div class="detail-row">
                <span class="label">Showtime</span>
                <span class="value"><?php echo htmlspecialchars($booking['show_time']); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Seat(s)</span>
                <span class="value"><?php echo htmlspecialchars($booking['seat_number']); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Number of Seats</span>
                <span class="value"><?php echo count($all_seats); ?> tickets</span>
            </div>
            
            <?php if ($discount_percent > 0): ?>
                <div class="detail-row">
                    <span class="label">Original Price</span>
                    <span class="value">RM <?php echo number_format($original_total, 2); ?></span>
                </div>
                <div class="discount-row">
                    <span class="label"><i class="fas fa-tag"></i> Staff Discount (<?php echo $discount_percent; ?>%)</span>
                    <span class="value">- RM <?php echo number_format($discount_amount, 2); ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="total-amount">
            <span class="label">Total to Pay</span>
            <span class="value">RM <?php echo number_format($booking['total_price'], 2); ?></span>
        </div>
        
        <?php if ($discount_percent > 0): ?>
            <div class="mock-payment-info" style="background: rgba(40, 167, 69, 0.1); margin-top: 15px;">
                <h3><i class="fas fa-check-circle"></i> Discount Applied!</h3>
                <p>Code: <strong><?php echo htmlspecialchars($discount_code); ?></strong></p>
                <p>You saved RM <?php echo number_format($discount_amount, 2); ?> today!</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="payment-form-card">
        <h2><i class="fas fa-credit-card"></i> Mock Payment</h2>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="paymentForm">
            <div class="payment-methods">
                <div class="method selected" data-method="credit_card">
                    <i class="fas fa-credit-card"></i>
                    <span>Credit Card</span>
                </div>
                <div class="method" data-method="debit_card">
                    <i class="fas fa-bank"></i>
                    <span>Debit Card</span>
                </div>
                <div class="method" data-method="online_banking">
                    <i class="fas fa-university"></i>
                    <span>Online Banking</span>
                </div>
                <div class="method" data-method="ewallet">
                    <i class="fas fa-mobile-alt"></i>
                    <span>E-Wallet</span>
                </div>
            </div>
            
            <input type="hidden" name="payment_method" id="paymentMethod" value="credit_card">
            
            <div class="card-details" style="opacity: 0.7;">
                <div class="form-group">
                    <label>Demo Card Number</label>
                    <input type="text" placeholder="4242 4242 4242 4242" value="4242 4242 4242 4242" readonly disabled>
                </div>
                <div class="form-group">
                    <label>Demo Cardholder Name</label>
                    <input type="text" placeholder="John Doe" value="TEST USER" readonly disabled>
                </div>
                <div style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Expiry Date</label>
                        <input type="text" placeholder="MM/YY" value="12/25" readonly disabled>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>CVV</label>
                        <input type="text" placeholder="123" value="123" readonly disabled>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="pay-btn" id="payBtn">
                <i class="fas fa-ticket-alt"></i> Complete Payment (RM <?php echo number_format($booking['total_price'], 2); ?>)
            </button>
        </form>
    </div>
</div>

<script>
    const methods = document.querySelectorAll('.method');
    const paymentMethodInput = document.getElementById('paymentMethod');
    
    methods.forEach(method => {
        method.addEventListener('click', () => {
            methods.forEach(m => m.classList.remove('selected'));
            method.classList.add('selected');
            paymentMethodInput.value = method.getAttribute('data-method');
        });
    });
    
    const form = document.getElementById('paymentForm');
    const payBtn = document.getElementById('payBtn');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const originalText = payBtn.innerHTML;
        payBtn.innerHTML = '<span class="loader"></span> Processing Payment...';
        payBtn.disabled = true;
        setTimeout(() => { form.submit(); }, 1000);
    });
</script>

</body>
</html>