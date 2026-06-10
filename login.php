<?php
// Start session to store user login state
session_start();

// Process the form when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db.php';
    
    // Get input values
    $identity = mysqli_real_escape_string($conn, $_POST['login_identity']);
    $password = $_POST['password'];
    
    $error = "";
    
    // Query to find user by email or username AND password (plain text comparison)
    $sql = "SELECT * FROM users WHERE (email = '$identity' OR username = '$identity') AND password = '$password'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Login successful - Set ALL session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];  // <-- THIS LINE WAS MISSING!
        
        // Redirect based on role
        if ($user['role'] == 'admin' || $user['role'] == 'staff') {
            header("Location: staff_homepage.php");
        } else {
            header("Location: homepage.php");
        }
        exit();
    } else {
        $error = "Invalid username/email or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    
<head>
    <meta charset="UTF-8">
    <title>Login Page - ARVR Movie</title>
    
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">

    <link rel="stylesheet" href="style.css">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">

    <!-- Page-specific tweak -->
    <style>
        .container {
            width: 380px;
            animation: fadeIn 0.8s ease;
        }

        button {
            margin-top: 10px;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            text-align: center;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            text-align: center;
        }
    </style>
</head>

<body>
    
<div class="wrapper">

    <!-- LEFT SIDE -->
    <div class="left">
        <div class="overlay">

            <div class="brand">
                <img src="ARVR MOVIE LOGO.png" alt="Logo" class="logo">

                <div class="brand-text">
                    <h1 class="site-name">ARVR Movie</h1>
                    <p class="tagline">Your cinematic ticket experience</p>
                </div>
            </div>

            <div class="hero-text">
                <h1>Welcome Back</h1>
                <p>Step into your cinematic experience</p>
            </div>

        </div>
    </div>

    <!-- RIGHT SIDE -->
    <div class="right">

        <div class="container">

            <h2>Login</h2>
            
            <?php if (isset($error) && $error != ""): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success'): ?>
                <div class="success-message">
                    Registration successful! Please login.
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">

                <div class="input-group">
                    <input type="text" name="login_identity" id="login_identity" required value="<?php echo isset($_POST['login_identity']) ? htmlspecialchars($_POST['login_identity']) : ''; ?>">
                    <label>Username / Email</label>
                </div>

                <div class="input-group">
                    <input type="password" name="password" required>
                    <label>Password</label>
                </div>

                <div class="form-options">
                    <a href="forgot_password.php" class="forgot">Forgot Password?</a>
                </div>

                <button type="submit">Login</button>

                <div class="footer">
                    <p>Don't have an account? <a href="register.php">Register</a></p>
                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>