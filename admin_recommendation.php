<?php
session_start();
include 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo "Access Denied. Admin only.";
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Handle save settings
$success_message = '';
$error_message = '';

// Get current settings
$recommendation_mode = $_SESSION['recommendation_mode'] ?? 'auto';
$fixed_logic = $_SESSION['fixed_logic'] ?? 'popular';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $recommendation_mode = $_POST['recommendation_mode'] ?? 'auto';
    $fixed_logic = $_POST['fixed_logic'] ?? 'popular';
    
    $_SESSION['recommendation_mode'] = $recommendation_mode;
    $_SESSION['fixed_logic'] = $fixed_logic;
    
    $success_message = "Recommendation settings saved successfully!";
}

// Get all users for dropdown
$users_sql = "SELECT user_id, username, role FROM users ORDER BY username";
$users_result = $conn->query($users_sql);
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Recommendation Settings - ARVR Cinema Admin</title>
    <link rel="icon" type="image/png" href="ARVR MOVIE LOGO.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #0a0a0a;
            color: white;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #1a0a0f 0%, #0a0a0a 100%);
            padding: 20px 40px;
            border-bottom: 2px solid #ff4d6d;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .logo h1 {
            font-size: 24px;
            background: linear-gradient(45deg, #ff4d6d, #ff8c00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo p {
            font-size: 14px;
            opacity: 0.7;
        }
        
        .admin-badge {
            background: #f44336;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info .username {
            color: #ff4d6d;
            font-weight: 600;
        }
        
        .btn {
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #ff4d6d;
            color: white;
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .btn-primary:hover, .btn-secondary:hover {
            transform: translateY(-2px);
        }
        
        .settings-section {
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .settings-card {
            background: rgba(255,255,255,0.03);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .settings-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .settings-header i {
            font-size: 32px;
            color: #ff4d6d;
        }
        
        .settings-header h2 {
            font-size: 24px;
        }
        
        .settings-header p {
            opacity: 0.7;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .radio-group {
            margin-bottom: 30px;
        }
        
        .radio-option {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: 0.3s;
            border: 2px solid transparent;
        }
        
        .radio-option:hover {
            background: rgba(255,255,255,0.08);
        }
        
        .radio-option.selected {
            background: rgba(255,77,109,0.1);
            border-color: #ff4d6d;
        }
        
        .radio-option input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.2);
            cursor: pointer;
            accent-color: #ff4d6d;
        }
        
        .radio-option label {
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .radio-desc {
            margin-left: 30px;
            margin-top: 10px;
            opacity: 0.7;
            font-size: 14px;
        }
        
        .logic-options {
            margin-left: 40px;
            margin-top: 15px;
            padding: 15px;
            background: rgba(0,0,0,0.3);
            border-radius: 10px;
            display: none;
        }
        
        .logic-options.show {
            display: block;
        }
        
        .logic-option {
            margin-bottom: 12px;
        }
        
        .logic-option input[type="radio"] {
            margin-right: 10px;
            accent-color: #ff4d6d;
        }
        
        .logic-option label {
            font-size: 15px;
            font-weight: normal;
        }
        
        .preview-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .preview-header h3 {
            font-size: 18px;
        }
        
        .preview-header h3 i {
            color: #ff4d6d;
            margin-right: 8px;
        }
        
        .user-select {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* Custom Select Dropdown */
        .custom-select {
            position: relative;
            min-width: 250px;
        }
        
        .select-trigger {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            padding: 10px 15px;
            color: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.3s;
        }
        
        .select-trigger:hover {
            background: rgba(255,255,255,0.1);
            border-color: #ff4d6d;
        }
        
        .select-trigger span {
            font-size: 14px;
        }
        
        .select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1a1a2e;
            border-radius: 8px;
            margin-top: 5px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .select-dropdown.show {
            display: block;
        }
        
        .select-option {
            padding: 10px 15px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .select-option:hover {
            background: rgba(255,77,109,0.2);
        }
        
        .select-option .user-role {
            font-size: 11px;
            opacity: 0.6;
        }
        
        .role-badge-small {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .role-user-small { background: #2196F3; }
        .role-staff-small { background: #FF9800; }
        .role-admin-small { background: #f44336; }
        
        .preview-movies {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .preview-movie-card {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            overflow: hidden;
            transition: 0.3s;
        }
        
        .preview-movie-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.08);
        }
        
        .preview-movie-poster {
            width: 100%;
            height: 250px;
            object-fit: cover;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
        }
        
        .preview-movie-info {
            padding: 12px;
        }
        
        .preview-movie-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .preview-movie-genre {
            font-size: 12px;
            opacity: 0.6;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            opacity: 0.6;
        }
        
        .message {
            padding: 15px 40px;
            margin: 0 40px 20px 40px;
            border-radius: 10px;
        }
        
        .message.success {
            background: rgba(76, 175, 80, 0.2);
            border-left: 4px solid #4CAF50;
        }
        
        .message.error {
            background: rgba(244, 67, 54, 0.2);
            border-left: 4px solid #f44336;
        }
        
        .save-btn {
            background: #ff4d6d;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
            margin-top: 20px;
        }
        
        .save-btn:hover {
            background: #e63946;
            transform: translateY(-2px);
        }
        
        .info-note {
            background: rgba(255,77,109,0.1);
            border-radius: 10px;
            padding: 15px;
            margin-top: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
        }
        
        .info-note i {
            color: #ff4d6d;
            font-size: 20px;
        }
        
        @media (max-width: 768px) {
            .admin-header {
                padding: 20px;
            }
            
            .settings-section {
                padding: 20px;
            }
            
            .settings-card {
                padding: 20px;
            }
            
            .preview-movies {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
            
            .preview-movie-poster {
                height: 200px;
            }
            
            .preview-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-select {
                width: 100%;
            }
            
            .custom-select {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="logo">
            <h1><i class="fas fa-magic"></i> Recommendation Settings</h1>
            <p>Configure how movies are recommended to customers</p>
        </div>
        <div class="user-info">
            <span class="admin-badge"><i class="fas fa-crown"></i> ADMIN</span>
            <span class="username"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
            <a href="staff_homepage.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <?php if ($success_message): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="settings-section">
        <form method="POST" action="" id="settingsForm">
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fas fa-sliders-h"></i>
                    <div>
                        <h2>Recommendation Logic</h2>
                        <p>Choose how the system recommends movies to customers</p>
                    </div>
                </div>
                
                <div class="radio-group">
                    <div class="radio-option <?php echo ($recommendation_mode == 'auto') ? 'selected' : ''; ?>" onclick="document.getElementById('mode_auto').checked = true; updateUI();">
                        <input type="radio" name="recommendation_mode" id="mode_auto" value="auto" <?php echo ($recommendation_mode == 'auto') ? 'checked' : ''; ?> onchange="updateUI()">
                        <label for="mode_auto">🤖 Auto (Smart Mode)</label>
                        <div class="radio-desc">System automatically chooses the best logic per user. New users see popular movies. Returning users see personalized recommendations based on their watch history.</div>
                    </div>
                    
                    <div class="radio-option <?php echo ($recommendation_mode == 'fixed') ? 'selected' : ''; ?>" onclick="document.getElementById('mode_fixed').checked = true; updateUI();">
                        <input type="radio" name="recommendation_mode" id="mode_fixed" value="fixed" <?php echo ($recommendation_mode == 'fixed') ? 'checked' : ''; ?> onchange="updateUI()">
                        <label for="mode_fixed">🔒 Fixed Logic (Same for Everyone)</label>
                        <div class="radio-desc">All customers see the same type of recommendations regardless of their watch history.</div>
                    </div>
                    
                    <div class="logic-options <?php echo ($recommendation_mode == 'fixed') ? 'show' : ''; ?>" id="logicOptions">
                        <div class="logic-option">
                            <input type="radio" name="fixed_logic" id="logic_popular" value="popular" <?php echo ($fixed_logic == 'popular') ? 'checked' : ''; ?>>
                            <label for="logic_popular">🔥 Popular Now - Show most booked movies in the last 7 days</label>
                        </div>
                        <div class="logic-option">
                            <input type="radio" name="fixed_logic" id="logic_history" value="history" <?php echo ($fixed_logic == 'history') ? 'checked' : ''; ?>>
                            <label for="logic_history">🎯 Based on Watch History - Show movies matching user's most-watched genre</label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save Settings</button>
                
                <div class="info-note">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>How it works:</strong> When a customer visits the homepage, they'll see 6 movie recommendations based on your selected logic. 
                        For "Based on Watch History", the system looks at the user's confirmed bookings and finds the genre they watch most.
                    </div>
                </div>
            </div>
        </form>
        
        <div class="settings-card">
            <div class="preview-section">
                <div class="preview-header">
                    <h3><i class="fas fa-eye"></i> Live Preview</h3>
                    <div class="user-select">
                        <div class="custom-select" id="userSelect">
                            <div class="select-trigger" onclick="toggleDropdown()">
                                <span id="selectedUserName">Select a user</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="select-dropdown" id="selectDropdown">
                                <?php foreach ($users as $user): ?>
                                    <div class="select-option" data-user-id="<?php echo $user['user_id']; ?>" data-user-name="<?php echo htmlspecialchars($user['username']); ?>" onclick="selectUser(this)">
                                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                                        <span class="role-badge-small role-<?php echo $user['role']; ?>-small"><?php echo strtoupper($user['role']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="loadPreview()"><i class="fas fa-search"></i> Preview</button>
                    </div>
                </div>
                <div id="previewResults">
                    <div class="loading">
                        <i class="fas fa-user"></i> Select a user and click Preview
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let selectedUserId = null;
        let selectedUserName = null;
        
        function toggleDropdown() {
            const dropdown = document.getElementById('selectDropdown');
            dropdown.classList.toggle('show');
        }
        
        function selectUser(element) {
            selectedUserId = element.dataset.userId;
            selectedUserName = element.dataset.userName;
            
            document.getElementById('selectedUserName').innerHTML = selectedUserName;
            
            // Close dropdown
            document.getElementById('selectDropdown').classList.remove('show');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const select = document.getElementById('userSelect');
            if (!select.contains(event.target)) {
                document.getElementById('selectDropdown').classList.remove('show');
            }
        });
        
        function updateUI() {
            const autoRadio = document.getElementById('mode_auto');
            const fixedRadio = document.getElementById('mode_fixed');
            const logicOptions = document.getElementById('logicOptions');
            
            if (autoRadio.checked) {
                document.querySelectorAll('.radio-option').forEach(opt => opt.classList.remove('selected'));
                autoRadio.closest('.radio-option').classList.add('selected');
                logicOptions.classList.remove('show');
            } else if (fixedRadio.checked) {
                document.querySelectorAll('.radio-option').forEach(opt => opt.classList.remove('selected'));
                fixedRadio.closest('.radio-option').classList.add('selected');
                logicOptions.classList.add('show');
            }
        }
        
        async function loadPreview() {
            if (!selectedUserId) {
                alert('Please select a user first');
                return;
            }
            
            const mode = document.querySelector('input[name="recommendation_mode"]:checked').value;
            const fixedLogic = document.querySelector('input[name="fixed_logic"]:checked')?.value || 'popular';
            
            const previewDiv = document.getElementById('previewResults');
            previewDiv.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-pulse"></i> Loading recommendations for ' + selectedUserName + '...</div>';
            
            try {
                const response = await fetch(`get_recommendations_preview.php?user_id=${selectedUserId}&mode=${mode}&fixed_logic=${fixedLogic}`);
                const data = await response.json();
                
                if (data.error) {
                    previewDiv.innerHTML = `<div class="loading" style="color: #f44336;"><i class="fas fa-exclamation-circle"></i> ${data.error}</div>`;
                    return;
                }
                
                if (data.movies && data.movies.length > 0) {
                    let html = '<div class="preview-movies">';
                    data.movies.forEach(movie => {
                        const posterUrl = movie.poster || 'https://via.placeholder.com/300x450?text=No+Poster';
                        html += `
                            <div class="preview-movie-card">
                                <img class="preview-movie-poster" src="${posterUrl}" alt="${movie.title}" onerror="this.src='https://via.placeholder.com/300x450?text=No+Poster'">
                                <div class="preview-movie-info">
                                    <div class="preview-movie-title">${escapeHtml(movie.title)}</div>
                                    <div class="preview-movie-genre">${movie.genre || 'N/A'}</div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    previewDiv.innerHTML = html;
                } else {
                    previewDiv.innerHTML = '<div class="loading"><i class="fas fa-film"></i> No recommendations available for this user</div>';
                }
            } catch (error) {
                previewDiv.innerHTML = '<div class="loading" style="color: #f44336;"><i class="fas fa-exclamation-circle"></i> Error loading preview</div>';
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Auto-load when radio changes
        document.querySelectorAll('input[name="recommendation_mode"], input[name="fixed_logic"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (selectedUserId) loadPreview();
            });
        });
    </script>
</body>
</html>