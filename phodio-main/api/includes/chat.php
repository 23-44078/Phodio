<?php

/**
 * Booking chat repository.
 *
 * Every message belongs to exactly one booking. A client may only read and
 * write threads on their own bookings; a studio account may read and write
 * every thread. That rule is enforced here, not in SQL, because the PHP
 * application owns authentication.
 */

require_once __DIR__ . '/auth.php';

const PHODIO_CHAT_MAX_BODY = 2000;
const PHODIO_CHAT_MAX_MESSAGES = 300;


/*
|--------------------------------------------------------------------------
| FEATURE DETECTION
|--------------------------------------------------------------------------
*/

/**
 * True once 20261009_02_chat_messages.sql has been applied.
 */
function phodio_chat_messages_supported(PDO $pdo): bool
{
    static $supported = null;

    if ($supported !== null) {
        return $supported;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM information_schema.tables
             WHERE table_name = 'chat_messages'
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
| OWNERSHIP
|--------------------------------------------------------------------------
*/

function phodio_chat_booking_belongs_to_client(
    PDO $pdo,
    int $bookingId,
    int $clientId
): bool {
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM bookings
         WHERE id = :booking_id
           AND client_id = :client_id
         LIMIT 1'
    );

    $stmt->execute([
        'booking_id' => $bookingId,
        'client_id' => $clientId,
    ]);

    return $stmt->fetchColumn() !== false;
}

function phodio_chat_booking_exists(PDO $pdo, int $bookingId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM bookings WHERE id = :booking_id LIMIT 1'
    );

    $stmt->execute([
        'booking_id' => $bookingId,
    ]);

    return $stmt->fetchColumn() !== false;
}


/*
|--------------------------------------------------------------------------
| VIEWER
|--------------------------------------------------------------------------
*/

/**
 * Resolve who is asking for the thread.
 *
 * Three ways in, in order:
 *   1. a studio PHP session,
 *   2. a client PHP session (restored from the phodio_session cookie),
 *   3. a per-booking HMAC token, which only exists when the optional
 *      PHODIO_CHAT_KEY environment variable is set. The token is what lets a
 *      conversation survive a Vercel cold start that dropped the PHP session.
 *
 * @return array{type: string, id: int, name: string}|null
 */
function phodio_chat_viewer(PDO $pdo, int $bookingId): ?array
{
    // 1. Studio session.
    $admin = phodio_current_admin();

    if ($admin !== null) {
        return [
            'type' => 'admin',
            'id' => $admin['id'],
            'name' => $admin['name'],
        ];
    }

    // 2. Client session (database-backed cookie).
    $clientId = phodio_current_client_id($pdo);

    if ($clientId !== null) {
        if (!phodio_chat_booking_belongs_to_client($pdo, $bookingId, $clientId)) {
            return null;
        }

        return [
            'type' => 'client',
            'id' => $clientId,
            'name' => (string) ($_SESSION['client_name'] ?? 'Client'),
        ];
    }

    // 3. Optional signed token (PHODIO_CHAT_KEY).
    if (phodio_chat_key() === '') {
        return null;
    }

    $token = phodio_request_chat_token();
    $type = strtolower(trim((string) ($_REQUEST['chat_sender_type'] ?? '')));
    $senderId = (int) ($_REQUEST['chat_sender_id'] ?? 0);

    if ($token === '' || $senderId < 1 || !in_array($type, ['admin', 'client'], true)) {
        return null;
    }

    if (!phodio_chat_token_valid($bookingId, $type, $senderId, $token)) {
        return null;
    }

    if ($type === 'client') {
        if (!phodio_chat_booking_belongs_to_client($pdo, $bookingId, $senderId)) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT firstname, lastname FROM users WHERE id = :id LIMIT 1'
        );

        $stmt->execute(['id' => $senderId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'type' => 'client',
            'id' => $senderId,
            'name' => $user
                ? trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')) ?: 'Client'
                : 'Client',
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT username, full_name, role FROM admin WHERE id = :id LIMIT 1'
    );

    $stmt->execute(['id' => $senderId]);

    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        return null;
    }

    $displayName = trim((string) ($account['full_name'] ?? ''));

    return [
        'type' => 'admin',
        'id' => $senderId,
        'name' => $displayName !== '' ? $displayName : (string) $account['username'],
    ];
}


/*
|--------------------------------------------------------------------------
| READ
|--------------------------------------------------------------------------
*/

/**
 * @return array<int, array<string, mixed>>
 */
function phodio_chat_fetch(PDO $pdo, int $bookingId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            id,
            booking_id,
            sender_type,
            sender_id,
            sender_name,
            body,
            created_at,
            read_at
         FROM chat_messages
         WHERE booking_id = :booking_id
         ORDER BY created_at ASC, id ASC
         LIMIT ' . PHODIO_CHAT_MAX_MESSAGES
    );

    $stmt->execute([
        'booking_id' => $bookingId,
    ]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($messages as $index => $message) {
        $messages[$index]['id'] = (int) $message['id'];
        $messages[$index]['booking_id'] = (int) $message['booking_id'];
        $messages[$index]['sender_id'] = $message['sender_id'] === null
            ? null
            : (int) $message['sender_id'];
        $messages[$index]['created_at'] = phodio_chat_iso(
            (string) $message['created_at']
        );
        $messages[$index]['read_at'] = $message['read_at'] === null
            ? null
            : phodio_chat_iso((string) $message['read_at']);
    }

    return $messages;
}

/**
 * Normalise a Postgres timestamp to an ISO-8601 string for the browser.
 */
function phodio_chat_iso(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable $error) {
        return $value;
    }

    return $date->format(DateTimeInterface::ATOM);
}

/**
 * Mark everything the other side has written as read.
 */
function phodio_chat_mark_read(
    PDO $pdo,
    int $bookingId,
    string $readerType
): void {
    $other = $readerType === 'admin' ? 'client' : 'admin';

    $stmt = $pdo->prepare(
        'UPDATE chat_messages
         SET read_at = NOW()
         WHERE booking_id = :booking_id
           AND sender_type = :sender_type
           AND read_at IS NULL'
    );

    $stmt->execute([
        'booking_id' => $bookingId,
        'sender_type' => $other,
    ]);
}


/*
|--------------------------------------------------------------------------
| WRITE
|--------------------------------------------------------------------------
*/

/**
 * @return array{ok: bool, error?: string, message?: array<string, mixed>}
 */
function phodio_chat_send(
    PDO $pdo,
    int $bookingId,
    array $viewer,
    string $body
): array {
    $body = trim(preg_replace('/\r\n?/', "\n", $body) ?? '');

    if ($body === '') {
        return [
            'ok' => false,
            'error' => 'Write a message before sending.',
        ];
    }

    if (mb_strlen($body) > PHODIO_CHAT_MAX_BODY) {
        return [
            'ok' => false,
            'error' => 'Messages are limited to '
                . PHODIO_CHAT_MAX_BODY
                . ' characters.',
        ];
    }

    $senderName = trim((string) ($viewer['name'] ?? ''));

    if ($senderName === '') {
        $senderName = $viewer['type'] === 'admin' ? 'Studio' : 'Client';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO chat_messages
            (
                booking_id,
                sender_type,
                sender_id,
                sender_name,
                body,
                created_at
            )
         VALUES
            (
                :booking_id,
                :sender_type,
                :sender_id,
                :sender_name,
                :body,
                NOW()
            )
         RETURNING id, created_at'
    );

    $stmt->execute([
        'booking_id' => $bookingId,
        'sender_type' => $viewer['type'],
        'sender_id' => $viewer['id'] > 0 ? $viewer['id'] : null,
        'sender_name' => $senderName,
        'body' => $body,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'ok' => true,
        'message' => [
            'id' => (int) $row['id'],
            'booking_id' => $bookingId,
            'sender_type' => $viewer['type'],
            'sender_id' => $viewer['id'],
            'sender_name' => $senderName,
            'body' => $body,
            'created_at' => phodio_chat_iso((string) $row['created_at']),
            'read_at' => null,
            'pending' => false,
        ],
    ];
}
