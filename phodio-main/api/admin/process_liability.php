<?php

/**
 * Save a new liability.
 *
 * The guard matters: this endpoint writes to the studio's books, so it must
 * only be reachable by a signed-in studio account.
 */

require_once __DIR__ . '/functions.php';

checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liabilities.php');
    exit;
}

$creditor = trim((string) ($_POST['creditor'] ?? ''));
$desc     = trim((string) ($_POST['description'] ?? ''));
$amt      = trim((string) ($_POST['amount'] ?? ''));
$date     = trim((string) ($_POST['due_date'] ?? ''));

if ($creditor === '' || $desc === '' || $amt === '' || $date === '') {
    header(
        'Location: liabilities.php?status=error&message='
        . rawurlencode('Complete the creditor, description, due date and amount before saving.')
    );
    exit;
}

if (!is_numeric($amt) || (float) $amt < 0) {
    header(
        'Location: liabilities.php?status=error&message='
        . rawurlencode('Enter the amount as a number, for example 1250.00.')
    );
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header(
        'Location: liabilities.php?status=error&message='
        . rawurlencode('Choose a valid due date for the liability.')
    );
    exit;
}

$amount = round((float) $amt, 2);

$stmt = $conn->prepare(
    "INSERT INTO liabilities (creditor, description, amount, due_date) VALUES (?, ?, ?, ?)"
);

$stmt->bind_param("ssds", $creditor, $desc, $amount, $date);

if ($stmt->execute()) {
    header("Location: liabilities.php?status=success");
    exit;
}

// Logged, never printed: database errors must not reach the browser.
error_log('Phodio: could not save a liability — ' . $conn->error);

header(
    'Location: liabilities.php?status=error&message='
    . rawurlencode('Could not save that liability. Please try again.')
);
exit;
