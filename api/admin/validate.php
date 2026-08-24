<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, 'Method not allowed.');
}

$id    = (int) ($_GET['id'] ?? 0);
$token = (string) ($_GET['token'] ?? '');

if ($id <= 0 || $token === '') {
    respond(400, 'id and token are required.');
}

// Load the user.
$stmt = db()->prepare('SELECT id, username, status FROM users WHERE id = :id');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if ($user === false) {
    respond(401, 'Invalid validation link.');
}

// Verify HMAC with constant-time comparison.
$expected = hash_hmac('sha256', (string) $id, (string) env('APP_SECRET', ''));
if (!hash_equals($expected, $token)) {
    respond(401, 'Invalid validation link.');
}

// Set status to validated.
db()->prepare('UPDATE users SET status = :status WHERE id = :id')
    ->execute([':status' => 'validated', ':id' => $id]);

// In production this would redirect; in test mode return 200 JSON.
if (env('APP_ENV') === 'testing') {
    echo json_encode(['message' => 'User validated.', 'username' => $user['username']]);
} else {
    header('Location: /admin/?validated=1');
    http_response_code(302);
}
