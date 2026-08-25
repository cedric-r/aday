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

$format = trim((string) ($_GET['format'] ?? 'zip'));

// -- Metadata export (CSV or JSON) -----------------------------------------

if ($format === 'csv' || $format === 'json') {
    $stmt = db()->query(
        'SELECT p.id, u.username, u.name, u.timezone, u.substack_url,
                p.filename, p.description, p.posted_at, p.highlight, p.gear,
                p.exif_make, p.exif_model, p.exif_focal, p.exif_aperture, p.exif_shutter, p.exif_iso
         FROM photos p
         JOIN users u ON u.id = p.user_id
         ORDER BY p.posted_at DESC, p.id DESC'
    );
    $rows = $stmt->fetchAll();

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="aday-metadata.json"');
        echo json_encode(['photos' => $rows]);
        return;
    }

    $headers = [
        'id', 'username', 'name', 'timezone', 'substack_url', 'filename',
        'posted_at', 'description', 'highlight', 'gear',
        'camera_make', 'camera_model', 'focal_length', 'aperture', 'shutter', 'iso',
    ];

    $out = fopen('php://temp', 'w+');
    if ($out === false) {
        respond(500, 'Failed to open output stream.');
    }
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['id'],
            $row['username'],
            $row['name'],
            $row['timezone'],
            $row['substack_url'],
            $row['filename'],
            $row['posted_at'],
            $row['description'],
            (int) $row['highlight'],
            $row['gear'] ?? '',
            $row['exif_make'] ?? '',
            $row['exif_model'] ?? '',
            $row['exif_focal'] ?? '',
            $row['exif_aperture'] ?? '',
            $row['exif_shutter'] ?? '',
            $row['exif_iso'] ?? '',
        ]);
    }
    rewind($out);
    $csv = (string) stream_get_contents($out);
    fclose($out);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aday-metadata.csv"');
    if (env('APP_ENV') === 'testing') {
        echo $csv;
    } else {
        ob_end_clean();
        echo $csv;
    }
    return;
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
