<?php

/**
 * Actions behind admin/team.php.
 *
 * Every action requires a super admin, and the guards below make sure the
 * studio can never lock itself out: you cannot edit your own role or status,
 * and the last remaining super admin can neither be demoted, disabled, nor
 * removed.
 */

require_once __DIR__ . '/functions.php';

checkSuperAdmin();

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

function team_account(PDO $pdo, int $id): ?array
{
    if (phodio_admin_roles_supported($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT
                id,
                username,
                role,
                (COALESCE(is_active, TRUE))::int AS is_active
             FROM admin
             WHERE id = :id
             LIMIT 1'
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT
                id,
                username,
                'super_admin' AS role,
                1 AS is_active
             FROM admin
             WHERE id = :id
             LIMIT 1"
        );
    }

    $stmt->execute(['id' => $id]);

    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    return $account === false ? null : $account;
}

function team_super_admin_count(PDO $pdo): int
{
    if (!phodio_admin_roles_supported($pdo)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin');
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM admin WHERE role = 'super_admin'"
    );

    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function team_validate_password(string $password, bool $required = true): void
{
    if (!$required && $password === '') {
        return;
    }

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

$rolesSupported = phodio_admin_roles_supported($pdo);

switch ($action) {
    /*
     |----------------------------------------------------------------------
     | CREATE
     |----------------------------------------------------------------------
     */
    case 'create':
        if (!$rolesSupported) {
            team_fail('Run api/db/migrations/20261009_01_admin_roles.sql in Supabase before managing roles.');
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $role = strtolower(trim((string) ($_POST['role'] ?? PHODIO_ROLE_ADMIN)));

        if ($role !== PHODIO_ROLE_SUPER_ADMIN) {
            $role = PHODIO_ROLE_ADMIN;
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            team_fail('Usernames must be 3-50 characters using letters, numbers, dots, underscores or hyphens.');
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

        $insert = $pdo->prepare(
            'INSERT INTO admin
                (username, password, role, full_name, is_active)
             VALUES
                (:username, :password, :role, :full_name, TRUE)'
        );

        $insert->execute([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'full_name' => $fullName !== '' ? $fullName : null,
        ]);

        team_ok('created');

    /*
     |----------------------------------------------------------------------
     | CHANGE ROLE
     |----------------------------------------------------------------------
     */
    case 'role':
        if (!$rolesSupported) {
            team_fail('Run api/db/migrations/20261009_01_admin_roles.sql in Supabase before managing roles.');
        }

        if ($id === null) {
            team_fail('Pick a valid studio account.');
        }

        if ($id === $currentId) {
            team_fail('You cannot change your own role.');
        }

        $account = team_account($pdo, $id);

        if ($account === null) {
            team_fail('That studio account no longer exists.');
        }

        $role = strtolower(trim((string) ($_POST['role'] ?? PHODIO_ROLE_ADMIN)));

        if ($role !== PHODIO_ROLE_SUPER_ADMIN) {
            $role = PHODIO_ROLE_ADMIN;
        }

        $wasSuper = strtolower((string) $account['role']) === PHODIO_ROLE_SUPER_ADMIN;

        if ($wasSuper
            && $role !== PHODIO_ROLE_SUPER_ADMIN
            && team_super_admin_count($pdo) <= 1
        ) {
            team_fail('At least one super admin must remain.');
        }

        $update = $pdo->prepare(
            'UPDATE admin SET role = :role WHERE id = :id'
        );

        $update->execute([
            'role' => $role,
            'id' => $id,
        ]);

        team_ok('role');

    /*
     |----------------------------------------------------------------------
     | ENABLE / DISABLE
     |----------------------------------------------------------------------
     */
    case 'toggle':
        if (!$rolesSupported) {
            team_fail('Run api/db/migrations/20261009_01_admin_roles.sql in Supabase before managing roles.');
        }

        if ($id === null) {
            team_fail('Pick a valid studio account.');
        }

        if ($id === $currentId) {
            team_fail('You cannot disable your own account.');
        }

        $account = team_account($pdo, $id);

        if ($account === null) {
            team_fail('That studio account no longer exists.');
        }

        $isActive = (int) $account['is_active'] === 1;

        if ($isActive
            && strtolower((string) $account['role']) === PHODIO_ROLE_SUPER_ADMIN
            && team_super_admin_count($pdo) <= 1
        ) {
            team_fail('At least one active super admin must remain.');
        }

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
            team_fail('You cannot remove your own account.');
        }

        $account = team_account($pdo, $id);

        if ($account === null) {
            team_fail('That studio account no longer exists.');
        }

        if (strtolower((string) $account['role']) === PHODIO_ROLE_SUPER_ADMIN
            && team_super_admin_count($pdo) <= 1
        ) {
            team_fail('At least one super admin must remain.');
        }

        $delete = $pdo->prepare('DELETE FROM admin WHERE id = :id');

        $delete->execute(['id' => $id]);

        team_ok('deleted');

    default:
        team_fail('That action is not recognised.');
}
