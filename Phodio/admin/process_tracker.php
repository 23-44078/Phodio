<?php
include 'functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['track_date'];
    $income = $_POST['income_today'];
    $clients = $_POST['client_today'];
    $target = $_POST['target'];

    // Using ON DUPLICATE KEY UPDATE so you can update a day's record if you log it twice
    $stmt = $conn->prepare("INSERT INTO daily_tracker (track_date, income_today, client_today, target) 
                            VALUES (?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE income_today=?, client_today=?, target=?");
    
    $stmt->bind_param("sdiidii", $date, $income, $clients, $target, $income, $clients, $target);

    if ($stmt->execute()) {
        header("Location: tracker.php?status=success");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>