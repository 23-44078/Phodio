<?php
include 'db.php';
if(isset($_GET['id'])) {
    $id = $_GET['id'];
    $conn->query("DELETE FROM bookings WHERE id = $id");
}
header("Location: index.php");
?>