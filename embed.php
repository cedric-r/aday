<?php

declare(strict_types=1);

/**
 * Embeddable SPA entry point (/embed) — same shell as the app but with a
 * permissive frame policy so Substack and other embeds can iframe it.
 *
 * Everything else is denied framing via .htaccess (Content-Security-Policy
 * incl. non-always). This script overrides the value for exactly the
 * /embed route (the target). Route: ^embed$ -> embed.php.
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

echo $shell;