<?php

/**
 * Unified authentication for Phodio.
 *
 * One credential check serves both account types:
 *
 *   - studio accounts (the `admin` table)  -> /admin/
 *   - client accounts (the `users` table)  -> /client_dashboard.php
 *
 * The login form never reveals which table a username belongs to, and both
 * failures return the same message.
 *
 * Roles only exist once 20261009_01_admin_roles.sql has been applied. Until
 * then every studio account behaves as a super admin, so the app keeps working
 * on a database that has not been migrated yet.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/booking_helpers.php';

if (!defined('PHODIO_ROLE_ADMIN')) {
    define('PHODIO_ROLE_ADMIN', 'admin');
}

if (!defined('PHODIO_ROLE_SUPER_ADMIN')) {
    define('PHODIO_ROLE_SUPER_ADMIN', 'super_admin');
}


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

function phodio_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}


/*
|--------------------------------------------------------------------------
| FEATURE DETECTION
|--------------------------------------------------------------------------
*/

/**
 * True when admin.role / admin.is_active exist in the database.
 *
 * Cached per request. The information_schema lookup is cheap and lets the app
 * run before 20261009_01_admin_roles.sql has been applied.
 */
function phodio_admin_roles_supported(PDO $pdo): bool
{
    static $supported = null;

    if ($supported !== null) {
        return $supported;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM information_schema.columns
             WHERE table_name = 'admin'
               AND column_name = 'role'
             LIMIT 1"
        );

        $stmt->execute();

        $supported = $stmt->fetchColumn() !== false;
    } catch (Throwable $error) {
        $supported = false;
    }

    return $supported;
}


/*
|--------------------------------------------------------------------------
| STUDIO ACCOUNTS
|--------------------------------------------------------------------------
*/

/**
 * Look up a studio account by username, with or without the role columns.
 */
function phodio_find_studio_account(PDO $pdo, string $username): ?array
{
    $username = trim($username);

    if ($username === '') {
        return null;
    }

    if (phodio_admin_roles_supported($pdo)) {
        $stmt = $pdo->prepare(
            "SELECT
                id,
                username,
                password,
                role,
                full_name,
                (COALESCE(is_active, TRUE))::int AS is_active
             FROM admin
             WHERE username = :username
             LIMIT 1"
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT
                id,
                username,
                password,
                'super_admin' AS role,
                NULL AS full_name,
                1 AS is_active
             FROM admin
             WHERE username = :username
             LIMIT 1"
        );
    }

    $stmt->execute([
        'username' => $username,
    ]);

    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    return $account === false ? null : $account;
}

/**
 * Sign a studio account in and populate the PHP session.
 *
 * @return array{ok: bool, error?: string, type?: string, redirect?: string}
 */
function phodio_login_studio(
    PDO $pdo,
    string $username,
    string $password
): array {
    $account = phodio_find_studio_account($pdo, $username);

    if ($account === null) {
        return [
            'ok' => false,
            'error' => 'The email or password you entered is incorrect.',
        ];
    }

    if ((int) $account['is_active'] !== 1) {
        return [
            'ok' => false,
            'error' => 'This studio account is disabled. Ask a super admin to re-enable it.',
        ];
    }

    if (!password_verify($password, (string) $account['password'])) {
        return [
            'ok' => false,
            'error' => 'The email or password you entered is incorrect.',
        ];
    }

    phodio_start_session();
    session_regenerate_id(true);

    $role = strtolower(trim((string) ($account['role'] ?? '')));

    if ($role !== PHODIO_ROLE_SUPER_ADMIN) {
        $role = PHODIO_ROLE_ADMIN;
    }

    $displayName = trim((string) ($account['full_name'] ?? ''));

    if ($displayName === '') {
        $displayName = (string) $account['username'];
    }

    $_SESSION['auth_type'] = 'admin';
    $_SESSION['admin_id'] = (int) $account['id'];
    $_SESSION['admin'] = (string) $account['username'];
    $_SESSION['admin_role'] = $role;
    $_SESSION['admin_name'] = $displayName;

    if (phodio_admin_roles_supported($pdo)) {
        try {
            $stamp = $pdo->prepare(
                'UPDATE admin SET last_login_at = NOW() WHERE id = :id'
            );

            $stamp->execute([
                'id' => (int) $account['id'],
            ]);
        } catch (Throwable $error) {
            // The audit column is optional; never block a login for it.
        }
    }

    session_write_close();

    return [
        'ok' => true,
        'type' => 'admin',
        'redirect' => 'admin/dashboard.php',
    ];
}

/**
 * The signed-in studio account, or null.
 *
 * @return array{id: int, username: string, role: string, name: string}|null
 */
function phodio_current_admin(): ?array
{
    phodio_start_session();

    if (empty($_SESSION['admin'])) {
        return null;
    }

    $role = strtolower((string) ($_SESSION['admin_role'] ?? PHODIO_ROLE_ADMIN));

    if ($role !== PHODIO_ROLE_SUPER_ADMIN) {
        $role = PHODIO_ROLE_ADMIN;
    }

    $username = (string) $_SESSION['admin'];

    return [
        'id' => (int) ($_SESSION['admin_id'] ?? 0),
        'username' => $username,
        'role' => $role,
        'name' => (string) ($_SESSION['admin_name'] ?? $username),
    ];
}

function phodio_is_super_admin(): bool
{
    $admin = phodio_current_admin();

    return $admin !== null && $admin['role'] === PHODIO_ROLE_SUPER_ADMIN;
}


/*
|--------------------------------------------------------------------------
| CLIENT ACCOUNTS
|--------------------------------------------------------------------------
*/

function phodio_client_exists(PDO $pdo, string $username): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM users WHERE username = :username LIMIT 1'
    );

    $stmt->execute([
        'username' => trim($username),
    ]);

    return $stmt->fetchColumn() !== false;
}

/**
 * Sign a client in.
 *
 * The authoritative credential is the row in phodio_sessions plus the
 * phodio_session cookie: Vercel's PHP file sessions are not guaranteed to
 * survive between serverless requests, but the database row always does.
 *
 * @return array{ok: bool, error?: string, type?: string, redirect?: string}
 */
function phodio_login_client(
    PDO $pdo,
    string $username,
    string $password
): array {
    $username = trim($username);

    if ($username === '' || $password === '') {
        return [
            'ok' => false,
            'error' => 'Please enter your email and password.',
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT
            id,
            firstname,
            lastname,
            username,
            password
         FROM users
         WHERE username = :username
         LIMIT 1"
    );

    $stmt->execute([
        'username' => $username,
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $invalid = [
        'ok' => false,
        'error' => 'The email or password you entered is incorrect.',
    ];

    if (!$user) {
        return $invalid;
    }

    if (!password_verify($password, (string) $user['password'])) {
        return $invalid;
    }

    $clientId = (int) $user['id'];
    $clientName = trim(
        (($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''))
    );

    $sessionId = bin2hex(random_bytes(32));

    try {
        $pdo->beginTransaction();

        // One active session per client keeps the credential predictable.
        $clear = $pdo->prepare(
            'DELETE FROM phodio_sessions WHERE client_id = :client_id'
        );

        $clear->execute([
            'client_id' => $clientId,
        ]);

        $insert = $pdo->prepare(
            "INSERT INTO phodio_sessions
                (
                    session_id,
                    client_id,
                    client_username,
                    client_name,
                    created_at,
                    expires_at
                )
             VALUES
                (
                    :session_id,
                    :client_id,
                    :client_username,
                    :client_name,
                    NOW(),
                    NOW() + INTERVAL '7 days'
                )"
        );

        $insert->execute([
            'session_id' => $sessionId,
            'client_id' => $clientId,
            'client_username' => (string) $user['username'],
            'client_name' => $clientName,
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'ok' => false,
            'error' => 'Could not start your session. Please try again.',
        ];
    }

    phodio_start_session();
    session_regenerate_id(true);

    $_SESSION['auth_type'] = 'client';
    $_SESSION['client_id'] = $clientId;
    $_SESSION['client'] = (string) $user['username'];
    $_SESSION['client_username'] = (string) $user['username'];
    $_SESSION['client_name'] = $clientName;

    setcookie(
        'phodio_session',
        $sessionId,
        [
            'expires' => time() + (7 * 24 * 60 * 60),
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );

    // Make the cookie visible to the rest of this request too.
    $_COOKIE['phodio_session'] = $sessionId;

    session_write_close();

    return [
        'ok' => true,
        'type' => 'client',
        'redirect' => 'client_dashboard.php',
    ];
}

/**
 * Restore the client identity from the phodio_session cookie when the PHP
 * session was lost (cold Vercel instance).
 */
function phodio_restore_client_session(PDO $pdo): bool
{
    phodio_start_session();

    if (!empty($_SESSION['client_id'])) {
        return true;
    }

    $sessionId = (string) ($_COOKIE['phodio_session'] ?? '');

    if ($sessionId === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT
            client_id,
            client_username,
            client_name
         FROM phodio_sessions
         WHERE session_id = :session_id
           AND expires_at > NOW()
         LIMIT 1"
    );

    $stmt->execute([
        'session_id' => $sessionId,
    ]);

    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        return false;
    }

    $_SESSION['auth_type'] = 'client';
    $_SESSION['client_id'] = (int) $session['client_id'];
    $_SESSION['client_username'] = (string) ($session['client_username'] ?? '');
    $_SESSION['client_name'] = (string) ($session['client_name'] ?? '');

    return true;
}

/**
 * Signed-in client id, restoring the session from the database if needed.
 */
function phodio_current_client_id(PDO $pdo): ?int
{
    if (!phodio_restore_client_session($pdo)) {
        return null;
    }

    return (int) $_SESSION['client_id'];
}


/*
|--------------------------------------------------------------------------
| UNIFIED ENTRY POINT
|--------------------------------------------------------------------------
*/

/**
 * Try the studio table first, then the client table.
 *
 * @return array{ok: bool, error?: string, type?: string, redirect?: string}
 */
function phodio_unified_login(
    PDO $pdo,
    string $username,
    string $password
): array {
    $username = trim($username);

    if ($username === '' || $password === '') {
        return [
            'ok' => false,
            'error' => 'Enter your username and password to continue.',
        ];
    }

    $studio = phodio_find_studio_account($pdo, $username);

    if ($studio !== null) {
        // phodio_login_studio() re-checks the password and reports disabled
        // accounts, so it is safe to hand the credentials straight over.
        $result = phodio_login_studio($pdo, $username, $password);

        if ($result['ok'] || ($result['error'] ?? '') !== 'The email or password you entered is incorrect.') {
            return $result;
        }
    }

    if (phodio_client_exists($pdo, $username)) {
        return phodio_login_client($pdo, $username, $password);
    }

    return [
        'ok' => false,
        'error' => 'The email or password you entered is incorrect.',
    ];
}


/*
|--------------------------------------------------------------------------
| CHAT ACCESS TOKENS (optional PHODIO_CHAT_KEY)
|--------------------------------------------------------------------------
*/

/**
 * Value of the optional PHODIO_CHAT_KEY environment variable.
 *
 * When it is empty, chat relies on the PHP session / phodio_session cookie
 * only and no tokens are issued.
 */
function phodio_chat_key(): string
{
    $key = getenv('PHODIO_CHAT_KEY');

    if ($key === false || trim((string) $key) === '') {
        $key = $_ENV['PHODIO_CHAT_KEY'] ?? '';
    }

    return trim((string) $key);
}

/**
 * Per-booking HMAC token, or null when PHODIO_CHAT_KEY is not configured.
 */
function phodio_chat_token(
    int $bookingId,
    string $senderType,
    int $senderId
): ?string {
    $key = phodio_chat_key();

    if ($key === '') {
        return null;
    }

    return hash_hmac(
        'sha256',
        $senderType . ':' . $senderId . ':' . $bookingId,
        $key
    );
}

function phodio_chat_token_valid(
    int $bookingId,
    string $senderType,
    int $senderId,
    string $token
): bool {
    $expected = phodio_chat_token($bookingId, $senderType, $senderId);

    return $expected !== null
        && $token !== ''
        && hash_equals($expected, $token);
}

/**
 * Read the token from the request headers or the query/body.
 */
function phodio_request_chat_token(): string
{
    $headers = [
        'HTTP_X_PHODIO_CHAT_TOKEN',
        'HTTP_X_PHODIO_CHAT_KEY',
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            return trim((string) $_SERVER[$header]);
        }
    }

    return trim((string) ($_REQUEST['chat_token'] ?? ''));
}
