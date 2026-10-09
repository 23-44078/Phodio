<?php

/**
 * Log (or re-log) one day of studio performance.
 *
 * The guard matters: this endpoint writes the studio's earnings, so it must
 * only be reachable by a signed-in studio account.
 */

require_once __DIR__ . '/functions.php';

checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tracker.php');
    exit;
}

$date    = trim((string) ($_POST['track_date'] ?? ''));
$income  = trim((string) ($_POST['income_today'] ?? ''));
$clients = trim((string) ($_POST['client_today'] ?? ''));
$target  = trim((string) ($_POST['target'] ?? ''));

if ($date === '' || $income === '' || $clients === '' || $target === '') {
    header(
        'Location: tracker.php?status=error&message='
        . rawurlencode('Complete the date, income, client count and target before saving.')
    );
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header(
        'Location: tracker.php?status=error&message='
        . rawurlencode('Choose a valid date for the entry.')
    );
    exit;
}

foreach (['income' => $income, 'target' => $target] as $field => $value) {
    if (!is_numeric($value) || (float) $value < 0) {
        header(
            'Location: tracker.php?status=error&message='
            . rawurlencode('Enter the ' . $field . ' as a number, for example 5000.00.')
        );
        exit;
    }
}

if (!ctype_digit($clients) || (int) $clients < 0) {
    header(
        'Location: tracker.php?status=error&message='
        . rawurlencode('Enter the client count as a whole number, for example 4.')
    );
    exit;
}

$incomeAmount = round((float) $income, 2);
$targetAmount = round((float) $target, 2);
$clientCount  = (int) $clients;

// Re-logging the same day updates that row instead of failing.
$stmt = $conn->prepare(
    "INSERT INTO daily_tracker (track_date, income_today, client_today, target)
     VALUES (?, ?, ?, ?)
     ON CONFLICT (track_date) DO UPDATE SET
        income_today = EXCLUDED.income_today,
        client_today = EXCLUDED.client_today,
        target = EXCLUDED.target"
);

/*
 * Four placeholders, four bound values.
 *
 * The previous version bound seven values ("sdiidii") against four
 * placeholders, which PDO rejects with SQLSTATE[HY093] — every tracker entry
 * silently failed to save. ON CONFLICT ... EXCLUDED re-uses the inserted
 * values, so the extra three bindings were never needed.
 */
$stmt->bind_param("sdid", $date, $incomeAmount, $clientCount, $targetAmount);

if ($stmt->execute()) {
    header("Location: tracker.php?status=success");
    exit;
}

// Logged, never printed: database errors must not reach the browser.
error_log('Phodio: could not save a tracker entry — ' . $conn->error);

header(
    'Location: tracker.php?status=error&message='
    . rawurlencode('Could not save that entry. Please try again.')
);
exit;
