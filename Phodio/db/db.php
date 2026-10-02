<?php
// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "studio_mgmt";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    // In production, you'd want to log this instead of dying with the error
    die("Database Connection failed: " . $conn->connect_error);
}

// Set character set to utf8mb4 (CRITICAL for password hashes and emojis)
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'");

// Keep PHP date validation and SQL timestamps aligned to Philippine Time.
date_default_timezone_set('Asia/Manila');
?>