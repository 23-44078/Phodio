<?php
require_once __DIR__ . '/functions.php';
checkLogin();

if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id === null) {
        header('Location: liabilities.php');
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM liabilities WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: liabilities.php?status=deleted");
    } else {
        header('Location: liabilities.php?error=' . rawurlencode('Could not delete this liability. Please try again.'));
    }

    $stmt->close();
} else {
    header("Location: liabilities.php");
}
exit;
