<?php

/**
 * Save a new expense.
 *
 * The guard matters: this endpoint writes to the studio's books, so it must
 * only be reachable by a signed-in studio account.
 */

require_once __DIR__ . '/functions.php';

checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: expenses.php');
    exit;
}

$desc = trim((string) ($_POST['description'] ?? ''));
$amt  = trim((string) ($_POST['amount'] ?? ''));
$date = trim((string) ($_POST['expense_date'] ?? ''));

if ($desc === '' || $amt === '' || $date === '') {
    header(
        'Location: expenses.php?status=error&message='
        . rawurlencode('Complete the description, date and amount before saving.')
    );
    exit;
}

if (!is_numeric($amt) || (float) $amt < 0) {
    header(
        'Location: expenses.php?status=error&message='
        . rawurlencode('Enter the amount as a number, for example 1250.00.')
    );
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header(
        'Location: expenses.php?status=error&message='
        . rawurlencode('Choose a valid date for the expense.')
    );
    exit;
}

$amount = round((float) $amt, 2);

$stmt = $conn->prepare(
    "INSERT INTO expenses (description, amount, expense_date) VALUES (?, ?, ?)"
);

$stmt->bind_param("sds", $desc, $amount, $date);

if ($stmt->execute()) {
    header("Location: expenses.php?status=success");
    exit;
}

// The reason is logged, never printed: database errors must not reach the
// browser of whoever is looking at the page.
error_log('Phodio: could not save an expense — ' . $conn->error);

header(
    'Location: expenses.php?status=error&message='
    . rawurlencode('Could not save that expense. Please try again.')
);
exit;
