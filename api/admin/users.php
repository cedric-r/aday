<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

$admin  = Auth::requireAdmin();
$method = $_SERVER['REQUEST_METHOD'];

// ── Parse JSON or form body ───────────────────────────────────────────────────

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $raw  = (string) file_get_contents('php://input');
    $data = (array) (json_decode($raw, true) ?? []);
} else {
    $data = $_POST;
}

/** @param string $key */
$get = static fn (string $key): string => trim((string) ($data[$key] ?? ''));

// ── GET — list all users ──────────────────────────────────────────────────────

if ($method === 'GET') {
    $stmt = db()->query(
        'SELECT id, username, name, email, timezone, status, is_admin, created_at
         FROM users ORDER BY name ASC'
    );
    echo json_encode($stmt->fetchAll());
    return;
}

// ── POST — create user ────────────────────────────────────────────────────────

if ($method === 'POST') {
    $username    = $get('username');
    $name        = $get('name');
    $substackUrl = $get('substack_url');
    $email       = $get('email');
    $password    = $get('password');
    $timezone    = $get('timezone') ?: 'UTC';
    $isAdmin     = (int) ($data['is_admin'] ?? 0);

    $errors = [];

    if ($username === '' || !preg_match('/^[a-z0-9_]{3,30}$/i', $username)) {
        $errors['username'] = 'Username must be 3–30 alphanumeric characters or underscores.';
    }
    if ($name === '') {
        $errors['name'] = 'Name is required.';
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Valid email required.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if ($errors !== []) {
        respond(422, ['errors' => $errors]);
    }

    db()->prepare(
        'INSERT INTO users (username, name, substack_url, email, password_hash, timezone, status, is_admin)
         VALUES (:username, :name, :substack_url, :email, :password_hash, :timezone, :status, :is_admin)'
    )->execute([
        ':username'      => $username,
        ':name'          => $name,
        ':substack_url'  => $substackUrl !== '' ? $substackUrl : null,
        ':email'         => $email,
        ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ':timezone'      => $timezone,
        ':status'        => 'validated',
        ':is_admin'      => $isAdmin,
    ]);

    http_response_code(201);
    echo json_encode(['message' => 'User created.']);
    return;
}

// ── PUT — update user ─────────────────────────────────────────────────────────

if ($method === 'PUT') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        respond(400, 'id is required.');
    }

    $name     = $get('name');
    $email    = $get('email');
    $timezone = $get('timezone');
    $status   = $get('status');
    $isAdmin  = isset($data['is_admin']) ? (int) $data['is_admin'] : null;

    // Block self-demotion of admin flag.
    if ($id === (int) $admin['id'] && $isAdmin === 0) {
        respond(400, 'Cannot remove your own admin flag.');
    }

    $fields  = [];
    $params  = [':id' => $id];

    if ($name !== '') {
        $fields[]        = 'name = :name';
        $params[':name'] = $name;
    }
    if ($email !== '') {
        $fields[]         = 'email = :email';
        $params[':email'] = $email;
    }
    if ($timezone !== '') {
        $fields[]            = 'timezone = :timezone';
        $params[':timezone'] = $timezone;
    }
    if ($status !== '') {
        $fields[]           = 'status = :status';
        $params[':status']  = $status;
    }
    if ($isAdmin !== null) {
        $fields[]            = 'is_admin = :is_admin';
        $params[':is_admin'] = $isAdmin;
    }

    if ($fields === []) {
        respond(400, 'No fields to update.');
    }

    db()->prepare(
        'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id'
    )->execute($params);

    echo json_encode(['message' => 'User updated.']);
    return;
}

// ── DELETE — remove user ──────────────────────────────────────────────────────

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        respond(400, 'id is required.');
    }

    // Block self-delete.
    if ($id === (int) $admin['id']) {
        respond(400, 'Cannot delete your own account.');
    }

    // Block deleting last admin.
    $stmt = db()->query('SELECT COUNT(*) as cnt FROM users WHERE is_admin = 1');
    $countRow = $stmt->fetch();
    $adminCount = (int) ($countRow ? $countRow['cnt'] : 0);

    $targetStmt = db()->prepare('SELECT is_admin FROM users WHERE id = :id');
    $targetStmt->execute([':id' => $id]);
    $target = $targetStmt->fetch();

    if ($target !== false && (int) $target['is_admin'] === 1 && $adminCount <= 1) {
        respond(400, 'Cannot delete the last admin account.');
    }

    db()->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
    echo json_encode(['message' => 'User deleted.']);
    return;
}

respond(405, 'Method not allowed.');
