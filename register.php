<?php
// Process the form when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db.php';
    
    // Get and sanitize input
    $username = mysqli_real_escape_string($conn, $_POST['Username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Basic validation
    $error = "";
    
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Check if email already exists
        $check_sql = "SELECT * FROM users WHERE email = '$email'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            // Insert new user with PLAIN TEXT password (NO HASHING)
            $sql = "INSERT INTO users (username, email, password) 
                    VALUES ('$username', '$email', '$password')";
            
            if ($conn->query($sql) === TRUE) {
                // Redirect to login page after successful registration
                header("Location: login.php?registered=success");
                exit();
            } else {
                $error = "Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    
<head>
    <meta charset="UTF-8">
    <title>Movie Booking - Register</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <link rel="stylesheet" href="style.css">
    <style>
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
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
                <h1>Join the Front Row</h1>
                <p>Book your perfect cinema experience</p>
            </div>

        </div>
    </div>

    <!-- RIGHT SIDE -->
    <div class="right">

        <div class="container">
            <h2>Create Account</h2>
            
            <?php if (isset($error) && $error != ""): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">

                <div class="input-group">
                    <input type="text" name="Username" required value="<?php echo isset($_POST['Username']) ? htmlspecialchars($_POST['Username']) : ''; ?>">
                    <label>Full Name</label>
                </div>

                <div class="input-group">
                    <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <label>Email</label>
                </div>

                <div class="input-group">
                    <input type="password" name="password" required>
                    <label>Password</label>
                </div>

                <div class="input-group">
                    <input type="password" name="confirm_password" required>
                    <label>Confirm Password</label>
                </div>

                <button type="submit">Register!</button>

            </form>

            <div class="footer">
                Already a member? <a href="login.php">Login</a>
            </div>
        </div>

    </div>

</div>

</body>
</html>