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
        'SELECT p.id, u.username, u.name, p.filename, p.description, p.posted_at
         FROM photos p
         JOIN users u ON u.id = p.user_id
         ORDER BY p.posted_at DESC'
    );
    echo json_encode($stmt->fetchAll());
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
