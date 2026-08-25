<?php

declare(strict_types=1);

/**
 * Router for the PHP built-in development server (dev only — production uses
 * Apache + .htaccess SPA fallback).
 *
 * Usage: php -S localhost:8765 router.php
 *
 * 1.  /api/* and known PHP entry points (setup.php) → let the built-in server
 *     execute the PHP file (return false; the file exists at the docroot).
 * 2.  Static assets built by Vite live under dist/ — the built-in server
 *     cannot serve them from the docroot, so readfile() them with a correct
 *     Content-Type.
 * 3.  Any other path → serve dist/index.html (SPA fallback for React Router).
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (str_starts_with($uri, '/api/') || in_array($uri, ['/setup.php', '/router.php'], true)) {
    return false; // let PHP built-in server handle
}

$file = __DIR__ . '/dist' . $uri;
if ($uri !== '/' && is_file($file)) {
    $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
        'js'          => 'application/javascript',
        'css'         => 'text/css',
        'png'         => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp'        => 'image/webp',
        'svg'         => 'image/svg+xml',
        'ico'         => 'image/x-icon',
        default       => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    readfile($file);
    return true;
}

include __DIR__ . '/dist/index.html';