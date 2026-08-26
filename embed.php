<?php

declare(strict_types=1);

/**
 * Embeddable SPA entry point (/embed) — same shell as the app but with a
 * permissive frame policy so it can be iframed by sites that allow arbitrary
 * iframes (Notion, self-hosted blogs, Webflow, ...).
 *
 * It also injects OpenGraph/Twitter tags so pasting the /embed URL (with an
 * optional ?photographer=...) on Substack or anywhere else shows a proper
 * branded card instead of a bare link. Route: ^embed$ -> embed.php.
 */

$shell = @file_get_contents(__DIR__ . '/dist/index.html');
if ($shell === false) {
    http_response_code(500);
    exit('Build missing — run npm run build first.');
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
// Frame policy is set per-path in .htaccess (frame-ancestors * for /embed,
// 'none' everywhere else via <If> on THE_REQUEST). Nothing extra needed here.

// ── Social card meta ──────────────────────────────────────────────────────
$photographer = isset($_GET['photographer']) ? trim((string) $_GET['photographer']) : '';
$base         = 'https://aday.photoni.st';
$title        = 'Document Your Life';
$description  = 'One day, many photographers. Browse the live gallery.';

$autoload = __DIR__ . '/vendor/autoload.php';
if ($photographer !== '' && is_file($autoload)) {
    require_once $autoload;
    try {
        $stmt = db()->prepare('SELECT name FROM users WHERE username = :u AND status = :s');
        $stmt->execute([':u' => $photographer, ':s' => 'validated']);
        $name = $stmt->fetchColumn();
        if ($name !== false) {
            $title       = $name . ' — Document Your Life';
            $description = 'Photos by ' . $name . ' — one day, many photographers.';
        }
        $base = rtrim((string) env('APP_URL', 'https://aday.photoni.st'), '/');
    } catch (Throwable) {
        // DB/env unavailable — fall back to the generic card.
    }
}

$image = $base . '/documentyourlife.png';
$url   = $base . '/embed' . ($photographer !== '' ? '?photographer=' . urlencode($photographer) : '');

$meta = implode("\n", [
    '<meta property="og:title" content="' . htmlspecialchars($title) . '">',
    '<meta property="og:description" content="' . htmlspecialchars($description) . '">',
    '<meta property="og:image" content="' . htmlspecialchars($image) . '">',
    '<meta property="og:url" content="' . htmlspecialchars($url) . '">',
    '<meta property="og:type" content="website">',
    '<meta property="og:site_name" content="Document Your Life">',
    '<meta name="twitter:card" content="summary_large_image">',
    '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">',
    '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">',
    '<meta name="twitter:image" content="' . htmlspecialchars($image) . '">',
]);

$shell = str_replace('</head>', $meta . "\n</head>", $shell);

echo $shell;