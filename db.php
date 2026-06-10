<?php
$conn = new mysqli(
    "sql304.infinityfree.com",
    "if0_42146567",
    "8B3nhuqYd9KWBM",
    "if0_42146567_cinema"
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>