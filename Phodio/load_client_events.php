<?php
session_start();
include 'db/db.php';

if (!isset($_SESSION['client_id'])) {
    die("Unauthorized access");
}

$client_id = $_SESSION['client_id'];

// Fetch all bookings for the calendar
$sql = "SELECT id, client_id, package_type, booking_date, start_time, color_code FROM bookings";
$result = $conn->query($sql);

$events = [];

while ($row = $result->fetch_assoc()) {
    $title = ($row['client_id'] == $client_id) ? $row['package_type'] : "Booked"; // hide others' names
    $events[] = [
        'id' => $row['id'],
        'title' => $title,
        'start' => $row['booking_date'] . 'T' . $row['start_time'],
        'color' => $row['color_code']
    ];
}

header('Content-Type: application/json');
echo json_encode($events);
?>