<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/session.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, 'Method not allowed.');
}

$username = isset($_GET['username']) ? trim((string) $_GET['username']) : null;

// ── Single photographer ───────────────────────────────────────────────────────

if ($username !== null && $username !== '') {
    $stmt = db()->prepare(
        'SELECT id, username, name, substack_url, bio
         FROM users
         WHERE username = :username AND status = :status'
    );
    $stmt->execute([':username' => $username, ':status' => 'validated']);
    $user = $stmt->fetch();

    if ($user === false) {
        respond(404, 'Photographer not found.');
    }

    $photoStmt = db()->prepare(
        'SELECT id, filename, description, posted_at,
                highlight, gear,
                exif_make, exif_model, exif_focal, exif_aperture, exif_shutter, exif_iso
         FROM photos
         WHERE user_id = :user_id AND hidden = 0
         ORDER BY posted_at DESC, id DESC'
    );
    $photoStmt->execute([':user_id' => (int) $user['id']]);
    $photos = $photoStmt->fetchAll();

    // Decorate with thumbnail URL.
    $photos = array_map(static function (array $p) use ($user): array {
        $p['thumb_url'] = is_file(dirname(__DIR__) . "/uploads/{$user['username']}/thumbs/{$p['filename']}")
            ? "/uploads/{$user['username']}/thumbs/{$p['filename']}"
            : null;
        return $p;
    }, $photos);

    echo json_encode([
        'username'    => $user['username'],
        'name'        => $user['name'],
        'substack_url' => $user['substack_url'],
        'bio'         => $user['bio'],
        'photos'      => $photos,
    ]);
    return;
}

// ── Full index ────────────────────────────────────────────────────────────────

$stmt = db()->query(
    'SELECT u.username, u.name, u.substack_url, u.bio,
            COUNT(p.id) AS photo_count
     FROM users u
     LEFT JOIN photos p ON p.user_id = u.id AND p.hidden = 0
     WHERE u.status = \'validated\'
     GROUP BY u.id
     ORDER BY u.name COLLATE NOCASE ASC'
);

$results = $stmt->fetchAll();

// Cast photo_count to int.
$results = array_map(static function (array $row): array {
    $row['photo_count'] = (int) $row['photo_count'];
    return $row;
}, $results);

echo json_encode($results);
