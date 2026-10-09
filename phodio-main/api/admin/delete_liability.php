<?php

/**
 * Delete (settle and remove) one liability record.
 *
 * The liabilities table links to this file, which did not exist before, so
 * the delete button on that page returned 404.
 */

require_once __DIR__ . '/functions.php';

checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header(
        'Location: liabilities.php?status=error&message='
        . rawurlencode('Deleting a liability has to be submitted from the liabilities page.')
    );
    exit;
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id < 1) {
    header(
        'Location: liabilities.php?status=error&message='
        . rawurlencode('Select a valid liability to remove.')
    );
    exit;
}

$stmt = $conn->prepare("DELETE FROM liabilities WHERE id = ?");

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: liabilities.php?status=deleted");
    exit;
}

error_log('Phodio: could not delete a liability — ' . $conn->error);

header(
    'Location: liabilities.php?status=error&message='
    . rawurlencode('Could not remove that record. Please try again.')
);
exit;
