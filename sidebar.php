<!-- sidebar.php - Reusable sidebar component -->
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