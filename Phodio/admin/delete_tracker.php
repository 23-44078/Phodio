<?php
require_once __DIR__ . '/functions.php';
checkLogin();

if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id === null) {
        header('Location: tracker.php');
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM daily_tracker WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: tracker.php?status=deleted");
    } else {
        header('Location: tracker.php?error=' . rawurlencode('Could not delete this daily entry. Please try again.'));
    }

    $stmt->close();
} else {
    header("Location: tracker.php");
}
exit;
