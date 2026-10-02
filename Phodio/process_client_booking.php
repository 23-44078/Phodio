<?php
session_start();
include 'db/db.php';

if (!isset($_SESSION['client_id'])) {
    die("Unauthorized access");
}

$client_id = $_SESSION['client_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package = trim($_POST['package']);
    $motif   = trim($_POST['motif']);
    $date    = $_POST['date'];
    $time    = $_POST['time'];

    // Assign color based on package type (optional)
    $color_map = [
        'Solo' => '#3b82f6',
        'Duo' => '#10b981',
        '(3-4 pax)' => '#f59e0b'
    ];
    $color = $color_map[$package] ?? '#ef4444';

    // Check if client already has 2 bookings for the same day
    $stmt_check = $conn->prepare("SELECT COUNT(*) AS count FROM bookings WHERE client_id=? AND booking_date=?");
    $stmt_check->bind_param("is", $client_id, $date);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result()->fetch_assoc();

    if ($result_check['count'] >= 2) {
        echo "You can only have 2 bookings per day";
        exit;
    }

    // Insert booking
    $stmt = $conn->prepare("INSERT INTO bookings (client_id, package_type, motif, booking_date, start_time, color_code) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $client_id, $package, $motif, $date, $time, $color);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>