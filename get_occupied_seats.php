<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

$movie_id = isset($_GET['movie_id']) ? (int)$_GET['movie_id'] : 0;
$show_time = isset($_GET['time']) ? $_GET['time'] : '';

if ($movie_id == 0 || empty($show_time)) {
    echo json_encode(['occupied' => []]);
    exit();
}

// Get all pending and confirmed bookings for this movie and time
$sql = "SELECT seat_number FROM bookings 
        WHERE movie_id = ? 
        AND show_time = ? 
        AND status IN ('pending', 'confirmed')";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $movie_id, $show_time);
$stmt->execute();
$result = $stmt->get_result();

$occupied = [];
while ($row = $result->fetch_assoc()) {
    $occupied[] = $row['seat_number'];
}

echo json_encode(['occupied' => $occupied]);
?>