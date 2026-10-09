<?php

/**
 * Booking chat endpoint.
 *
 *   GET  chat.php?booking_id=123        -> the thread for that booking
 *   POST chat.php  (booking_id, body)   -> append a message
 *
 * Both return JSON. Authentication comes from the studio PHP session, the
 * client phodio_session cookie, or (when the optional PHODIO_CHAT_KEY
 * environment variable is set) a per-booking HMAC token issued by this file.
 */

require_once __DIR__ . '/includes/chat.php';

phodio_start_session();

$pdo = $conn->pdo();

if (!phodio_chat_messages_supported($pdo)) {
    phodio_json_response(
        [
            'ok' => false,
            'setup_required' => true,
            'message' => 'Chat is not set up yet. Run '
                . 'api/db/migrations/20261009_02_chat_messages.sql '
                . 'in the Supabase SQL Editor, then reload this page.',
        ],
        503
    );
}

$rawBookingId = $_REQUEST['booking_id'] ?? null;
$bookingId = filter_var($rawBookingId, FILTER_VALIDATE_INT);

if ($bookingId === false || $bookingId === null || $bookingId < 1) {
    phodio_json_response(
        [
            'ok' => false,
            'message' => 'A valid booking is required.',
        ],
        422
    );
}

$bookingId = (int) $bookingId;

if (!phodio_chat_booking_exists($pdo, $bookingId)) {
    phodio_json_response(
        [
            'ok' => false,
            'message' => 'Booking not found.',
        ],
        404
    );
}

$viewer = phodio_chat_viewer($pdo, $bookingId);

if ($viewer === null) {
    phodio_json_response(
        [
            'ok' => false,
            'message' => 'Please sign in to use chat.',
        ],
        401
    );
}

/*
 * Issued on every response. It is null unless PHODIO_CHAT_KEY is configured,
 * in which case the browser sends it back so the conversation survives a cold
 * start that lost the PHP session.
 */
$token = phodio_chat_token($bookingId, $viewer['type'], $viewer['id']);

$identity = [
    'type' => $viewer['type'],
    'id' => $viewer['id'],
    'name' => $viewer['name'],
    'token' => $token,
    'chat_sender_type' => $viewer['type'],
    'chat_sender_id' => $viewer['id'],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    phodio_chat_mark_read($pdo, $bookingId, $viewer['type']);

    phodio_json_response(
        [
            'ok' => true,
            'booking_id' => $bookingId,
            'viewer' => $identity,
            'messages' => phodio_chat_fetch($pdo, $bookingId),
            'max_body' => PHODIO_CHAT_MAX_BODY,
        ]
    );
}

$body = (string) ($_POST['body'] ?? '');

$result = phodio_chat_send($pdo, $bookingId, $viewer, $body);

if (!$result['ok']) {
    phodio_json_response(
        [
            'ok' => false,
            'message' => $result['error'] ?? 'Could not send that message.',
        ],
        422
    );
}

phodio_chat_mark_read($pdo, $bookingId, $viewer['type']);

phodio_json_response(
    [
        'ok' => true,
        'booking_id' => $bookingId,
        'viewer' => $identity,
        'message' => $result['message'],
        'messages' => phodio_chat_fetch($pdo, $bookingId),
        'max_body' => PHODIO_CHAT_MAX_BODY,
    ]
);
