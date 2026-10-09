<?php

/**
 * Delete one expense record.
 *
 * Two fixes here:
 *   - checkLogin() is now called, so the URL cannot be used by a stranger.
 *   - Only POST is accepted. A GET link could be triggered by any other site
 *     (or a preloaded image) while the studio was signed in.
 */

require_once __DIR__ . '/functions.php';

checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header(
        'Location: expenses.php?status=error&message='
        . rawurlencode('Deleting an expense has to be submitted from the expenses page.')
    );
    exit;
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id < 1) {
    header(
        'Location: expenses.php?status=error&message='
        . rawurlencode('Select a valid expense to delete.')
    );
    exit;
}

$stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: expenses.php?status=deleted");
    exit;
}

// Logged, never printed: database errors must not reach the browser.
error_log('Phodio: could not delete an expense — ' . $conn->error);

header(
    'Location: expenses.php?status=error&message='
    . rawurlencode('Could not delete that record. Please try again.')
);
exit;
