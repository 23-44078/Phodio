<?php

/**
 * Delete one daily tracker entry.
 *
 * The tracker table links to this file, which did not exist before, so the
 * delete button on that page returned 404.
 */

require_once __DIR__ . '/functions.php';

checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header(
        'Location: tracker.php?status=error&message='
        . rawurlencode('Deleting a tracker entry has to be submitted from the tracker page.')
    );
    exit;
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id < 1) {
    header(
        'Location: tracker.php?status=error&message='
        . rawurlencode('Select a valid entry to delete.')
    );
    exit;
}

$stmt = $conn->prepare("DELETE FROM daily_tracker WHERE id = ?");

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: tracker.php?status=deleted");
    exit;
}

error_log('Phodio: could not delete a tracker entry — ' . $conn->error);

header(
    'Location: tracker.php?status=error&message='
    . rawurlencode('Could not delete that entry. Please try again.')
);
exit;
