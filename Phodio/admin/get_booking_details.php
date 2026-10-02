<?php
include 'db.php';
if(isset($_POST['id'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_assoc());
}
?>