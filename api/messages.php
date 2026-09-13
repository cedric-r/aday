<?php

declare(strict_types=1);

/**
 * Notifications feed — messages an admin has sent to the participants.
 *
 * Read-only; requires a validated session. Visible to every validated user
 * (participants and admins alike).
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

Auth::requireValidated();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, 'Method not allowed.');
}

$stmt = db()->query(
    'SELECT id, subject, body, created_at
     FROM messages
     ORDER BY created_at DESC, id DESC
     LIMIT 200'
);

echo json_encode(['messages' => $stmt->fetchAll()]);
