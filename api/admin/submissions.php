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
        'SELECT p.id, u.username, u.name, p.filename, p.description, p.posted_at, p.highlight
         FROM photos p
         JOIN users u ON u.id = p.user_id
         ORDER BY p.posted_at DESC'
    );
    echo json_encode($stmt->fetchAll());
    return;
}

// -- POST {id, highlight} — toggle the home-page highlight flag ------------

if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw  = (string) file_get_contents('php://input');
        $data = (array) (json_decode($raw, true) ?? []);
    } else {
        $data = $_POST;
    }

    $id       = (int) ($data['id'] ?? 0);
    $highlight = isset($data['highlight']) ? (int) (bool) $data['highlight'] : -1;

    if ($id <= 0 || $highlight < 0) {
        respond(400, 'id and highlight are required.');
    }

    $stmt = db()->prepare(
        'UPDATE photos SET highlight = :highlight WHERE id = :id'
    );
    $stmt->execute([':highlight' => $highlight, ':id' => $id]);

    if ($stmt->rowCount() === 0) {
        respond(404, 'Photo not found.');
    }

    echo json_encode(['message' => 'Highlight updated.', 'id' => $id, 'highlight' => $highlight === 1]);
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

    db()->prepare('DELETE FROM photos WHERE id = :id')->execute([':id' => $id]);

    echo json_encode(['message' => 'Submission deleted.']);
    return;
}

respond(405, 'Method not allowed.');
