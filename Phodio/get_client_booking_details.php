<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db/db.php';

if (!isset($_SESSION['client'])) exit;

$id = $_POST['id'] ?? null;
if (!$id) exit;

// Get client ID
$stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
$stmt->bind_param("s", $_SESSION['client']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$clientId = $user['id'];

// Fetch booking only if it belongs to this client
$stmt = $conn->prepare("SELECT id, package, price, motif, date, time FROM bookings WHERE id=? AND client_id=?");
$stmt->bind_param("ii", $id, $clientId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if ($booking) {
    // Provide the value for select input (Package|Price|Duration)
    $booking['packageValue'] = $booking['package'].'|'.$booking['price'].'|NA';
    header('Content-Type: application/json');
    echo json_encode($booking);
} else {
    echo json_encode([]);
}