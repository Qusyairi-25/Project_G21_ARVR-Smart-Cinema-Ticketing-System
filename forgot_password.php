<?php
session_start();
include 'db.php';

$error = '';
$success = '';
$step = 'request'; // request, reset, complete
$email = '';
$token = '';

// STEP 1: Handle reset request (user submits email to get reset link)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_reset'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if email exists
    $check = $conn->query("SELECT * FROM users WHERE email = '$email'");
    
    if ($check->num_rows == 0) {
        $error = "Email address not found in our system.";
    } else {
        // Generate new token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Delete old tokens for this email
        $conn->query("DELETE FROM password_resets WHERE email = '$email'");
        
        // Insert new token
        $insert = "INSERT INTO password_resets (email, token, expires_at) VALUES ('$email', '$token', '$expires')";
        
        if ($conn->query($insert)) {
            // Build reset link
            $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $link = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/forgot_password.php?token=" . $token;
            
            $success = "✅ Reset link created and saved to database!<br><br>";
            $success .= "<div style='background:#f0f0f0;padding:15px;border-radius:5px;word-break:break-all;'>";
            $success .= "<strong>Click this link to reset your password:</strong><br>";
            $success .= "<a href='$link' target='_blank'>$link</a>";
            $success .= "</div><br>";
            $success .= "<small>Token saved in database. Valid until: $expires</small>";
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

// STEP 2: Check URL token (user clicked reset link)
// IMPORTANT: Check for token in GET parameter
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token_from_url = mysqli_real_escape_string($conn, $_GET['token']);
    
    // Look for token in database
    $sql = "SELECT * FROM password_resets WHERE token = '$token_from_url'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $reset = $result->fetch_assoc();
        $current_time = date('Y-m-d H:i:s');
        
        if ($reset['expires_at'] > $current_time) {
            // Valid token - show reset form
            $step = 'reset';
            $email = $reset['email'];
            $token = $token_from_url; // Store token for the form
        } else {
            $error = "❌ This reset link has expired. Please request a new one.";
            $conn->query("DELETE FROM password_resets WHERE token = '$token_from_url'");
        }
    } else {
        $error = "❌ Invalid reset link. Token not found in database. Please request a new one.";
    }
}

// STEP 3: Handle password update (user submits new password)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $token = mysqli_real_escape_string($conn, $_POST['token']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    
    if ($password != $confirm) {
        $error = "Passwords do not match!";
        $step = 'reset';
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
        $step = 'reset';
    } else {
        // CRITICAL FIX: Check database directly without using NOW() which might have timezone issues
        $verify = $conn->query("SELECT * FROM password_resets WHERE email = '$email' AND token = '$token'");
        
        if ($verify && $verify->num_rows > 0) {
            $reset_data = $verify->fetch_assoc();
            $expires_at = $reset_data['expires_at'];
            $current_time = date('Y-m-d H:i:s');
            
            // Manual expiration check
            if ($expires_at > $current_time) {
                // Update password
                if ($conn->query("UPDATE users SET password = '$password' WHERE email = '$email'")) {
                    // Delete used token
                    $conn->query("DELETE FROM password_resets WHERE email = '$email'");
                    $success = "✅ Password successfully updated! You can now login with your new password.";
                    $step = 'complete';
                } else {
                    $error = "Failed to update password. Please try again.";
                    $step = 'reset';
                }
            } else {
                $error = "❌ Reset token has expired. Please request a new reset link.";
                $conn->query("DELETE FROM password_resets WHERE email = '$email'");
                $step = 'request';
            }
        } else {
            $error = "❌ Invalid reset token. Token: " . substr($token, 0, 20) . "... Please request a new reset link.";
            $step = 'request';
        }
    }
}

// Debug: Show active tokens
$debug_tokens = $conn->query("SELECT email, LEFT(token, 20) as token_preview, expires_at FROM password_resets WHERE expires_at > NOW()");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - ARVR Movie</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { width: 500px; }
        .error-box {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            text-align: center;
        }
        .success-box {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            text-align: center;
        }
        .debug-box {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 10px;
            margin-top: 20px;
            border-radius: 5px;
            font-size: 12px;
            color: #856404;
        }
        button {
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="left">
        <div class="overlay">
            <div class="brand">
                <img src="ARVR MOVIE LOGO.png" alt="Logo" class="logo">
                <div class="brand-text">
                    <h1 class="site-name">ARVR Movie</h1>
                    <p class="tagline">Reset your password</p>
                </div>
            </div>
            <div class="hero-text">
                <h1>Need to Reset?</h1>
                <p>We'll help you create a new password</p>
            </div>
        </div>
    </div>

    <div class="right">
        <div class="container">
            
            <?php if ($step == 'complete'): ?>
                <!-- COMPLETE -->
                <h2>✅ Password Reset Complete!</h2>
                <div class="success-box">
                    <?php echo $success; ?>
                </div>
                <button onclick="location.href='login.php'">Go to Login</button>
                
            <?php elseif ($step == 'reset'): ?>
                <!-- RESET FORM -->
                <h2>Create New Password</h2>
                <p>Resetting password for: <strong><?php echo htmlspecialchars($email); ?></strong></p>
                
                <?php if ($error): ?>
                    <div class="error-box"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="input-group">
                        <input type="password" name="password" id="password" required>
                        <label>New Password (min 6 characters)</label>
                    </div>
                    
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirm_password" required>
                        <label>Confirm New Password</label>
                    </div>
                    
                    <button type="submit" name="update_password">Update Password</button>
                </form>
                
                <div class="footer">
                    <p><a href="forgot_password.php">Request new reset link</a></p>
                </div>
                
            <?php else: ?>
                <!-- REQUEST FORM -->
                <h2>Forgot Password?</h2>
                <p>Enter your email address to reset your password.</p>
                
                <?php if ($error): ?>
                    <div class="error-box"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-box"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="input-group">
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">
                        <label>Email Address</label>
                    </div>
                    <button type="submit" name="request_reset">Send Reset Link</button>
                    
                    <div class="footer">
                        <p><a href="login.php">Back to Login</a></p>
                    </div>
                </form>
                
            <?php endif; ?>
            
            <!-- DEBUG SECTION - Shows active tokens in database -->
            <?php if ($debug_tokens && $debug_tokens->num_rows > 0): ?>
            <div class="debug-box">
                <strong>🔍 Active Reset Tokens in Database:</strong><br>
                <table style="width:100%; margin-top:5px; font-size:11px;">
                    <tr>
                        <th>Email</th>
                        <th>Token (first 20 chars)</th>
                        <th>Expires At</th>
                    </tr>
                    <?php while($row = $debug_tokens->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['token_preview']); ?>...</td>
                        <td><?php echo htmlspecialchars($row['expires_at']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>

            </div>
            <?php else: ?>

            <?php endif; ?>
            
        </div>
    </div>
</div>

</body>
</html>