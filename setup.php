<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

// ── Guard: already set up? ────────────────────────────────────────────────────

$stmt = db()->prepare('SELECT value FROM settings WHERE key = :key');
$stmt->execute([':key' => 'setup_complete']);
$row = $stmt->fetch();

if ($row !== false && $row['value'] === '1') {
    respond(403, 'Setup already complete. Use scripts/reset_admin.php (CLI) to reset.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // GET — just confirm setup is available (200).
    echo json_encode(['message' => 'Setup is available. POST to create the first admin.']);
    return;
}

// ── Validate input ────────────────────────────────────────────────────────────

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

$errors = [];

if (!preg_match('/^[a-z0-9_]{3,30}$/i', $username)) {
    $errors['username'] = 'Username must be 3–30 alphanumeric characters or underscores.';
}

if (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}

if ($errors !== []) {
    respond(422, ['errors' => $errors]);
}

// ── Create admin user ─────────────────────────────────────────────────────────

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

db()->prepare(
    'INSERT INTO users (username, name, email, password_hash, timezone, status, is_admin)
     VALUES (:username, :name, :email, :password_hash, :timezone, :status, :is_admin)'
)->execute([
    ':username'      => $username,
    ':name'          => $username,
    ':email'         => $username . '@localhost',
    ':password_hash' => $passwordHash,
    ':timezone'      => 'UTC',
    ':status'        => 'validated',
    ':is_admin'      => 1,
]);

// ── Mark setup complete ───────────────────────────────────────────────────────

db()->prepare(
    'INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)'
)->execute([':key' => 'setup_complete', ':value' => '1']);

// ── Respond ───────────────────────────────────────────────────────────────────

http_response_code(201);
echo json_encode(['message' => 'Admin account created. Visit /admin/ to log in.']);
