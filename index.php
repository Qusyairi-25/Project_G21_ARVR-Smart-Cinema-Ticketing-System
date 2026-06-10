<?php
// Start session to check if user is already logged in
session_start();

// If already logged in, redirect to appropriate homepage
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff') {
        header("Location: staff_homepage.php");
    } else {
        header("Location: homepage.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
    
<head>
    <meta charset="UTF-8">
    <title>ARVR Movie - Your Cinematic Experience</title>
    
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">

    <link rel="stylesheet" href="style.css">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">

    <!-- Page-specific styles -->
    <style>
        .container {
            width: 450px;
            animation: fadeIn 0.8s ease;
            text-align: center;
        }

        .welcome-title {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .welcome-subtitle {
            color: #aaa;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        .choice-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 30px 0;
        }

        .choice-btn {
            flex: 1;
            padding: 14px 20px;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-login {
            background: #f5b5b5;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-login:hover {
            background: #555;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .btn-register {
            background: transparent;
            color: #ffa8a8;
            border: 2px solid #f5b5b5;
        }

        .btn-register:hover {
            background: rgba(51, 51, 51, 0.1);
            transform: translateY(-3px);
        }

        .info-text {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #666;
            font-size: 0.8rem;
        }

        .brand-center {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand-center .logo {
            width: 80px;
            margin-bottom: 15px;
        }

        .brand-center .site-name {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .brand-center .tagline {
            color: #aaa;
            font-size: 0.85rem;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .container {
                width: 90%;
                margin: 0 20px;
            }
            
            .choice-buttons {
                flex-direction: column;
                gap: 15px;
            }
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
                <h1>Ready for a Movie?</h1>
                <p>Login or Register to start your cinematic journey</p>
            </div>

        </div>
    </div>

    <!-- RIGHT SIDE -->
    <div class="right">

        <div class="container">

            <div class="brand-center">
                <img src="ARVR MOVIE LOGO.png" alt="Logo" class="logo" style="width: 60px;">
                <h1 class="site-name" style="font-size: 1.5rem;">ARVR Movie</h1>
                <p class="tagline">Your cinematic ticket experience</p>
            </div>

            <h2 class="welcome-title">Welcome!</h2>
            <p class="welcome-subtitle">Please login or create an account to continue</p>

            <div class="choice-buttons">
                <a href="login.php" class="choice-btn btn-login">🔐 Login</a>
                <a href="register.php" class="choice-btn btn-register">📝 Register</a>
            </div>

            <div class="info-text">
                <p>Access to movie bookings, showtimes, and exclusive offers</p>
            </div>

        </div>

    </div>

</div>

</body>
</html>