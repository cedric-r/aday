<?php

declare(strict_types=1);

/**
 * Admin message management — compose announcements for the participants,
 * list them, and retract (delete) them.
 *
 * Messages are delivered in-app on the Notifications tab (/notifications);
 * no email is sent from here.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

$admin  = Auth::requireAdmin();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET — all messages + recipient count ────────────────────────────────────

if ($method === 'GET') {
    $stmt = db()->query(
        'SELECT id, subject, body, created_at FROM messages ORDER BY created_at DESC, id DESC LIMIT 200'
    );

    // Recipients = validated participants (admins excluded from the count
    // shown in the composer, matching the broadcast-email audience).
    $recipients = (int) db()->query(
        "SELECT COUNT(*) FROM users WHERE status = 'validated' AND is_admin = 0"
    )->fetchColumn();

    echo json_encode([
        'messages'   => $stmt->fetchAll(),
        'recipients' => $recipients,
    ]);
    return;
}

// ── POST — create a message ─────────────────────────────────────────────────

if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw  = (string) file_get_contents('php://input');
        $data = (array) (json_decode($raw, true) ?? []);
    } else {
        $data = $_POST;
    }

    $subject = trim((string) ($data['subject'] ?? ''));
    $body    = trim((string) ($data['body'] ?? ''));

    if ($subject === '') {
        respond(422, 'subject is required.');
    }
    if (mb_strlen($subject) > 200) {
        respond(422, 'subject must be 200 characters or fewer.');
    }
    if (preg_match('/[\r\n]/', $subject)) {
        respond(422, 'subject must be a single line (no newlines).');
    }
    if ($body === '') {
        respond(422, 'body is required.');
    }
    if (mb_strlen($body) > 5000) {
        respond(422, 'body must be 5000 characters or fewer.');
    }

    db()->prepare(
        'INSERT INTO messages (subject, body, created_by) VALUES (:subject, :body, :created_by)'
    )->execute([
        ':subject'    => $subject,
        ':body'       => $body,
        ':created_by' => (int) $admin['id'],
    ]);

    $id = (int) db()->lastInsertId();
    $rows = db()->prepare('SELECT id, subject, body, created_at FROM messages WHERE id = :id');
    $rows->execute([':id' => $id]);

    http_response_code(201);
    echo json_encode(['message' => 'Message sent.', 'notification' => $rows->fetch()]);
    return;
}

// ── DELETE ?id=N — retract a message ────────────────────────────────────────

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        respond(400, 'id is required.');
    }

    $stmt = db()->prepare('DELETE FROM messages WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        respond(404, 'Message not found.');
    }

    echo json_encode(['message' => 'Message deleted.', 'id' => $id]);
    return;
}

respond(405, 'Method not allowed.');
