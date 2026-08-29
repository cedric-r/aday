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
    $description = mb_substr($description, 0, 2000);
    $gear        = trim((string) ($_POST['gear'] ?? ''));
    $gear        = $gear === '' ? null : mb_substr($gear, 0, 200);

    $uploadsDir = dirname(__DIR__) . "/uploads/{$user['username']}";
    $uploadPath = "{$uploadsDir}/{$filename}";

    // Reject decompression bombs: a small file can declare huge dimensions and
    // exhaust memory during GD/exif decode. Cap before we decode anything.
    $dims = @getimagesize($uploadPath);
    if ($dims !== false && ($dims[0] > 8000 || $dims[1] > 8000 || $dims[0] * $dims[1] > 40_000_000)) {
        @unlink($uploadPath);
        respond(422, 'Image dimensions exceed the 8000px / 40MP limit.');
    }

    // Capture camera metadata (digital files only; empty for scans/no-EXIF).
    $exif = Exif::extract($uploadPath);

    // Best-effort thumbnail (GD); the app falls back to the original when it fails.
    Thumbnails::generate($uploadPath, $uploadsDir, $filename);

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
    $random = isset($_GET['random']) && $_GET['random'] === '1';
    $hourRaw = isset($_GET['hour']) ? trim((string) $_GET['hour']) : '';
    $hour = ($hourRaw !== '' && preg_match('/^\d{1,2}$/', $hourRaw) && (int) $hourRaw <= 23) ? (int) $hourRaw : null;

    $baseSelect = '
            SELECT p.id, u.username, u.name, u.substack_url,
                   p.filename, p.description, p.posted_at,
                   p.highlight, p.gear,
                   p.exif_make, p.exif_model, p.exif_focal, p.exif_aperture, p.exif_shutter, p.exif_iso,
                   u.timezone
            FROM photos p
            JOIN users u ON u.id = p.user_id
        ';

        // Public feed queries never expose hidden (unlisted) photos.
        $visible = 'AND p.hidden = 0 ';
        $nextCursor = null;

        // Composite cursor "posted_at|id" so pagination/polling don't drop
        // photos that share the same posted_at second (burst uploads on event
        // day). Legacy plain-timestamp cursors still parse (id defaults 0).
        $before = $before !== null && $before !== '' ? explode('|', $before, 2) : null;
        $after  = $after  !== null && $after  !== '' ? explode('|', $after, 2)  : null;
        $beforeTime = $before ? $before[0] : null;
        $beforeId   = $before && isset($before[1]) ? (int) $before[1] : 0;
        $afterTime  = $after  ? $after[0]  : null;
        $afterId    = $after  && isset($after[1])  ? (int) $after[1]  : 0;

        if ($photographer !== '') {
            // Filter by a single (validated) photographer's username — used by
            // per-photographer embeds.
            $stmt = db()->prepare(
                $baseSelect .
                'WHERE u.username = :username AND u.status = :status ' . $visible .
                'ORDER BY p.posted_at DESC, p.id DESC
                 LIMIT 200'
            );
            $stmt->execute([':username' => $photographer, ':status' => 'validated']);
            $photos = $stmt->fetchAll();
        } elseif ($single > 0) {
            // Single photo by id (used by the lightbox deep-link when the photo
            // is not already in the loaded page). Hidden photos are treated as not found.
            $stmt = db()->prepare($baseSelect . 'WHERE p.id = :id ' . $visible);
            $stmt->execute([':id' => $single]);
            $photos = $stmt->fetchAll();
        } elseif ($highlights) {
            // Admin-picked highlights for the home page strip.
            $stmt = db()->prepare(
                $baseSelect .
                'WHERE p.highlight = 1 ' . $visible .
                'ORDER BY p.posted_at DESC, p.id DESC
                 LIMIT 50'
            );
            $stmt->execute();
            $photos = $stmt->fetchAll();
        } elseif ($random) {
            // One random public photo ("Surprise me").
            $stmt = db()->prepare(
                $baseSelect . 'WHERE 1=1 ' . $visible . 'ORDER BY RANDOM() LIMIT 1'
            );
            $stmt->execute();
            $photos = $stmt->fetchAll();
        } elseif ($hour !== null) {
            // "Follow the sun": photos taken during local hour H in *each*
            // photographer's own timezone, ordered eastmost-first so the feed
            // reads as the sun's progression across the planet. Hour matching
            // happens in PHP because SQLite has no IANA tz support; capped at
            // 1000 recent photos for memory safety.
            $stmt = db()->prepare(
                $baseSelect . 'WHERE 1=1 ' . $visible .
                'ORDER BY p.posted_at DESC, p.id DESC LIMIT 1000'
            );
            $stmt->execute();
            $utc = new DateTimeZone('UTC');
            $matched = [];
            foreach ($stmt->fetchAll() as $row) {
                try {
                    $tz = new DateTimeZone((string) ($row['timezone'] ?: 'UTC'));
                } catch (Exception) {
                    $tz = $utc;
                }
                $dt = new DateTime((string) $row['posted_at'], $utc);
                $dt->setTimezone($tz);
                if ((int) $dt->format('G') === $hour) {
                    $row['_offset']    = (int) $dt->format('Z'); // seconds east of UTC
                    $row['local_time'] = $dt->format('H:i');
                    $matched[] = $row;
                }
            }
            usort($matched, static fn (array $a, array $b): int =>
                [$b['_offset'], $b['posted_at'], $b['id']] <=> [$a['_offset'], $a['posted_at'], $a['id']]
            );
            $photos = array_slice($matched, 0, $limit);
            foreach ($photos as &$p) { unset($p['_offset'], $p['timezone']); }
            unset($p);
        } elseif ($after !== null) {
        // Polling variant: photos NEWER than the cursor, ascending, then reverse.
        $stmt = db()->prepare(
            $baseSelect .
            'WHERE (p.posted_at > :t OR (p.posted_at = :t AND p.id > :id)) ' . $visible .
            'ORDER BY p.posted_at ASC, p.id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':t', $afterTime);
        $stmt->bindValue(':id', $afterId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $photos = $stmt->fetchAll();
        // Reverse so newest is first in response.
        $photos = array_reverse($photos);

        $nextCursor = null;
    } elseif ($before !== null) {
        // Before-cursor: photos older than the cursor.
        $stmt = db()->prepare(
            $baseSelect .
            'WHERE (p.posted_at < :t OR (p.posted_at = :t AND p.id < :id)) ' . $visible .
            'ORDER BY p.posted_at DESC, p.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':t', $beforeTime);
        $stmt->bindValue(':id', $beforeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $photos = $stmt->fetchAll();

        $nextCursor = count($photos) === $limit
            ? $photos[count($photos) - 1]['posted_at'] . '|' . $photos[count($photos) - 1]['id']
            : null;
    } else {
        // Initial load: newest first.
        $stmt = db()->prepare(
            $baseSelect .
            'WHERE 1=1 ' . $visible .
            'ORDER BY p.posted_at DESC, p.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $photos = $stmt->fetchAll();

        $nextCursor = count($photos) === $limit
            ? $photos[count($photos) - 1]['posted_at'] . '|' . $photos[count($photos) - 1]['id']
            : null;
    }

    // Decorate with thumbnail URL (best-effort; null when no thumbs dir yet).
    // u.timezone is query-side plumbing for the hour filter — never expose it.
    $uploadsBase = dirname(__DIR__) . '/uploads';
    $photos = array_map(static function (array $p) use ($uploadsBase): array {
        unset($p['timezone']);
        $p['thumb_url'] = is_file($uploadsBase . '/' . $p['username'] . '/thumbs/' . $p['filename'])
            ? "/uploads/{$p['username']}/thumbs/{$p['filename']}"
            : null;
        return $p;
    }, $photos);

    echo json_encode([
        'photos'      => $photos,
        'next_cursor' => $nextCursor,
    ]);
    return;
}

// ── DELETE ?id=N — participant removes their own photo ──────────────────────

if ($method === 'DELETE') {
    $user = Auth::requireValidated();
    $id   = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        respond(400, 'id is required.');
    }

    // Ownership is enforced: a participant may only delete their own photos.
    $stmt = db()->prepare(
        'SELECT p.filename, p.user_id
         FROM photos p
         WHERE p.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $photo = $stmt->fetch();

    if ($photo === false || (int) $photo['user_id'] !== (int) $user['id']) {
        respond(404, 'Photo not found.');
    }

    // Remove the file, thumbnail, and DB row. Hidden photos are treated the
    // same as visible ones — ownership alone governs deletion.
    $filePath  = dirname(__DIR__) . "/uploads/{$user['username']}/{$photo['filename']}";
    $thumbPath = dirname(__DIR__) . "/uploads/{$user['username']}/thumbs/{$photo['filename']}";
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
    if (file_exists($thumbPath)) {
        @unlink($thumbPath);
    }

    db()->prepare('DELETE FROM photos WHERE id = :id')->execute([':id' => $id]);

    echo json_encode(['message' => 'Photo deleted.', 'id' => $id]);
    return;
}

respond(405, 'Method not allowed.');
