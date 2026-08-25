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
        'SELECT id, username, name, substack_url, email, timezone, status, is_admin, created_at
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
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        $errors['timezone'] = 'Valid IANA timezone required.';
    }
    if ($substackUrl !== '' && !Validate::httpUrl($substackUrl)) {
        $errors['substack_url'] = 'Substack URL must be a valid http(s) link.';
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

    $name        = $get('name');
    $email       = $get('email');
    $timezone    = $get('timezone');
    $status      = $get('status');
    $isAdmin     = isset($data['is_admin']) ? (int) $data['is_admin'] : null;
    $password    = isset($data['password']) ? (string) $data['password'] : null;
    $substackRaw = array_key_exists('substack_url', $data) ? $data['substack_url'] : null;

    // Block self-demotion of admin flag.
    if ($id === (int) $admin['id'] && $isAdmin === 0) {
        respond(400, 'Cannot remove your own admin flag.');
    }

    // Validate optional password reset.
    if ($password !== null && $password !== '') {
        if (strlen($password) < 8) {
            respond(422, ['errors' => ['password' => 'Password must be at least 8 characters.']]);
        }
    }

    // Validate timezone + status when present (admin-created users can carry
    // typos that later crash WindowCheck; status is a closed set).
    if ($timezone !== '' && !in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        respond(422, ['errors' => ['timezone' => 'Valid IANA timezone required.']]);
    }
    if ($status !== '' && !in_array($status, ['pending', 'validated', 'disabled'], true)) {
        respond(422, ['errors' => ['status' => 'Status must be pending, validated or disabled.']]);
    }
    // substack_url: only http(s) — blocks javascript:/data: link injection.
    if ($substackRaw !== null && $substackRaw !== '' && !Validate::httpUrl((string) $substackRaw)) {
        respond(422, ['errors' => ['substack_url' => 'Substack URL must be a valid http(s) link.']]);
    }

    $fields = [];
    $params = [':id' => $id];

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
        $fields[]          = 'status = :status';
        $params[':status'] = $status;
    }
    if ($isAdmin !== null) {
        $fields[]            = 'is_admin = :is_admin';
        $params[':is_admin'] = $isAdmin;
    }
    // substack_url: present in payload (even as empty string) → update; absent → skip.
    if ($substackRaw !== null) {
        $fields[]                 = 'substack_url = :substack_url';
        $params[':substack_url']  = ($substackRaw === '') ? null : (string) $substackRaw;
    }
    // password: non-empty string already validated above.
    if ($password !== null && $password !== '') {
        $fields[]                   = 'password_hash = :password_hash';
        $params[':password_hash']   = password_hash($password, PASSWORD_BCRYPT);
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

    // Fetch the username (needed to remove their uploads dir too).
    $targetStmt = db()->prepare('SELECT username, is_admin FROM users WHERE id = :id');
    $targetStmt->execute([':id' => $id]);
    $target = $targetStmt->fetch();

    // Block deleting the last admin (checked before self-delete so this guard is reachable
    // when there is exactly 1 admin and they try to delete themselves).
    if ($target !== false && (int) $target['is_admin'] === 1) {
        $adminCount = (int) db()->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
        if ($adminCount <= 1) {
            respond(400, 'Cannot delete the last admin account.');
        }
    }

    // Block self-delete.
    if ($id === (int) $admin['id']) {
        respond(400, 'Cannot delete your own account.');
    }

    db()->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);

    // Remove the user's upload directory (originals + thumbs) so deleted
    // participants' photos are not left reachable by URL (audit m9).
    if ($target !== false) {
        $dir = dirname(__DIR__, 2) . '/uploads/' . $target['username'];
        if (is_dir($dir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($dir);
        }
    }

    echo json_encode(['message' => 'User deleted.']);
    return;
}

respond(405, 'Method not allowed.');
