<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Include database connection
include 'db.php';

// On homepage.php - only show published and coming soon movies
$sql = "SELECT * FROM movies WHERE status IN ('published', 'coming_soon') 
        ORDER BY 
        CASE status 
            WHEN 'published' THEN 1 
            WHEN 'coming_soon' THEN 2 
        END, 
        movie_id DESC";
        
// Fetch different categories of movies from database

// 1. Fetch Trending Movies (based on rating - only published movies)
$trending_sql = "SELECT * FROM movies WHERE status = 'published' ORDER BY rating DESC LIMIT 10";
$trending_result = $conn->query($trending_sql);

// 2. Fetch Recommended Movies
include 'get_recommendations.php';
$user_id = $_SESSION['user_id'];
$recommended_movies = getRecommendations($conn, $user_id, 10);

// 3. Fetch Coming Soon Movies (status = 'coming_soon')
$upcoming_sql = "SELECT * FROM movies WHERE status = 'coming_soon' ORDER BY release_date DESC LIMIT 10";
$upcoming_result = $conn->query($upcoming_sql);

// 4. Get movies for carousel (published movies only)
$carousel_sql = "SELECT * FROM movies WHERE status = 'published' ORDER BY rating DESC LIMIT 5";
$carousel_result = $conn->query($carousel_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ARVR Cinema - Home</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <!-- FONTS -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    
    <!-- FONTAWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS FILES -->
    <link rel="stylesheet" href="base.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="homepage.css">
    
    <style>
        .user-welcome {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logout-btn {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: #800020;
            border-radius: 5px;
            transition: 0.3s;
        }
        
        .logout-btn:hover {
            background: #ff4d6d;
        }
        
        .no-movies {
            text-align: center;
            padding: 50px;
            color: #999;
            font-style: italic;
        }
        
        /* Make movie cards clickable with cursor pointer */
        .movie-card, .card {
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .movie-card:hover, .card:hover {
            transform: scale(1.05);
        }
        
        /* Staff Panel Button Styles */
        .staff-panel-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: 0.3s;
            margin-left: 15px;
        }
        
        .staff-panel-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .discount-btn {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: 0.3s;
            margin-left: 10px;
        }
        
        .discount-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(240, 147, 251, 0.4);
        }
        
        .role-badge {
            background: #ff4d6d;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .profile-btn {
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .profile-btn img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .topbar input {
            width: 300px;
            padding: 10px 15px;
            border-radius: 25px;
            border: none;
            background: rgba(255,255,255,0.1);
            color: white;
            font-family: 'Poppins', sans-serif;
        }
        
        .topbar input::placeholder {
            color: rgba(255,255,255,0.5);
        }
        
        /* Coming Soon Badge on Cards */
        .coming-soon-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #2196F3;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            z-index: 10;
        }
        
        .card {
            position: relative;
        }
        
        @media (max-width: 768px) {
            .topbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .topbar input {
                width: 100%;
            }
            
            .user-welcome {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        /* ==================== CHATBOT ASSISTANT STYLES ==================== */
        /* Floating Chat Button */
        .chatbot-float-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .chatbot-float-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }

        .chatbot-float-btn i {
            font-size: 28px;
            color: white;
        }

        /* Chatbot Modal/Window */
        .chatbot-window {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 380px;
            height: 550px;
            background: #1a1a2e;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 1001;
            border: 1px solid rgba(255,255,255,0.1);
            font-family: 'Poppins', sans-serif;
        }

        .chatbot-window.active {
            display: flex;
        }

        /* Chatbot Header */
        .chatbot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .chatbot-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .chatbot-header h3 i {
            margin-right: 8px;
        }

        .chatbot-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.8;
            transition: 0.3s;
        }

        .chatbot-close:hover {
            opacity: 1;
        }

        /* Chat Messages Area */
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #16213e;
        }

        /* Individual Message Bubbles */
        .message {
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.bot {
            background: #2d3561;
            color: #e0e0e0;
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }

        .message.user {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }

        /* Typing Indicator */
        .typing-indicator {
            display: flex;
            gap: 5px;
            padding: 10px 15px;
            background: #2d3561;
            border-radius: 18px;
            align-self: flex-start;
            width: fit-content;
        }

        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: #aaa;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-indicator span:nth-child(1) { animation-delay: 0s; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
            30% { transform: translateY(-10px); opacity: 1; }
        }

        /* Chat Input Area */
        .chat-input-area {
            display: flex;
            padding: 15px;
            background: #1a1a2e;
            border-top: 1px solid rgba(255,255,255,0.1);
            gap: 10px;
        }

        .chat-input-area input {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 25px;
            background: #2d3561;
            color: white;
            font-family: 'Poppins', sans-serif;
            outline: none;
        }

        .chat-input-area input::placeholder {
            color: rgba(255,255,255,0.5);
        }

        .chat-input-area button {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            transition: 0.3s;
        }

        .chat-input-area button:hover {
            transform: scale(1.05);
        }

        /* Mobile Responsive */
        @media (max-width: 500px) {
            .chatbot-window {
                width: calc(100% - 40px);
                right: 20px;
                bottom: 80px;
                height: 500px;
            }
        }
    </style>
</head>
<body>

<div class="dashboard">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <h2 class="logo-text">ARVR</h2>
        <ul class="menu">
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'homepage.php' ? 'active' : ''; ?>">
                <a href="homepage.php"><i class="fas fa-home"></i> Home</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'wishlist.php' ? 'active' : ''; ?>">
                <a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'genres.php' ? 'active' : ''; ?>">
                <a href="genres.php"><i class="fas fa-tags"></i> Genres</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'coming-soon.php' ? 'active' : ''; ?>">
                <a href="coming-soon.php"><i class="fas fa-clock"></i> Coming Soon</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'badges.php' ? 'active' : ''; ?>">
                <a href="badges.php"><i class="fas fa-medal"></i> Badges</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' ? 'active' : ''; ?>">
                <a href="ticket.php"><i class="fas fa-ticket-alt"></i> My Tickets</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            </li>
        </ul>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main">

        <!-- TOP BAR -->
<div class="topbar">
    <div class="user-welcome" style="margin-left: auto; display: flex; align-items: center; gap: 15px;">
        <span style="color: #ffffff;">
            Welcome, <?php echo htmlspecialchars($username); ?>!
            <?php if ($role == 'staff'): ?>
                <span class="role-badge">Staff Member</span>
            <?php elseif ($role == 'admin'): ?>
                <span class="role-badge" style="background: #ffd700; color: #333;">Administrator</span>
            <?php else: ?>
                <span class="role-badge">Member</span>
            <?php endif; ?>
        </span>
        
        <!-- Staff Panel Link (for staff and admin) -->
        <?php if ($role == 'staff' || $role == 'admin'): ?>
            <a href="staff_homepage.php" class="staff-panel-btn">
                <i class="fas fa-shield-alt"></i> Staff Panel
            </a>
        <?php endif; ?>
        
        <!-- Staff Discount Link (for staff and admin) -->
        <?php if ($role == 'staff' || $role == 'admin'): ?>
            <a href="staff_discount.php" class="discount-btn">
                <i class="fas fa-tags"></i> Discount Codes
            </a>
        <?php endif; ?>
        
        <button class="profile-btn" onclick="location.href='profile.php'">
            <img src="profileIcon.jpg" alt="Profile">
        </button>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>

        <!-- HERO -->
        <div class="hero">
            <div class="hero-content">
                <h1>Now Showing</h1>
                <p>Book your favorite movies instantly.</p>
                <button onclick="location.href='genres.php'">Book Now</button>
            </div>
        </div>

        <!-- TRENDING NOW CAROUSEL -->
        <h2 class="section-title">🔥 Trending Now</h2>
        <div class="carousel-wrapper">
            <div class="carousel" id="carousel">
                <?php 
                if ($carousel_result && $carousel_result->num_rows > 0) {
                    while ($movie = $carousel_result->fetch_assoc()) {
                        $poster_url = !empty($movie['poster']) ? $movie['poster'] : 'https://via.placeholder.com/240x360?text=No+Poster';
                ?>
                        <div class="movie-card" onclick="location.href='movie-details.php?id=<?php echo $movie['movie_id']; ?>'">
                            <img src="<?php echo $poster_url; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                            <div class="overlay">
                                <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                                <p>⭐ <?php echo $movie['rating'] ? $movie['rating'] : 'N/A'; ?> | <?php echo $movie['duration']; ?> min</p>
                                <p style="font-size: 11px; margin-top: 5px;">RM <?php echo number_format($movie['price'], 2); ?></p>
                                <button>View Details</button>
                            </div>
                        </div>
                <?php 
                    }
                } else {
                    echo '<div class="no-movies">No trending movies available</div>';
                }
                ?>
            </div>
        </div>

        <!-- RECOMMENDED FOR YOU -->
        <h2 class="section-title">🎬 Recommended for You</h2>
        <div class="movie-row">
            <button class="scroll-btn left">&#10094;</button>
            <div class="movies">
                <?php 
                if (!empty($recommended_movies)) {
                    foreach ($recommended_movies as $movie) {
                        // Only show published movies in recommendations
                        if ($movie['status'] != 'published') continue;
                        $poster_url = !empty($movie['poster']) ? $movie['poster'] : 'https://via.placeholder.com/180x260?text=No+Poster';
                ?>
                        <div class="card" style="background-image: url('<?php echo $poster_url; ?>');" 
                             onclick="location.href='movie-details.php?id=<?php echo $movie['movie_id']; ?>'"
                             title="<?php echo htmlspecialchars($movie['title']); ?> - RM <?php echo number_format($movie['price'], 2); ?>">
                        </div>
                <?php 
                    }
                } else {
                    echo '<div class="no-movies">No recommended movies available</div>';
                }
                ?>
            </div>
            <button class="scroll-btn right">&#10095;</button>
        </div>

        <!-- COMING SOON -->
        <h2 class="section-title">📅 Coming Soon</h2>
        <div class="movie-row">
            <button class="scroll-btn left">&#10094;</button>
            <div class="movies">
                <?php 
                if ($upcoming_result && $upcoming_result->num_rows > 0) {
                    while ($movie = $upcoming_result->fetch_assoc()) {
                        $poster_url = !empty($movie['poster']) ? $movie['poster'] : 'https://via.placeholder.com/180x260?text=No+Poster';
                ?>
                        <div class="card" style="background-image: url('<?php echo $poster_url; ?>'); position: relative;" 
                             onclick="location.href='movie-details.php?id=<?php echo $movie['movie_id']; ?>'"
                             title="<?php echo htmlspecialchars($movie['title']); ?> - Coming Soon">
                            <div class="coming-soon-badge">COMING SOON</div>
                        </div>
                <?php 
                    }
                } else {
                    echo '<div class="no-movies">No upcoming movies available</div>';
                }
                ?>
            </div>
            <button class="scroll-btn right">&#10095;</button>
        </div>

        <script>
        // Scroll buttons for movie rows
        const rows = document.querySelectorAll(".movie-row");
        rows.forEach(row => {
            const container = row.querySelector(".movies");
            const leftBtn = row.querySelector(".left");
            const rightBtn = row.querySelector(".right");
            if (rightBtn) {
                rightBtn.addEventListener("click", () => {
                    container.scrollBy({ left: 300, behavior: "smooth" });
                });
            }
            if (leftBtn) {
                leftBtn.addEventListener("click", () => {
                    container.scrollBy({ left: -300, behavior: "smooth" });
                });
            }
        });

        // Auto carousel for trending
        const carousel = document.getElementById("carousel");
        if (carousel) {
            let cards = document.querySelectorAll(".movie-card");
            if (cards.length > 0) {
                let index = 0;
                
                // Clone cards for infinite scroll
                const clones = [...cards].map(card => card.cloneNode(true));
                clones.forEach(clone => carousel.appendChild(clone));
                cards = document.querySelectorAll(".movie-card");
                const cardWidth = cards[0].offsetWidth + 18;
                
                function moveCarousel() {
                    index++;
                    carousel.style.transition = "transform 0.6s ease";
                    carousel.style.transform = `translateX(${-index * cardWidth}px)`;
                    updateActive();
                    
                    if (index === cards.length / 2) {
                        setTimeout(() => {
                            carousel.style.transition = "none";
                            index = 0;
                            carousel.style.transform = `translateX(0px)`;
                            updateActive();
                        }, 600);
                    }
                }
                
                function updateActive() {
                    cards.forEach((card, i) => {
                        card.classList.remove("active");
                        if (i === index) {
                            card.classList.add("active");
                        }
                    });
                }
                
                setInterval(moveCarousel, 2500);
                updateActive();
            }
        }
        </script>

    </div>
</div>

<!-- ==================== CHATBOT ASSISTANT ==================== -->
<!-- Floating Chat Button -->
<div class="chatbot-float-btn" id="chatbotFloatBtn">
    <i class="fas fa-comment-dots"></i>
</div>

<!-- Chatbot Window -->
<div class="chatbot-window" id="chatbotWindow">
    <div class="chatbot-header">
        <h3><i class="fas fa-robot"></i> ARVR Assistant</h3>
        <button class="chatbot-close" id="chatbotCloseBtn">&times;</button>
    </div>
    <div class="chat-messages" id="chatMessages">
        <div class="message bot">
            👋 Hello! Welcome to ARVR Cinema Assistant!<br><br>
            I can help you with:<br>
            • 🎬 Current movies<br>
            • 💺 Seat availability<br>
            • 🎟️ Booking tickets<br>
            • 💰 Ticket prices<br>
            • 📅 Coming soon movies<br><br>
            What would you like to know?<br><br>
            Type 'help' for a list of commands
        </div>
    </div>
    <div class="chat-input-area">
        <input type="text" id="chatInput" placeholder="Type your message here..." autocomplete="off">
        <button id="chatSendBtn"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<script>
    // Chatbot functionality
    (function() {
        const floatBtn = document.getElementById('chatbotFloatBtn');
        const chatbotWindow = document.getElementById('chatbotWindow');
        const closeBtn = document.getElementById('chatbotCloseBtn');
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const sendBtn = document.getElementById('chatSendBtn');
        
        let isOpen = false;
        
        // Open/Close chatbot window
        floatBtn.addEventListener('click', () => {
            chatbotWindow.classList.add('active');
            isOpen = true;
            setTimeout(() => chatInput.focus(), 300);
        });
        
        closeBtn.addEventListener('click', () => {
            chatbotWindow.classList.remove('active');
            isOpen = false;
        });
        
        function sendMessage() {
            const message = chatInput.value.trim();
            if (message === '') return;
            
            addMessage(message, 'user');
            chatInput.value = '';
            
            showTypingIndicator();
            
            fetch('chatbot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'message=' + encodeURIComponent(message)
            })
            .then(response => response.text())
            .then(data => {
                removeTypingIndicator();
                addMessage(data, 'bot');
            })
            .catch(error => {
                removeTypingIndicator();
                addMessage("Sorry, I'm having trouble connecting. Please try again later.", 'bot');
                console.error('Error:', error);
            });
        }
        
        function addMessage(text, sender) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;
            messageDiv.innerHTML = text;
            chatMessages.appendChild(messageDiv);
            scrollToBottom();
        }
        
        let typingIndicatorElement = null;
        
        function showTypingIndicator() {
            removeTypingIndicator();
            typingIndicatorElement = document.createElement('div');
            typingIndicatorElement.className = 'typing-indicator';
            typingIndicatorElement.innerHTML = '<span></span><span></span><span></span>';
            chatMessages.appendChild(typingIndicatorElement);
            scrollToBottom();
        }
        
        function removeTypingIndicator() {
            if (typingIndicatorElement) {
                typingIndicatorElement.remove();
                typingIndicatorElement = null;
            }
        }
        
        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        sendBtn.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        document.addEventListener('click', function(event) {
            const isClickInside = chatbotWindow.contains(event.target) || floatBtn.contains(event.target);
            if (!isClickInside && chatbotWindow.classList.contains('active')) {
                chatbotWindow.classList.remove('active');
            }
        });
    })();
</script>

</body>
</html>