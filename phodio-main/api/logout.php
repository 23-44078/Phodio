<?php

/**
 * Client sign-out.
 *
 * Destroying the PHP session on its own is not enough: the phodio_session
 * cookie would immediately restore the identity from the database on the next
 * request. The session row and the cookie are cleared here as well.
 */

require_once __DIR__ . '/includes/auth.php';

phodio_start_session();

$pdo = $conn->pdo();

$sessionId = (string) ($_COOKIE['phodio_session'] ?? '');

if ($sessionId !== '') {
    try {
        $stmt = $pdo->prepare(
            'DELETE FROM phodio_sessions WHERE session_id = :session_id'
        );

        $stmt->execute([
            'session_id' => $sessionId,
        ]);
    } catch (Throwable $error) {
        // Clearing the cookie below still signs the client out.
    }
}

setcookie(
    'phodio_session',
    '',
    [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]
);

unset($_COOKIE['phodio_session']);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        (bool) $params['secure'],
        (bool) $params['httponly']
    );
}

session_destroy();

header('Location: /login.php?as=client');
exit;
