<?php

/**
 * Actions behind admin/team.php.
 *
 * There is no super admin any more: every signed-in studio account can create,
 * disable and remove accounts. The only guard left is the one that keeps the
 * studio from locking itself out — you cannot disable or remove the account
 * you are currently signed in as.
 */

require_once __DIR__ . '/functions.php';

checkLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: team.php');
    exit;
}

$pdo = $conn->pdo();

function team_ok(string $status): void
{
    header('Location: team.php?status=' . rawurlencode($status));
    exit;
}

function team_fail(string $message): void
{
    header('Location: team.php?error=' . rawurlencode($message));
    exit;
}

/**
 * Load one studio account.
 *
 * is_active / full_name only exist once 20261009_01_admin_roles.sql has been
 * applied, so an unmigrated database falls back to "always active".
 */
function team_account(PDO $pdo, int $id): ?array
{
    if (phodio_admin_roles_supported($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT
                id,
                username,
                (COALESCE(is_active, TRUE))::int AS is_active
             FROM admin
             WHERE id = :id
             LIMIT 1'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT
                id,
                username,
                1 AS is_active
             FROM admin
             WHERE id = :id
             LIMIT 1'
        );
    }

    $stmt->execute(['id' => $id]);

    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    return $account === false ? null : $account;
}

function team_validate_password(string $password): void
{
    if (strlen($password) < 8 || strlen($password) > 72) {
        team_fail('Passwords must be between 8 and 72 characters.');
    }
}

$action = strtolower(trim((string) ($_POST['action'] ?? '')));

$rawId = $_POST['id'] ?? null;
$parsedId = filter_var($rawId, FILTER_VALIDATE_INT);
$id = ($parsedId === false || $parsedId === null || $parsedId < 1)
    ? null
    : (int) $parsedId;

$currentAdmin = phodio_current_admin();
$currentId = (int) ($currentAdmin['id'] ?? 0);

$accountsSupported = phodio_admin_roles_supported($pdo);

switch ($action) {
    /*
     |----------------------------------------------------------------------
     | CREATE
     |----------------------------------------------------------------------
     */
    case 'create':
        if (!$accountsSupported) {
            team_fail(
                'Run api/db/migrations/20261009_01_admin_roles.sql in Supabase '
                . 'before managing accounts.'
            );
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            team_fail(
                'Usernames must be 3-50 characters using letters, numbers, '
                . 'dots, underscores or hyphens.'
            );
        }

        if (mb_strlen($fullName) > 120) {
            team_fail('The display name must be 120 characters or fewer.');
        }

        team_validate_password($password);

        $exists = $pdo->prepare(
            'SELECT 1 FROM admin WHERE username = :username LIMIT 1'
        );

        $exists->execute(['username' => $username]);

        if ($exists->fetchColumn() !== false) {
            team_fail('That username is already taken.');
        }

        /*
         * role is left to the column default ('admin'): there is no super
         * admin any more, so every account is created equal.
         */
        $insert = $pdo->prepare(
            'INSERT INTO admin
                (username, password, full_name, is_active)
             VALUES
                (:username, :password, :full_name, TRUE)'
        );

        $insert->execute([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => $fullName !== '' ? $fullName : null,
        ]);

        team_ok('created');

    /*
     |----------------------------------------------------------------------
     | ENABLE / DISABLE
     |----------------------------------------------------------------------
     */
    case 'toggle':
        if (!$accountsSupported) {
            team_fail(
                'Run api/db/migrations/20261009_01_admin_roles.sql in Supabase '
                . 'before managing accounts.'
            );
        }

        if ($id === null) {
            team_fail('Pick a valid studio account.');
        }

        if ($id === $currentId) {
            team_fail('You cannot disable the account you are signed in as.');
        }

        $account = team_account($pdo, $id);

        if ($account === null) {
            team_fail('That studio account no longer exists.');
        }

        $isActive = (int) $account['is_active'] === 1;

        // Explicit TRUE/FALSE keeps the parameter unambiguous for PostgreSQL.
        $update = $pdo->prepare(
            $isActive
                ? 'UPDATE admin SET is_active = FALSE WHERE id = :id'
                : 'UPDATE admin SET is_active = TRUE WHERE id = :id'
        );

        $update->execute(['id' => $id]);

        team_ok($isActive ? 'disabled' : 'enabled');

    /*
     |----------------------------------------------------------------------
     | RESET PASSWORD
     |----------------------------------------------------------------------
     */
    case 'password':
        if ($id === null) {
            team_fail('Pick a valid studio account.');
        }

        $account = team_account($pdo, $id);

        if ($account === null) {
            team_fail('That studio account no longer exists.');
        }

        $password = (string) ($_POST['password'] ?? '');

        team_validate_password($password);

        $update = $pdo->prepare(
            'UPDATE admin SET password = :password WHERE id = :id'
        );

        $update->execute([
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $id,
        ]);

        team_ok('password');

    /*
     |----------------------------------------------------------------------
     | DELETE
     |----------------------------------------------------------------------
     */
    case 'delete':
        if ($id === null) {
            team_fail('Pick a valid studio account.');
        }

        if ($id === $currentId) {
            team_fail('You cannot remove the account you are signed in as.');
        }

        $account = team_account($pdo, $id);

        if ($account === null) {
            team_fail('That studio account no longer exists.');
        }

        $delete = $pdo->prepare('DELETE FROM admin WHERE id = :id');

        $delete->execute(['id' => $id]);

        team_ok('deleted');

    default:
        team_fail('That action is not recognised.');
}
