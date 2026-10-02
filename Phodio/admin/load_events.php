<?php
include 'db.php';
$result = $conn->query("SELECT id, title, booking_date as start, color_code as color FROM bookings");
echo json_encode($result->fetch_all(MYSQLI_ASSOC));
?>