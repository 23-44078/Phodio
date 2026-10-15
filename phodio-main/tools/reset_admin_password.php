<?php
/**
 * CLI tool: create or reset an admin account password.
 *
 * This file lives outside api/, so Vercel never exposes it over HTTP and the
 * PHP runtime never executes it. Run it locally (or with any PHP 8 install)
 * while pointing DATABASE_URL at your Supabase database:
 *
 *   DATABASE_URL="postgresql://..." php tools/reset_admin_password.php 'NewPassword' soulprint
 *
 * Usage: php tools/reset_admin_password.php <new-password> [username]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

$password = $argv[1] ?? '';
$username = $argv[2] ?? 'soulprint';

if ($password === '') {
    fwrite(
        STDERR,
        "Usage: php tools/reset_admin_password.php <new-password> [username]\n"
    );

    exit(1);
}

require_once __DIR__ . '/../api/config/database.php';

$hash = password_hash($password, PASSWORD_DEFAULT);

$lookup = $conn->prepare('SELECT id FROM admin WHERE username = ?');
$lookup->bind_param('s', $username);
$lookup->execute();

$existing = $lookup->get_result()->fetch_assoc();

if ($existing) {
    $update = $conn->prepare('UPDATE admin SET password = ? WHERE username = ?');
    $update->bind_param('ss', $hash, $username);
    $update->execute();

    echo "Updated the password for admin user '{$username}'.\n";
} else {
    $insert = $conn->prepare('INSERT INTO admin (username, password) VALUES (?, ?)');
    $insert->bind_param('ss', $username, $hash);
    $insert->execute();

    echo "Created admin user '{$username}'.\n";
}
