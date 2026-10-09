<?php

/**
 * Entry point: send every visitor to the home page of their account type.
 *
 * The client check goes through phodio_current_client_id() on purpose. On
 * Vercel the PHP session file can disappear between serverless requests, and
 * the phodio_session cookie is what keeps a client signed in. Reading
 * $_SESSION['client_id'] directly (as this file used to do) sent clients who
 * were still signed in back to the login page after every cold start.
 */

require_once __DIR__ . '/includes/auth.php';

phodio_start_session();

$pdo = $conn->pdo();

if (phodio_current_admin() !== null) {
    header('Location: /admin/dashboard.php');
    exit;
}

if (phodio_current_client_id($pdo) !== null) {
    header('Location: /client_dashboard.php');
    exit;
}

header('Location: /login.php');
exit;
