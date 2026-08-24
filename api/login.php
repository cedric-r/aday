<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/session.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Method not allowed.');
}

// ── Parse input ──────────────────────────────────────────────────────────────

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $raw  = (string) file_get_contents('php://input');
    $data = (array) (json_decode($raw, true) ?? []);
} else {
    $data = $_POST;
}

$username = trim((string) ($data['username'] ?? ''));
$password = (string) ($data['password'] ?? '');

if ($username === '' || $password === '') {
    respond(400, 'Username and password are required.');
}

// ── Load user ─────────────────────────────────────────────────────────────────

$stmt = db()->prepare(
    'SELECT id, username, name, password_hash, status, is_admin
     FROM users WHERE username = :username'
);
$stmt->execute([':username' => $username]);
$user = $stmt->fetch();

if ($user === false) {
    respond(401, 'Invalid credentials.');
}

if (!password_verify($password, (string) $user['password_hash'])) {
    respond(401, 'Invalid credentials.');
}

if ($user['status'] !== 'validated') {
    respond(403, 'Account pending approval.');
}

// ── Start authenticated session ───────────────────────────────────────────────

if (env('APP_ENV') !== 'testing') {
    session_regenerate_id(true);
}
$_SESSION['user_id'] = (int) $user['id'];

echo json_encode([
    'username' => $user['username'],
    'name'     => $user['name'],
    'is_admin' => (bool) $user['is_admin'],
    'status'   => $user['status'],
]);
