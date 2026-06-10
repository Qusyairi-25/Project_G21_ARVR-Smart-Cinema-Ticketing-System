<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Please login to use the chatbot.";
    exit();
}

$user_id = $_SESSION['user_id'];
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (empty($message)) {
    echo "Please type a message.";
    exit();
}

$message_lower = strtolower($message);
$response = "";

// Store selected movie, time, step, and selected seats in session
if (!isset($_SESSION['chatbot_selected_movie_id'])) {
    $_SESSION['chatbot_selected_movie_id'] = null;
}
if (!isset($_SESSION['chatbot_selected_movie_title'])) {
    $_SESSION['chatbot_selected_movie_title'] = null;
}
if (!isset($_SESSION['chatbot_selected_time'])) {
    $_SESSION['chatbot_selected_time'] = null;
}
if (!isset($_SESSION['chatbot_step'])) {
    $_SESSION['chatbot_step'] = 'idle'; // idle, waiting_for_showtime, waiting_for_seats
}
if (!isset($_SESSION['chatbot_selected_seats'])) {
    $_SESSION['chatbot_selected_seats'] = []; // Array to store multiple seats
}

$showtimes = ['10:00 AM', '1:30 PM', '4:00 PM', '7:30 PM', '10:30 PM'];

// Function to get movie title by ID
function getMovieTitle($conn, $movie_id) {
    $query = mysqli_query($conn, "SELECT title FROM movies WHERE movie_id = '$movie_id'");
    if ($query && $row = mysqli_fetch_assoc($query)) {
        return $row['title'];
    }
    return "Movie";
}

// Function to get available seats for a specific movie and time
function getAvailableSeats($conn, $movie_id, $show_time) {
    $occupied_sql = "SELECT seat_number FROM bookings WHERE movie_id = ? AND show_time = ? AND status IN ('pending', 'confirmed')";
    $occupied_stmt = mysqli_prepare($conn, $occupied_sql);
    mysqli_stmt_bind_param($occupied_stmt, "is", $movie_id, $show_time);
    mysqli_stmt_execute($occupied_stmt);
    $occupied_result = mysqli_stmt_get_result($occupied_stmt);
    
    $occupied_seats = [];
    while ($row = mysqli_fetch_assoc($occupied_result)) {
        $occupied_seats[] = $row['seat_number'];
    }
    mysqli_stmt_close($occupied_stmt);
    
    return $occupied_seats;
}

// Function to display seat map with selection support
function displaySeatMap($conn, $movie_id, $movie_title, $show_time, $price, $selected_seats = []) {
    $occupied_seats = getAvailableSeats($conn, $movie_id, $show_time);
    
    $response = "💺 <strong>Seat Availability for '{$movie_title}' at {$show_time}</strong><br><br>
                 🟢 = Available | 🔵 = Selected | 🔴 = Booked<br>
                 💰 Price: RM " . number_format($price, 2) . " per ticket<br><br>
                 
                 <strong>Screen</strong><br>
                 ================<br><br>";
    
    // Generate seat map (rows A-F, seats 1-10)
    $rows = ['A', 'B', 'C', 'D', 'E', 'F'];
    foreach ($rows as $row) {
        $response .= "<strong>Row {$row}:</strong> ";
        for ($i = 1; $i <= 10; $i++) {
            $seat = $row . $i;
            if (in_array($seat, $occupied_seats)) {
                $response .= "🔴 {$seat} ";
            } elseif (in_array($seat, $selected_seats)) {
                $response .= "🔵 {$seat} ";
            } else {
                $response .= "🟢 {$seat} ";
            }
        }
        $response .= "<br>";
    }
    
    $available_count = (6 * 10) - count($occupied_seats);
    $selected_count = count($selected_seats);
    $total_price = $selected_count * $price;
    
    $response .= "<br>📊 <strong>Summary:</strong><br>";
    $response .= "   • Available seats: {$available_count}<br>";
    $response .= "   • Selected seats: {$selected_count}<br>";
    $response .= "   • Total price: RM " . number_format($total_price, 2) . "<br><br>";
    
    if ($selected_count > 0) {
        $seats_list = implode(', ', $selected_seats);
        $response .= "✅ <strong>Currently selected seats:</strong> {$seats_list}<br><br>";
        $response .= "💡 Options:<br>";
        $response .= "   • Type 'add A2' to add another seat<br>";
        $response .= "   • Type 'remove A1' to remove a seat<br>";
        $response .= "   • Type 'confirm' to proceed to payment<br>";
        $response .= "   • Type 'clear' to clear all selected seats<br><br>";
    } else {
        $response .= "💡 To select seats, type: 'add [seat number]'<br>";
        $response .= "💡 Example: 'add A1' or 'add A1, A2, A3' for multiple seats<br><br>";
    }
    
    $response .= "💡 Type 'times' to change showtime or 'movies' to select another movie.";
    
    return $response;
}

// Function to display showtime selection menu
function displayShowtimeMenu($movie_title) {
    global $showtimes;
    
    $response = "🎬 <strong>Select a showtime for '{$movie_title}':</strong><br><br>";
    
    foreach ($showtimes as $index => $time) {
        $response .= ($index + 1) . ". 🕐 {$time}<br>";
    }
    
    $response .= "<br>💡 Please type the number (1-5) or the time:<br>";
    $response .= "Examples: '1' or '10:00 AM' or '4:00 PM'<br><br>";
    $response .= "Type 'cancel' to go back to main menu.";
    
    return $response;
}

// Function to parse multiple seats from message
function parseMultipleSeats($message) {
    $seats = [];
    preg_match_all('/([A-F])(\d{1,2})/i', $message, $matches);
    
    if (isset($matches[0]) && count($matches[0]) > 0) {
        foreach ($matches[0] as $seat) {
            $seats[] = strtoupper($seat);
        }
    }
    
    return $seats;
}

// Function to create booking and get booking IDs for payment
function createBookingsAndRedirect($conn, $user_id, $movie_id, $movie_title, $show_time, $selected_seats, $price) {
    $booking_ids = [];
    $booking_date = date('Y-m-d H:i:s');
    
    foreach ($selected_seats as $seat) {
        $insert = mysqli_query($conn, "INSERT INTO bookings (user_id, movie_id, show_time, seat_number, total_price, booking_date, status) 
                                       VALUES ('$user_id', '$movie_id', '$show_time', '$seat', '$price', '$booking_date', 'pending')");
        
        if ($insert) {
            $booking_ids[] = mysqli_insert_id($conn);
        } else {
            return false;
        }
    }
    
    if (count($booking_ids) > 0) {
        // Return the booking IDs as a comma-separated string
        return implode(',', $booking_ids);
    }
    
    return false;
}

// ========== CHATBOT RESPONSES ==========

// 0. HANDLE SHOWTIME SELECTION (if waiting for showtime)
if ($_SESSION['chatbot_step'] == 'waiting_for_showtime' && 
    !(strpos($message_lower, 'movie') !== false || 
      strpos($message_lower, 'help') !== false ||
      strpos($message_lower, 'cancel') !== false)) {
    
    $selected_time = null;
    
    // Check if user typed a number (1-5)
    if (preg_match('/^[1-5]$/', $message)) {
        $index = (int)$message - 1;
        $selected_time = $showtimes[$index];
    }
    // Check if user typed a time
    else {
        foreach ($showtimes as $time) {
            if (strpos($message_lower, strtolower($time)) !== false) {
                $selected_time = $time;
                break;
            }
        }
    }
    
    if ($selected_time) {
        $movie_id = $_SESSION['chatbot_selected_movie_id'];
        $movie_title = $_SESSION['chatbot_selected_movie_title'];
        $price_query = mysqli_query($conn, "SELECT price FROM movies WHERE movie_id = '$movie_id'");
        $movie_data = mysqli_fetch_assoc($price_query);
        $price = $movie_data['price'];
        
        $_SESSION['chatbot_selected_time'] = $selected_time;
        $_SESSION['chatbot_selected_seats'] = [];
        $_SESSION['chatbot_step'] = 'waiting_for_seats';
        
        $response = displaySeatMap($conn, $movie_id, $movie_title, $selected_time, $price, []);
    } 
    elseif (strpos($message_lower, 'cancel') !== false) {
        $_SESSION['chatbot_step'] = 'idle';
        $_SESSION['chatbot_selected_movie_id'] = null;
        $_SESSION['chatbot_selected_movie_title'] = null;
        $_SESSION['chatbot_selected_time'] = null;
        $_SESSION['chatbot_selected_seats'] = [];
        $response = "✅ Cancelled. What would you like to do?<br><br>Type 'movies' to see current movies or 'help' for options.";
    }
    else {
        $response = "❌ Invalid showtime selection.<br><br>" . displayShowtimeMenu($_SESSION['chatbot_selected_movie_title']);
    }
}

// 0.5 HANDLE SEAT SELECTION (if waiting for seats)
elseif ($_SESSION['chatbot_step'] == 'waiting_for_seats') {
    $movie_id = $_SESSION['chatbot_selected_movie_id'];
    $movie_title = $_SESSION['chatbot_selected_movie_title'];
    $show_time = $_SESSION['chatbot_selected_time'];
    $price_query = mysqli_query($conn, "SELECT price FROM movies WHERE movie_id = '$movie_id'");
    $movie_data = mysqli_fetch_assoc($price_query);
    $price = $movie_data['price'];
    $occupied_seats = getAvailableSeats($conn, $movie_id, $show_time);
    
    // Handle ADD seats
    if (strpos($message_lower, 'add') !== false) {
        $new_seats = parseMultipleSeats($message);
        
        if (empty($new_seats)) {
            $response = "❌ Please specify which seat(s) to add.<br><br>Example: 'add A1' or 'add A1, A2, A3'";
        } else {
            $added_seats = [];
            $failed_seats = [];
            
            foreach ($new_seats as $seat) {
                if (in_array($seat, $occupied_seats)) {
                    $failed_seats[] = "{$seat} (already booked)";
                } elseif (in_array($seat, $_SESSION['chatbot_selected_seats'])) {
                    $failed_seats[] = "{$seat} (already selected)";
                } else {
                    $_SESSION['chatbot_selected_seats'][] = $seat;
                    $added_seats[] = $seat;
                }
            }
            
            if (count($added_seats) > 0) {
                $response = "✅ Added: " . implode(', ', $added_seats) . "<br><br>";
            }
            if (count($failed_seats) > 0) {
                $response .= "❌ Could not add: " . implode(', ', $failed_seats) . "<br><br>";
            }
            
            $response .= displaySeatMap($conn, $movie_id, $movie_title, $show_time, $price, $_SESSION['chatbot_selected_seats']);
        }
    }
    
    // Handle REMOVE seats
    elseif (strpos($message_lower, 'remove') !== false) {
        $remove_seats = parseMultipleSeats($message);
        
        if (empty($remove_seats)) {
            $response = "❌ Please specify which seat(s) to remove.<br><br>Example: 'remove A1' or 'remove A1, A2'";
        } else {
            $removed_seats = [];
            $failed_seats = [];
            
            foreach ($remove_seats as $seat) {
                $key = array_search($seat, $_SESSION['chatbot_selected_seats']);
                if ($key !== false) {
                    unset($_SESSION['chatbot_selected_seats'][$key]);
                    $removed_seats[] = $seat;
                } else {
                    $failed_seats[] = $seat;
                }
            }
            
            // Re-index array
            $_SESSION['chatbot_selected_seats'] = array_values($_SESSION['chatbot_selected_seats']);
            
            if (count($removed_seats) > 0) {
                $response = "✅ Removed: " . implode(', ', $removed_seats) . "<br><br>";
            }
            if (count($failed_seats) > 0) {
                $response .= "❌ Could not remove: " . implode(', ', $failed_seats) . " (not in selection)<br><br>";
            }
            
            $response .= displaySeatMap($conn, $movie_id, $movie_title, $show_time, $price, $_SESSION['chatbot_selected_seats']);
        }
    }
    
    // Handle CLEAR all seats
    elseif (strpos($message_lower, 'clear') !== false) {
        $_SESSION['chatbot_selected_seats'] = [];
        $response = "🗑️ Cleared all selected seats.<br><br>";
        $response .= displaySeatMap($conn, $movie_id, $movie_title, $show_time, $price, []);
    }
    
    // Handle CONFIRM booking - NOW REDIRECTS TO PAYMENT
    elseif (strpos($message_lower, 'confirm') !== false || strpos($message_lower, 'done') !== false) {
        if (count($_SESSION['chatbot_selected_seats']) == 0) {
            $response = "❌ No seats selected. Please add seats first.<br><br>";
            $response .= displaySeatMap($conn, $movie_id, $movie_title, $show_time, $price, []);
        } else {
            // Create bookings and get booking IDs
            $booking_ids_string = createBookingsAndRedirect($conn, $user_id, $movie_id, $movie_title, $show_time, $_SESSION['chatbot_selected_seats'], $price);
            
            if ($booking_ids_string) {
                $seats_list = implode(', ', $_SESSION['chatbot_selected_seats']);
                $total_seats = count($_SESSION['chatbot_selected_seats']);
                $total_price = $total_seats * $price;
                
                // Return a response with a clickable link to payment page
                $response = "✅ <strong>Bookings Created Successfully!</strong><br><br>
                             🎬 Movie: {$movie_title}<br>
                             💺 Seats: {$seats_list}<br>
                             🕐 Showtime: {$show_time}<br>
                             🎟️ Number of tickets: {$total_seats}<br>
                             💰 Total price: RM " . number_format($total_price, 2) . "<br><br>
                             
                             ⚠️ <strong>IMPORTANT:</strong> Your seats are reserved for 15 minutes.<br><br>
                             
                             <a href='payment.php?booking_ids={$booking_ids_string}' 
                                style='display: inline-block; background: linear-gradient(45deg, #800020, #ff4d6d); 
                                       color: white; padding: 12px 24px; border-radius: 10px; 
                                       text-decoration: none; font-weight: bold; margin-top: 10px;'>
                                💳 Click Here to Complete Payment →
                             </a><br><br>
                             
                             Or copy this link to pay later:<br>
                             <code style='background: #2d3561; padding: 5px; border-radius: 5px; font-size: 12px;'>
                             payment.php?booking_ids={$booking_ids_string}
                             </code><br><br>
                             
                             ⏰ <strong>Note:</strong> Your booking will expire if not paid within 15 minutes!<br><br>
                             
                             <a href='ticket.php' style='color: #ff4d6d;'>Or view all your tickets here →</a>";
                
                // Reset session after successful booking creation
                $_SESSION['chatbot_step'] = 'idle';
                $_SESSION['chatbot_selected_movie_id'] = null;
                $_SESSION['chatbot_selected_movie_title'] = null;
                $_SESSION['chatbot_selected_time'] = null;
                $_SESSION['chatbot_selected_seats'] = [];
            } else {
                $response = "❌ Booking failed. Some seats may have been taken. Please try again.<br><br>
                             Type 'seats' to see updated seat availability.";
            }
        }
    }
    
    // Handle SHOW SEATS (redisplay map)
    elseif (strpos($message_lower, 'seats') !== false || strpos($message_lower, 'map') !== false) {
        $response = displaySeatMap($conn, $movie_id, $movie_title, $show_time, $price, $_SESSION['chatbot_selected_seats']);
    }
    
    // Handle CHANGE SHOWTIME
    elseif (strpos($message_lower, 'times') !== false || strpos($message_lower, 'change time') !== false) {
        $_SESSION['chatbot_step'] = 'waiting_for_showtime';
        $response = displayShowtimeMenu($movie_title);
    }
    
    // Handle CANCEL
    elseif (strpos($message_lower, 'cancel') !== false) {
        $_SESSION['chatbot_step'] = 'idle';
        $_SESSION['chatbot_selected_movie_id'] = null;
        $_SESSION['chatbot_selected_movie_title'] = null;
        $_SESSION['chatbot_selected_time'] = null;
        $_SESSION['chatbot_selected_seats'] = [];
        $response = "✅ Booking cancelled. What would you like to do?<br><br>Type 'movies' to see current movies or 'help' for options.";
    }
    
    // Show current selection status
    else {
        $response = "🎟️ <strong>Current Booking Session</strong><br><br>";
        if (count($_SESSION['chatbot_selected_seats']) > 0) {
            $seats_list = implode(', ', $_SESSION['chatbot_selected_seats']);
            $total = count($_SESSION['chatbot_selected_seats']) * $price;
            $response .= "✅ Selected seats: {$seats_list}<br>";
            $response .= "💰 Total: RM " . number_format($total, 2) . "<br><br>";
        } else {
            $response .= "📭 No seats selected yet.<br><br>";
        }
        
        $response .= "💡 <strong>Available Commands:</strong><br>";
        $response .= "   • 'add A1' - Add a seat<br>";
        $response .= "   • 'add A1, A2, A3' - Add multiple seats<br>";
        $response .= "   • 'remove A1' - Remove a seat<br>";
        $response .= "   • 'clear' - Clear all selected seats<br>";
        $response .= "   • 'seats' - Show seat map again<br>";
        $response .= "   • 'times' - Change showtime<br>";
        $response .= "   • 'confirm' - Proceed to payment<br>";
        $response .= "   • 'cancel' - Cancel booking<br><br>";
        
        $response .= displaySeatMap($conn, $movie_id, $movie_title, $show_time, $price, $_SESSION['chatbot_selected_seats']);
    }
}

// 1. GREETINGS
elseif (strpos($message_lower, 'hello') !== false || 
        strpos($message_lower, 'hi') !== false || 
        strpos($message_lower, 'hey') !== false) {
    
    // Reset any pending states
    $_SESSION['chatbot_step'] = 'idle';
    $_SESSION['chatbot_selected_movie_id'] = null;
    $_SESSION['chatbot_selected_movie_title'] = null;
    $_SESSION['chatbot_selected_time'] = null;
    $_SESSION['chatbot_selected_seats'] = [];
    
    $response = "👋 Hello! Welcome to ARVR Cinema Assistant!<br><br>
                 I can help you book tickets for multiple seats! 🎉<br><br>
                 
                 I can help you with:<br>
                 • 🎬 Current movies<br>
                 • 💺 Seat availability (for a specific movie & time)<br>
                 • 🎟️ Book multiple seats at once<br>
                 • 💰 Ticket prices<br>
                 • 📅 Coming soon movies<br><br>
                 
                 💡 <strong>Try this example flow:</strong><br>
                 1️⃣ 'seats for Oppenheimer'<br>
                 2️⃣ '3' (select 4:00 PM)<br>
                 3️⃣ 'add A1, A2, A3' (select 3 seats)<br>
                 4️⃣ 'confirm' (proceed to payment)<br>
                 5️⃣ Complete payment on the next page<br><br>
                 
                 What would you like to know?";
}

// 2. CURRENT MOVIES
elseif (strpos($message_lower, 'movie') !== false || 
        strpos($message_lower, 'current') !== false || 
        strpos($message_lower, 'now playing') !== false) {
    
    $_SESSION['chatbot_step'] = 'idle';
    
    $query = "SELECT * FROM movies WHERE status = 'now_showing' ORDER BY rating DESC";
    $movies = mysqli_query($conn, $query);
    
    if ($movies && mysqli_num_rows($movies) > 0) {
        $response = "🎬 <strong>Currently Showing Movies:</strong><br><br>";
        while ($movie = mysqli_fetch_assoc($movies)) {
            $title = htmlspecialchars($movie['title'] ?? 'Unknown');
            $genre = $movie['genre'] ?? 'N/A';
            $duration = $movie['duration'] ?? 'N/A';
            $rating = $movie['rating'] ?? 'N/A';
            $price = number_format($movie['price'] ?? 15, 2);
            
            $response .= "🎥 <strong>{$title}</strong><br>";
            $response .= "   🎭 Genre: {$genre}<br>";
            $response .= "   ⏱️ Duration: {$duration} mins<br>";
            $response .= "   ⭐ Rating: {$rating}/10<br>";
            $response .= "   💰 Price: RM {$price}<br><br>";
        }
        $response .= "💡 To book tickets, type: 'seats for [movie name]'<br>";
        $response .= "💡 Example: 'seats for Oppenheimer'";
    } else {
        $response = "😅 No movies are currently showing. Check back soon for new releases!";
    }
}

// 3. SHOWTIMES
elseif (strpos($message_lower, 'time') !== false || 
        strpos($message_lower, 'showtime') !== false) {
    
    $response = "🕐 <strong>Available Showtimes:</strong><br><br>";
    foreach ($showtimes as $index => $time) {
        $response .= ($index + 1) . ". 🕐 {$time}<br>";
    }
    $response .= "<br>💡 To check seat availability, first select a movie:<br>";
    $response .= "'seats for [movie name]'<br><br>";
    $response .= "Example: 'seats for Oppenheimer'";
}

// 4. SEAT AVAILABILITY - ASK FOR SHOWTIME FIRST
elseif (strpos($message_lower, 'seat') !== false || 
        strpos($message_lower, 'available') !== false) {
    
    // Extract movie name from message
    $movie_name = "";
    
    if (preg_match('/(?:for|in)\s+(.+?)(?:\s+at|\s*$)/i', $message, $movie_matches)) {
        $movie_name = trim($movie_matches[1]);
    }
    
    if (!empty($movie_name)) {
        // Find movie by name
        $movie_query = mysqli_query($conn, "SELECT movie_id, title, price FROM movies WHERE LOWER(title) LIKE '%" . mysqli_real_escape_string($conn, strtolower($movie_name)) . "%' AND status = 'now_showing' LIMIT 1");
        
        if ($movie_query && $movie = mysqli_fetch_assoc($movie_query)) {
            $movie_id = $movie['movie_id'];
            $movie_title = $movie['title'];
            
            // Store selected movie and set step to ask for showtime
            $_SESSION['chatbot_selected_movie_id'] = $movie_id;
            $_SESSION['chatbot_selected_movie_title'] = $movie_title;
            $_SESSION['chatbot_selected_seats'] = [];
            $_SESSION['chatbot_step'] = 'waiting_for_showtime';
            
            $response = displayShowtimeMenu($movie_title);
            
        } else {
            $response = "❌ Could not find movie '{$movie_name}'.<br><br>
                         Type 'movies' to see all currently showing movies.";
        }
    } else {
        $response = "🎬 <strong>To check seat availability and book tickets:</strong><br><br>
                     Type: 'seats for [movie name]'<br><br>
                     Examples:<br>
                     • 'seats for Oppenheimer'<br>
                     • 'seats for Barbie'<br>
                     • 'seats for Interstellar'<br><br>
                     
                     After selecting a movie and showtime, you can:<br>
                     • Add multiple seats: 'add A1, A2, A3'<br>
                     • Remove seats: 'remove A1'<br>
                     • Confirm booking: 'confirm' (redirects to payment)<br><br>
                     
                     Type 'movies' to see all available movies!";
    }
}

// 5. TICKET PRICES
elseif (strpos($message_lower, 'price') !== false || 
        strpos($message_lower, 'cost') !== false) {
    
    $response = "💰 <strong>Ticket Prices by Movie:</strong><br><br>";
    
    $price_query = mysqli_query($conn, "SELECT title, price, rating FROM movies WHERE status = 'now_showing' ORDER BY price DESC");
    
    if ($price_query && mysqli_num_rows($price_query) > 0) {
        while ($movie = mysqli_fetch_assoc($price_query)) {
            $response .= "🎬 <strong>{$movie['title']}</strong><br>";
            $response .= "   💵 RM " . number_format($movie['price'], 2) . " per ticket<br>";
            $response .= "   ⭐ Rating: {$movie['rating']}/10<br><br>";
        }
    }
    
    $response .= "💡 <strong>Group Discounts:</strong><br>
                  • Book 5+ tickets: 10% off automatically!<br>
                  • Students with valid ID: 10% off (Wednesdays)<br>
                  • Senior Citizens (60+): RM 10 flat rate<br>
                  • Children (under 12): RM 10 flat rate";
}

// 6. COMING SOON MOVIES
elseif (strpos($message_lower, 'coming soon') !== false || 
        strpos($message_lower, 'upcoming') !== false) {
    
    $query = "SELECT * FROM movies WHERE status = 'coming_soon' ORDER BY release_date DESC";
    $movies = mysqli_query($conn, $query);
    
    if ($movies && mysqli_num_rows($movies) > 0) {
        $response = "📅 <strong>Coming Soon to ARVR Cinema:</strong><br><br>";
        while ($movie = mysqli_fetch_assoc($movies)) {
            $title = htmlspecialchars($movie['title'] ?? 'Unknown');
            $genre = $movie['genre'] ?? 'N/A';
            $release = $movie['release_date'] ?? 'Coming Soon';
            
            $response .= "🎬 <strong>{$title}</strong><br>";
            $response .= "   🎭 Genre: {$genre}<br>";
            $response .= "   📆 Releases: {$release}<br><br>";
        }
    } else {
        $response = "No upcoming movies announced yet. Stay tuned!";
    }
}

// 7. HELP MENU
elseif (strpos($message_lower, 'help') !== false || 
        strpos($message_lower, 'what can you do') !== false) {
    
    $response = "🤖 <strong>ARVR Assistant Help Menu</strong><br><br>
                 
                 <strong>🎬 Movies:</strong><br>
                 • 'movies' - See current movies<br>
                 • 'coming soon' - Upcoming releases<br><br>
                 
                 <strong>🕐 Showtimes:</strong><br>
                 • 'times' - See available showtimes<br><br>
                 
                 <strong>💺 Book Multiple Seats (Step by Step):</strong><br><br>
                 
                 <strong>Step 1 - Select Movie:</strong><br>
                 • 'seats for Oppenheimer'<br><br>
                 
                 <strong>Step 2 - Choose Showtime:</strong><br>
                 • '3' (selects 4:00 PM)<br><br>
                 
                 <strong>Step 3 - Select Seats:</strong><br>
                 • 'add A1' - Add single seat<br>
                 • 'add A1, A2, A3' - Add multiple seats<br>
                 • 'remove B2' - Remove a seat<br>
                 • 'clear' - Clear all selections<br>
                 • 'seats' - Show seat map again<br><br>
                 
                 <strong>Step 4 - Complete Booking:</strong><br>
                 • 'confirm' - Create booking and go to payment page<br><br>
                 
                 <strong>💰 Pricing:</strong><br>
                 • 'price' - Ticket prices<br><br>
                 
                 <strong>📋 Other Commands:</strong><br>
                 • 'my bookings' - View your tickets<br>
                 • 'cancel booking [ID]' - Cancel pending booking<br><br>
                 
                 <strong>Example Flow:</strong><br>
                 1️⃣ 'seats for Oppenheimer'<br>
                 2️⃣ '3'<br>
                 3️⃣ 'add A1, A2, A3'<br>
                 4️⃣ 'confirm'<br>
                 5️⃣ Complete payment on the payment page<br><br>
                 
                 Need more help? Visit our cinema counter! 😊";
}

// 8. MY BOOKINGS
elseif (strpos($message_lower, 'my bookings') !== false || 
        strpos($message_lower, 'my tickets') !== false ||
        strpos($message_lower, 'my reservation') !== false) {
    
    $query = mysqli_query($conn, "SELECT b.*, m.title 
                                   FROM bookings b 
                                   LEFT JOIN movies m ON b.movie_id = m.movie_id 
                                   WHERE b.user_id = '$user_id' 
                                   ORDER BY b.booking_date DESC LIMIT 5");
    
    if ($query && mysqli_num_rows($query) > 0) {
        $response = "🎫 <strong>Your Recent Bookings:</strong><br><br>";
        while ($booking = mysqli_fetch_assoc($query)) {
            $status_icon = $booking['status'] == 'confirmed' ? '✅' : ($booking['status'] == 'cancelled' ? '❌' : '⏳');
            $status_text = $booking['status'] == 'confirmed' ? 'Confirmed' : ($booking['status'] == 'cancelled' ? 'Cancelled' : 'Pending Payment');
            
            $response .= "{$status_icon} <strong>Booking #{$booking['booking_id']}</strong><br>";
            $response .= "   🎬 Movie: {$booking['title']}<br>";
            $response .= "   💺 Seat: {$booking['seat_number']}<br>";
            $response .= "   🕐 Time: {$booking['show_time']}<br>";
            $response .= "   💰 Total: RM " . number_format($booking['total_price'], 2) . "<br>";
            $response .= "   📌 Status: {$status_text}<br>";
            
            if ($booking['status'] == 'pending') {
                $response .= "   🔗 <a href='payment.php?booking_ids={$booking['booking_id']}' style='color: #ffd700;'>Complete Payment →</a><br>";
            }
            $response .= "<br>";
        }
       
    } else {
        $response = "📭 You haven't made any bookings yet.<br><br>
                     Type 'seats for [movie name]' to get started! 🎬<br><br>
                     Example: 'seats for Oppenheimer'";
    }
}

// 9. CANCEL BOOKING
elseif (strpos($message_lower, 'cancel') !== false && 
        (strpos($message_lower, 'booking') !== false || strpos($message_lower, 'ticket') !== false)) {
    
    preg_match('/(\d+)/', $message, $matches);
    
    if (isset($matches[0])) {
        $booking_id = $matches[0];
        
        $check_query = mysqli_query($conn, "SELECT status FROM bookings WHERE booking_id = '$booking_id' AND user_id = '$user_id'");
        $booking = mysqli_fetch_assoc($check_query);
        
        if ($booking) {
            if ($booking['status'] == 'pending') {
                $cancel_query = mysqli_query($conn, "UPDATE bookings SET status = 'cancelled' WHERE booking_id = '$booking_id' AND user_id = '$user_id'");
                
                if ($cancel_query) {
                    $response = "✅ Booking #{$booking_id} has been cancelled successfully.<br><br>
                                 The seat is now available for others to book.<br>
                                 <a href='ticket.php' style='color: #ff4d6d;'>View your tickets →</a>";
                } else {
                    $response = "❌ Could not cancel booking #{$booking_id}. Please try again.";
                }
            } else {
                $response = "❌ Cannot cancel booking #{$booking_id} because it is already {$booking['status']}.<br>
                             Only pending bookings can be cancelled.";
            }
        } else {
            $response = "❌ Booking #{$booking_id} not found or doesn't belong to you.";
        }
    } else {
        $response = "❌ To cancel a booking, type: 'cancel booking [ID]'<br><br>
                     Example: 'cancel booking 123'<br>
                     Type 'my bookings' to see your booking IDs.";
    }
}

// 10. DEFAULT RESPONSE
else {
    $response = "🤔 I'm not sure I understand.<br><br>
                 
                 💡 <strong>Try these commands:</strong><br>
                 • 'movies' - See current movies<br>
                 • 'seats for Oppenheimer' - Start booking<br>
                 • 'times' - See showtimes<br>
                 • 'price' - Ticket prices<br>
                 • 'my bookings' - View your tickets<br>
                 • 'help' - See all commands<br><br>
                 
                 🎟️ <strong>Want to book multiple seats?</strong><br>
                 Just type 'seats for [movie name]' and I'll guide you!<br><br>
                 
                 What would you like to know?";
}

// Save to database logs
$stmt = $conn->prepare("INSERT INTO chatbot_logs (user_id, user_message, bot_reply) VALUES (?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("iss", $user_id, $message, $response);
    $stmt->execute();
    $stmt->close();
}

// Return the response
echo $response;
?>