<?php
session_start();

// Check if user is logged in and is staff or admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Only staff and admin can access this page
if ($role != 'staff' && $role != 'admin') {
    header("Location: homepage.php");
    exit();
}

include 'db.php';

// Handle discount code generation (Admin only)
$generated_code = null;
$generation_error = null;
$generation_success = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_code']) && $role == 'admin') {
    $staff_id = $_POST['staff_id'] ?? null;
    $discount_percent = $_POST['discount_percent'] ?? 15;
    $expiry_days = $_POST['expiry_days'] ?? 30;
    
    if ($staff_id) {
        // Generate unique code
        $prefix = "STAFF";
        $random = strtoupper(substr(uniqid(), -6));
        $code = $prefix . $random;
        
        $expiry_date = date('Y-m-d', strtotime("+$expiry_days days"));
        
        $insert_sql = "INSERT INTO discount_codes (code, discount_percent, generated_for, generated_by, expiry_date) 
                       VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("siiis", $code, $discount_percent, $staff_id, $user_id, $expiry_date);
        
        if ($stmt->execute()) {
            $generation_success = "Discount code generated successfully! Code: $code";
        } else {
            $generation_error = "Failed to generate code: " . $conn->error;
        }
        $stmt->close();
    } else {
        $generation_error = "Please select a staff member.";
    }
}

// Handle code usage for discount
$used_code = null;
$use_error = null;
$use_success = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['use_discount'])) {
    $code = trim($_POST['discount_code']);
    
    // Check if code exists and is valid
    $check_sql = "SELECT * FROM discount_codes 
                  WHERE code = ? AND status = 'active' 
                  AND (generated_for = ? OR generated_for IS NULL)
                  AND (expiry_date IS NULL OR expiry_date >= CURDATE())";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("si", $code, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $discount = $result->fetch_assoc();
        
        // Store discount in session for checkout
        $_SESSION['staff_discount'] = [
            'code' => $discount['code'],
            'percent' => $discount['discount_percent'],
            'code_id' => $discount['code_id']
        ];
        
        $use_success = "Discount code applied! You get {$discount['discount_percent']}% off on your next purchase.";
    } else {
        $use_error = "Invalid or expired discount code.";
    }
    $stmt->close();
}

// Get staff's available discount codes
$staff_sql = "SELECT * FROM discount_codes 
              WHERE (generated_for = ? OR generated_for IS NULL)
              AND status = 'active'
              AND (expiry_date IS NULL OR expiry_date >= CURDATE())
              ORDER BY generated_at DESC";
$stmt = $conn->prepare($staff_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$staff_codes_result = $stmt->get_result();
$stmt->close();

// Get all staff members for admin (for code generation)
$staff_list = [];
if ($role == 'admin') {
    $staff_sql = "SELECT user_id, username, email FROM users WHERE role = 'staff' ORDER BY username";
    $staff_list_result = $conn->query($staff_sql);
    while ($row = $staff_list_result->fetch_assoc()) {
        $staff_list[] = $row;
    }
}

// Get previously used codes by this staff
$used_codes_sql = "SELECT * FROM discount_codes 
                   WHERE generated_for = ? AND status = 'used'
                   ORDER BY used_at DESC LIMIT 10";
$stmt = $conn->prepare($used_codes_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$used_codes_result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Discount - ARVR Cinema</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="base.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="homepage.css">
    
    <style>
        /* Additional styles for discount page */
        .main {
            margin-left: 0;
            padding: 20px;
            min-height: 100vh;
            background: #0a0a0a;
        }
        
        .topbar {
            background: rgba(15, 15, 26, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border: 1px solid rgba(255,77,109,0.2);
        }
        
        .topbar h1 {
            font-size: 24px;
            background: linear-gradient(45deg, #ff4d6d, #ff8c00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .user-welcome {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-welcome span {
            color: #ff4d6d;
        }
        
        .discount-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .discount-card {
            background: rgba(26, 26, 46, 0.95);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(255,77,109,0.2);
        }
        
        .discount-card h2 {
            color: #ff4d6d;
            margin-bottom: 20px;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .input-group {
            margin: 15px 0;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #ccc;
            font-weight: 500;
        }
        
        .input-group input, 
        .input-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #333;
            border-radius: 8px;
            background: #2a2a3e;
            color: white;
            font-size: 14px;
        }
        
        .input-group input:focus, 
        .input-group select:focus {
            outline: none;
            border-color: #ff4d6d;
        }
        
        .btn {
            background: #ff4d6d;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn:hover {
            background: #e63946;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #4a4a6a;
            text-decoration: none;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a5a7a;
        }
        
        .btn-back {
            background: #2196F3;
            color: white;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background: #1976D2;
        }
        
        .success {
            background: rgba(40, 167, 69, 0.2);
            border-left: 4px solid #28a745;
            color: #28a745;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .error {
            background: rgba(220, 53, 69, 0.2);
            border-left: 4px solid #dc3545;
            color: #dc3545;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .code-list {
            margin-top: 20px;
        }
        
        .code-item {
            background: #2a2a3e;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .code-item .code {
            font-family: monospace;
            font-size: 18px;
            font-weight: bold;
            color: #ff4d6d;
        }
        
        .badge {
            background: #28a745;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            display: inline-block;
        }
        
        .badge-expired {
            background: #dc3545;
        }
        
        .info-box {
            background: rgba(42, 42, 62, 0.5);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 3px solid #ff4d6d;
        }
        
        .info-box ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .info-box li {
            margin: 5px 0;
        }
        
        .copy-btn {
            background: #667eea;
            margin-left: 10px;
            padding: 6px 15px;
            font-size: 13px;
            border: none;
            cursor: pointer;
        }
        
        .back-button-container {
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                padding: 15px;
            }
            
            .discount-card {
                padding: 20px;
            }
            
            .code-item {
                flex-direction: column;
                text-align: center;
            }
            
            .topbar {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .user-welcome {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="dashboard">
    <!-- Main Content -->
    <div class="main">
        
        <div class="topbar">
            <h1>
                <i class="fas fa-tags"></i> 
                Staff Discount Center
            </h1>
            <div class="user-welcome">
                <span>
                    <i class="fas fa-user"></i> 
                    Welcome, <?php echo htmlspecialchars($username); ?>!
                </span>
                <span class="badge" style="background: #ffffff;">
                    <i class="fas fa-shield-alt"></i> 
                    <?php echo strtoupper($role); ?>
                </span>
                <a href="logout.php" class="btn" style="background: #dc3545; padding: 8px 15px;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

                <div class="back-button-container">
            <a href="homepage.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Go Back to Homepage
            </a>
        </div>
        
        <div class="discount-container">
            
            <?php if ($use_success): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i> <?php echo $use_success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($use_error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $use_error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($generation_success): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i> <?php echo $generation_success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($generation_error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $generation_error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Apply Discount Code Section -->
            <div class="discount-card">
                <h2>
                    <i class="fas fa-percent"></i> 
                    Apply Staff Discount
                </h2>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i> 
                    As a staff member, you are eligible for exclusive discount codes. 
                    Enter your code below to get discount on your movie tickets.
                </div>
                
                <form method="POST" action="">
                    <div class="input-group">
                        <label for="discount_code">
                            <i class="fas fa-qrcode"></i> Enter Discount Code:
                        </label>
                        <input type="text" id="discount_code" name="discount_code" 
                               placeholder="e.g., STAFF5F2G8H" required>
                    </div>
                    <button type="submit" name="use_discount" class="btn">
                        <i class="fas fa-gift"></i> Apply Discount
                    </button>
                </form>
            </div>
            
            <!-- Your Available Discount Codes -->
            <?php if ($staff_codes_result && $staff_codes_result->num_rows > 0): ?>
            <div class="discount-card">
                <h2>
                    <i class="fas fa-ticket-alt"></i> 
                    Your Available Discount Codes
                </h2>
                <div class="code-list">
                    <?php while ($code = $staff_codes_result->fetch_assoc()): ?>
                    <div class="code-item">
                        <div>
                            <div class="code"><?php echo htmlspecialchars($code['code']); ?></div>
                            <small>
                                <i class="fas fa-calendar"></i> 
                                Generated: <?php echo date('d M Y', strtotime($code['generated_at'])); ?>
                            </small>
                            <?php if ($code['expiry_date']): ?>
                                <small>
                                    <i class="fas fa-hourglass-end"></i> 
                                    Expires: <?php echo date('d M Y', strtotime($code['expiry_date'])); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="badge">
                                <?php echo $code['discount_percent']; ?>% OFF
                            </span>
                            <button class="btn-secondary copy-btn" onclick="copyCode('<?php echo $code['code']; ?>')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Admin: Generate Discount Code Section -->
            <?php if ($role == 'admin'): ?>
            <div class="discount-card">
                <h2>
                    <i class="fas fa-magic"></i> 
                    Generate Discount Code for Staff
                </h2>
                <form method="POST" action="">
                    <div class="input-group">
                        <label for="staff_id">
                            <i class="fas fa-user"></i> Select Staff Member:
                        </label>
                        <select id="staff_id" name="staff_id" required>
                            <option value="">Select a staff member...</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['user_id']; ?>">
                                    <?php echo htmlspecialchars($staff['username']); ?> 
                                    (<?php echo htmlspecialchars($staff['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label for="discount_percent">
                            <i class="fas fa-percent"></i> Discount Percentage:
                        </label>
                        <input type="number" id="discount_percent" name="discount_percent" 
                               min="5" max="50" value="15" required>
                    </div>
                    
                    <div class="input-group">
                        <label for="expiry_days">
                            <i class="fas fa-calendar-alt"></i> Valid for (days):
                        </label>
                        <input type="number" id="expiry_days" name="expiry_days" 
                               min="1" max="365" value="30" required>
                    </div>
                    
                    <button type="submit" name="generate_code" class="btn">
                        <i class="fas fa-qrcode"></i> Generate Code
                    </button>
                </form>
                
                <div class="info-box" style="margin-top: 20px;">
                    <i class="fas fa-chart-line"></i> <strong>Statistics:</strong>
                    <ul>
                        <li>Total codes generated: <?php 
                            $stats_sql = "SELECT COUNT(*) as total FROM discount_codes";
                            $stats_result = $conn->query($stats_sql);
                            $stats = $stats_result->fetch_assoc();
                            echo $stats['total'];
                        ?></li>
                        <li>Active codes: <?php 
                            $active_sql = "SELECT COUNT(*) as active FROM discount_codes WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE())";
                            $active_result = $conn->query($active_sql);
                            $active = $active_result->fetch_assoc();
                            echo $active['active'];
                        ?></li>
                        <li>Used codes: <?php 
                            $used_sql = "SELECT COUNT(*) as used FROM discount_codes WHERE status = 'used'";
                            $used_result = $conn->query($used_sql);
                            $used = $used_result->fetch_assoc();
                            echo $used['used'];
                        ?></li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Previously Used Codes -->
            <?php if ($used_codes_result && $used_codes_result->num_rows > 0): ?>
            <div class="discount-card">
                <h2>
                    <i class="fas fa-history"></i> 
                    Previously Used Codes
                </h2>
                <div class="code-list">
                    <?php while ($code = $used_codes_result->fetch_assoc()): ?>
                    <div class="code-item">
                        <div>
                            <div class="code" style="color: #888;">
                                <?php echo htmlspecialchars($code['code']); ?>
                            </div>
                            <small>
                                <i class="fas fa-check-circle"></i> 
                                Used on: <?php echo date('d M Y', strtotime($code['used_at'])); ?>
                            </small>
                        </div>
                        <div>
                            <span class="badge badge-expired">
                                USED - <?php echo $code['discount_percent']; ?>% OFF
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Staff Discount Information -->
            <div class="discount-card">
                <h2>
                    <i class="fas fa-info-circle"></i> 
                    Staff Discount Information
                </h2>
                <div class="info-box">
                    <p><strong><i class="fas fa-question-circle"></i> How to get your discount code:</strong></p>
                    <ul>
                        <li>Admins will generate unique discount codes for staff members</li>
                        <li>Each code can only be used once</li>
                        <li>Discount codes typically give 15-20% off on ticket prices</li>
                        <li>Codes are valid for 30 days from generation date</li>
                    </ul>
                    <p style="margin-top: 15px;"><strong><i class="fas fa-rocket"></i> How to use:</strong></p>
                    <ul>
                        <li>Copy your discount code from above</li>
                        <li>Go to movie booking page</li>
                        <li>Enter the code at checkout to apply discount</li>
                        <li>The discount will be applied automatically to your total</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyCode(code) {
    navigator.clipboard.writeText(code).then(function() {
        alert('Discount code copied: ' + code);
    }, function() {
        alert('Failed to copy code. Please copy manually.');
    });
}
</script>

</body>
</html>