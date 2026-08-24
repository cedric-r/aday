<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/lib/Auth.php';

session_start();

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    respond(405, 'Method not allowed.');
}

// Check ZipArchive is available.
if (!extension_loaded('zip')) {
    header('Content-Type: application/json; charset=utf-8');
    respond(500, 'ZipArchive extension is not available on this server.');
}

$tempPath = null;

try {
    $exporter = new Exporter();
    $tempPath = $exporter->buildZip();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="aday-all-photos.zip"');
    header('Content-Length: ' . filesize($tempPath));

    // In test mode we cannot call ob_end_clean() (it would kill PHPUnit's buffer);
    // instead echo the file contents so TestHelper captures them.
    if (env('APP_ENV') === 'testing') {
        echo file_get_contents($tempPath);
    } else {
        ob_end_clean();
        readfile($tempPath);
    }
} finally {
    if ($tempPath !== null && file_exists($tempPath)) {
        unlink($tempPath);
    }
}
