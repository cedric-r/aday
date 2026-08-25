<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

Auth::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// -- GET --------------------------------------------------------------------

if ($method === 'GET') {
    $stmt = db()->query(
        'SELECT p.id, u.username, u.name, p.filename, p.description, p.posted_at, p.highlight, p.hidden
         FROM photos p
         JOIN users u ON u.id = p.user_id
         ORDER BY p.posted_at DESC'
    );
    $photos = $stmt->fetchAll();

    // Decorate with thumbnail URL (admin table shows small thumbs).
    $uploadsBase = dirname(__DIR__, 2) . '/uploads';
    $photos = array_map(static function (array $p) use ($uploadsBase): array {
        $p['thumb_url'] = is_file($uploadsBase . '/' . $p['username'] . '/thumbs/' . $p['filename'])
            ? "/uploads/{$p['username']}/thumbs/{$p['filename']}"
            : null;
        return $p;
    }, $photos);

    echo json_encode($photos);
    return;
}

// -- POST {id, highlight|hidden} — toggle admin flags -----------------------

if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw  = (string) file_get_contents('php://input');
        $data = (array) (json_decode($raw, true) ?? []);
    } else {
        $data = $_POST;
    }

    $id        = (int) ($data['id'] ?? 0);
    $bool      = static function (mixed $v): ?bool {
        if ($v === true || $v === 1 || $v === '1') return true;
        if ($v === false || $v === 0 || $v === '0') return false;
        return null; // anything else (e.g. the string "false") is invalid
    };
    $highlightRaw = array_key_exists('highlight', $data) ? $bool($data['highlight']) : null;
    $hiddenRaw    = array_key_exists('hidden', $data)    ? $bool($data['hidden'])    : null;

    if ($id <= 0 || ($highlightRaw === null && $hiddenRaw === null)) {
        respond(400, 'id and highlight/hidden are required (booleans only).');
    }

    $highlight = $highlightRaw === null ? -1 : (int) $highlightRaw;
    $hidden    = $hiddenRaw    === null ? -1 : (int) $hiddenRaw;

    $sets  = [];
    $bind  = [':id' => $id];
    $reply = ['message' => 'Photo updated.', 'id' => $id];
    if ($highlight >= 0) {
        $sets[] = 'highlight = :highlight';
        $bind[':highlight'] = $highlight;
        $reply['highlight'] = $highlight === 1;
    }
    if ($hidden >= 0) {
        $sets[] = 'hidden = :hidden';
        $bind[':hidden'] = $hidden;
        $reply['hidden'] = $hidden === 1;
    }

    $stmt = db()->prepare(
        'UPDATE photos SET ' . implode(', ', $sets) . ' WHERE id = :id'
    );
    $stmt->execute($bind);

    if ($stmt->rowCount() === 0) {
        respond(404, 'Photo not found.');
    }

    echo json_encode($reply);
    return;
}

// -- DELETE ?id=N -----------------------------------------------------------

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        respond(400, 'id is required.');
    }

    $stmt = db()->prepare(
        'SELECT p.filename, u.username
         FROM photos p
         JOIN users u ON u.id = p.user_id
         WHERE p.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $photo = $stmt->fetch();

    if ($photo === false) {
        respond(404, 'Photo not found.');
    }

    $filePath = dirname(__DIR__, 2) . "/uploads/{$photo['username']}/{$photo['filename']}";
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
    // Also remove the thumbnail (best-effort; audit MINOR 5).
    $thumbPath = dirname(__DIR__, 2) . "/uploads/{$photo['username']}/thumbs/{$photo['filename']}";
    if (file_exists($thumbPath)) {
        @unlink($thumbPath);
    }

    db()->prepare('DELETE FROM photos WHERE id = :id')->execute([':id' => $id]);

    echo json_encode(['message' => 'Submission deleted.']);
    return;
}

respond(405, 'Method not allowed.');
