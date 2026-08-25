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

    // Load late-submission toggle (default off).
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->execute([':key' => 'allow_late_submissions']);
    $lateRow   = $stmt->fetch();
    $allowLate = $lateRow !== false && $lateRow['value'] === '1';

    if (!WindowCheck::isPostingOpen((string) $user['timezone'], $eventDate, allowLate: $allowLate)) {
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
    $gear        = trim((string) ($_POST['gear'] ?? ''));
    $gear        = $gear === '' ? null : mb_substr($gear, 0, 200);

    // Capture camera metadata (digital files only; empty for scans/no-EXIF).
    $exif = Exif::extract(dirname(__DIR__) . "/uploads/{$user['username']}/{$filename}");

    db()->prepare(
        'INSERT INTO photos (user_id, filename, description, gear, exif_make, exif_model, exif_focal, exif_aperture, exif_shutter, exif_iso)
         VALUES (:user_id, :filename, :description, :gear, :exif_make, :exif_model, :exif_focal, :exif_aperture, :exif_shutter, :exif_iso)'
    )->execute([
        ':user_id'      => (int) $user['id'],
        ':filename'     => $filename,
        ':description'  => $description,
        ':gear'         => $gear,
        ':exif_make'    => $exif['make'],
        ':exif_model'   => $exif['model'],
        ':exif_focal'   => $exif['focal'],
        ':exif_aperture'=> $exif['aperture'],
        ':exif_shutter' => $exif['shutter'],
        ':exif_iso'     => $exif['iso'],
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
    $single = isset($_GET['photo']) ? (int) $_GET['photo'] : 0;
    $highlights = isset($_GET['highlight']) && $_GET['highlight'] !== '0';
    $photographer = isset($_GET['photographer']) ? trim((string) $_GET['photographer']) : '';

    $baseSelect = '
        SELECT p.id, u.username, u.name, u.substack_url,
               p.filename, p.description, p.posted_at,
               p.highlight, p.gear,
               p.exif_make, p.exif_model, p.exif_focal, p.exif_aperture, p.exif_shutter, p.exif_iso
        FROM photos p
        JOIN users u ON u.id = p.user_id
    ';

    $nextCursor = null;

    if ($photographer !== '') {
        // Filter by a single (validated) photographer's username — used by
        // per-photographer embeds.
        $stmt = db()->prepare(
            $baseSelect .
            'WHERE u.username = :username AND u.status = :status
             ORDER BY p.posted_at DESC, p.id DESC
             LIMIT 200'
        );
        $stmt->execute([':username' => $photographer, ':status' => 'validated']);
        $photos = $stmt->fetchAll();
    } elseif ($single > 0) {
        // Single photo by id (used by the lightbox deep-link when the photo
        // is not already in the loaded feed page).
        $stmt = db()->prepare($baseSelect . 'WHERE p.id = :id');
        $stmt->execute([':id' => $single]);
        $photos = $stmt->fetchAll();
    } elseif ($highlights) {
        // Admin-picked highlights for the home page strip.
        $stmt = db()->prepare(
            $baseSelect .
            'WHERE p.highlight = 1
             ORDER BY p.posted_at DESC, p.id DESC
             LIMIT 50'
        );
        $stmt->execute();
        $photos = $stmt->fetchAll();
    } elseif ($after !== null && $after !== '') {
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
