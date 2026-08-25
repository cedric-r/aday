<?php

declare(strict_types=1);

/**
 * Social-card / meta renderer for individual photo pages (/photos/:id).
 *
 * Real users get the normal SPA (dist/index.html) via this script too — the
 * only difference is the injected <meta> tags. Crawlers (Substack, iMessage,
 * Twitter, Facebook, Slack…) read the tags without executing JS, so sharing
 * a photo page shows the image and title instead of a blank card.
 *
 * Hidden photos are treated as not found (no tags, 404).
 */

require_once __DIR__ . '/vendor/autoload.php';

$id  = max(0, (int) ($_GET['id'] ?? 0));
$index = @file_get_contents(__DIR__ . '/dist/index.html');
if ($index === false) {
    http_response_code(500);
    exit('Build missing — run npm run build first.');
}

header('Content-Type: text/html; charset=utf-8');

$base = rtrim((string) env('APP_URL', 'https://aday.photoni.st'), '/');
$pageUrl = "{$base}/photos/{$id}";

$photo = null;
if ($id > 0) {
    $stmt = db()->prepare(
        'SELECT p.id, p.filename, p.description, p.posted_at,
                u.username, u.name
         FROM photos p
         JOIN users u ON u.id = p.user_id
         WHERE p.id = :id AND p.hidden = 0'
    );
    $stmt->execute([':id' => $id]);
    $photo = $stmt->fetch();
}

if ($photo !== false && $photo !== null) {
    $imageUrl = $base . '/uploads/' . rawurlencode((string) $photo['username']) . '/' . rawurlencode((string) $photo['filename']);
    $title    = 'Photo by ' . $photo['name'] . ' — Document Your Life';
    $descText = trim((string) $photo['description']);
    $desc     = $descText !== '' ? mb_substr($descText, 0, 180) : 'One day. Many photographers. One shared moment.';

    $tags = implode("\n", [
        '<meta property="og:type" content="article" />',
        '<meta property="og:site_name" content="Document Your Life" />',
        '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '" />',
        '<meta property="og:description" content="' . htmlspecialchars($desc, ENT_QUOTES) . '" />',
        '<meta property="og:image" content="' . htmlspecialchars($imageUrl, ENT_QUOTES) . '" />',
        '<meta property="og:image:alt" content="' . htmlspecialchars($desc, ENT_QUOTES) . '" />',
        '<meta property="og:url" content="' . htmlspecialchars($pageUrl, ENT_QUOTES) . '" />',
        '<meta name="twitter:card" content="summary_large_image" />',
        '<meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '" />',
        '<meta name="twitter:description" content="' . htmlspecialchars($desc, ENT_QUOTES) . '" />',
        '<meta name="twitter:image" content="' . htmlspecialchars($imageUrl, ENT_QUOTES) . '" />',
        '<link rel="canonical" href="' . htmlspecialchars($pageUrl, ENT_QUOTES) . '" />',
        '',
    ]);

    $index = str_replace('</head>', $tags . '</head>', $index);
} else {
    http_response_code(404);
}

echo $index;