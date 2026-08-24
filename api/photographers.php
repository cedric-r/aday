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
        'SELECT id, username, name, substack_url
         FROM users
         WHERE username = :username AND status = :status'
    );
    $stmt->execute([':username' => $username, ':status' => 'validated']);
    $user = $stmt->fetch();

    if ($user === false) {
        respond(404, 'Photographer not found.');
    }

    $photoStmt = db()->prepare(
        'SELECT id, filename, description, posted_at
         FROM photos
         WHERE user_id = :user_id
         ORDER BY posted_at DESC, id DESC'
    );
    $photoStmt->execute([':user_id' => (int) $user['id']]);
    $photos = $photoStmt->fetchAll();

    echo json_encode([
        'username'    => $user['username'],
        'name'        => $user['name'],
        'substack_url' => $user['substack_url'],
        'photos'      => $photos,
    ]);
    return;
}

// ── Full index ────────────────────────────────────────────────────────────────

$stmt = db()->query(
    'SELECT u.username, u.name, u.substack_url,
            COUNT(p.id) AS photo_count
     FROM users u
     LEFT JOIN photos p ON p.user_id = u.id
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
