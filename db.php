<?php
date_default_timezone_set('Asia/Manila');

// Pinalitan natin ng parking_db
$conn = new mysqli("localhost", "root", "", "parking_db");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Importante: para tugma ang NOW() sa SQL at PHP DateTime
$conn->query("SET time_zone = '+08:00'");
?>