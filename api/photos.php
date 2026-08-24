<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// ── POST — upload a photo ─────────────────────────────────────────────────────

if ($method === 'POST') {
    $user = Auth::requireValidated();

    // Load event date.
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->execute([':key' => 'event_date']);
    $row       = $stmt->fetch();
    $eventDate = $row !== false ? (string) $row['value'] : null;

    if ($eventDate === null) {
        respond(503, 'Event not configured.');
    }

    if (!WindowCheck::isPostingOpen((string) $user['timezone'], $eventDate)) {
        respond(403, 'Posting window closed.');
    }

    try {
        FileUpload::validate($_FILES['photo'] ?? []);
    } catch (UploadException $e) {
        respond(422, $e->getMessage());
    }

    try {
        $filename = FileUpload::save($_FILES['photo'] ?? [], (string) $user['username']);
    } catch (UploadException $e) {
        respond(500, 'Failed to save file: ' . $e->getMessage());
    }

    $description = trim((string) ($_POST['description'] ?? ''));

    db()->prepare(
        'INSERT INTO photos (user_id, filename, description) VALUES (:user_id, :filename, :description)'
    )->execute([
        ':user_id'     => (int) $user['id'],
        ':filename'    => $filename,
        ':description' => $description,
    ]);

    $id = (int) db()->lastInsertId();

    $photoRow = db()->prepare('SELECT posted_at FROM photos WHERE id = :id');
    $photoRow->execute([':id' => $id]);
    $row = $photoRow->fetch();

    http_response_code(201);
    echo json_encode([
        'id'        => $id,
        'filename'  => $filename,
        'posted_at' => $row ? $row['posted_at'] : date('Y-m-d H:i:s'),
    ]);
    return;
}

// ── GET — cursor-paginated feed ───────────────────────────────────────────────

if ($method === 'GET') {
    $limit  = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
    $before = isset($_GET['before']) ? trim($_GET['before']) : null;
    $after  = isset($_GET['after'])  ? trim($_GET['after'])  : null;

    $baseSelect = '
        SELECT p.id, u.username, u.name, u.substack_url,
               p.filename, p.description, p.posted_at
        FROM photos p
        JOIN users u ON u.id = p.user_id
    ';

    if ($after !== null && $after !== '') {
        // Polling variant: photos NEWER than $after, ascending order, then reverse.
        $stmt = db()->prepare(
            $baseSelect .
            'WHERE p.posted_at > :after
             ORDER BY p.posted_at ASC, p.id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':after', $after);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $photos = $stmt->fetchAll();
        // Reverse so newest is first in response.
        $photos = array_reverse($photos);

        $nextCursor = count($photos) === $limit
            ? $photos[count($photos) - 1]['posted_at']
            : null;
    } elseif ($before !== null && $before !== '') {
        // Before-cursor: photos older than $before.
        $stmt = db()->prepare(
            $baseSelect .
            'WHERE p.posted_at < :before
             ORDER BY p.posted_at DESC, p.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':before', $before);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $photos = $stmt->fetchAll();

        $nextCursor = count($photos) === $limit
            ? $photos[count($photos) - 1]['posted_at']
            : null;
    } else {
        // Initial load: newest first.
        $stmt = db()->prepare(
            $baseSelect .
            'ORDER BY p.posted_at DESC, p.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $photos = $stmt->fetchAll();

        $nextCursor = count($photos) === $limit
            ? $photos[count($photos) - 1]['posted_at']
            : null;
    }

    echo json_encode([
        'photos'      => $photos,
        'next_cursor' => $nextCursor,
    ]);
    return;
}

respond(405, 'Method not allowed.');
