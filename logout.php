<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging Out...</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #0a0a0a;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: white;
        }
        .logout-container {
            text-align: center;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255,77,109,0.3);
            border-top-color: #ff4d6d;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <meta http-equiv="refresh" content="2; url=login.php">
</head>
<body>
    <div class="logout-container">
        <div class="spinner"></div>
        <h2>Logging out...</h2>
        <p>See you next time!</p>
    </div>
    <?php
    // Clear session after showing the message
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    session_destroy();
    ?>
</body>
</html>