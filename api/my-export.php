<?php

declare(strict_types=1);

/**
 * Self-service download — a validated participant downloads their own photos
 * as a ZIP (originals + descriptions.txt). Uses the same ZipArchive flow as
 * the admin export, scoped to the session user.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

$user = Auth::requireValidated();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, 'Method not allowed.');
}

if (!extension_loaded('zip')) {
    respond(503, 'ZIP export is not available on this server.');
}

$username = (string) $user['username'];

$stmt = db()->prepare(
    'SELECT filename, description, posted_at FROM photos
     WHERE user_id = :uid AND hidden = 0
     ORDER BY posted_at ASC, id ASC'
);
$stmt->execute([':uid' => (int) $user['id']]);
$photos = $stmt->fetchAll();

if ($photos === []) {
    respond(404, 'You have no photos to download yet.');
}

// Cheap DoS guard: one concurrent export per session user. The lock file is
// created atomically; a second simultaneous request bounces with 429 instead
// of stacking ZipArchive builds on the CPU.
$lockDir  = sys_get_temp_dir() . '/aday-export-locks';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0700, true);
}
$lockFile = $lockDir . '/u' . (int) $user['id'] . '.lock';
$lockH    = @fopen($lockFile, 'x');
if ($lockH === false) {
    respond(429, 'An export is already in progress — try again in a moment.');
}

$baseDir = dirname(__DIR__) . '/uploads/' . $username;

$tempPath = sys_get_temp_dir() . '/aday_my_' . bin2hex(random_bytes(8)) . '.zip';
$zip = new ZipArchive();
$opened = $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($opened !== true) {
    fclose($lockH);
    @unlink($lockFile);
    respond(500, 'Failed to create ZIP archive.');
}

$descriptions = '';
$included = 0;
foreach ($photos as $p) {
    $path = $baseDir . '/' . $p['filename'];
    if (!is_file($path)) {
        continue;
    }
    $zip->addFile($path, $p['filename']);
    $descriptions .= $p['filename'] . ': ' . ($p['description'] !== '' ? $p['description'] : '(no description)')
        . '  [' . $p['posted_at'] . "]\n";
    $included++;
}

if ($included === 0) {
    $zip->close();
    @unlink($tempPath);
    fclose($lockH);
    @unlink($lockFile);
    respond(404, 'Your photo files are no longer on disk.');
}

$zip->addFromString('descriptions.txt', $descriptions);
$zip->close();

// Stream then clean up (temp file + lock) — the lock is released after the
// full response is written; PHP guarantees these run even if the client
// disconnects mid-download.
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="my-documentyourlife.zip"');
header('Content-Length: ' . (string) filesize($tempPath));
if (env('APP_ENV') === 'testing') {
    echo "ZIP:" . basename($tempPath);
} else {
    readfile($tempPath);
}
@unlink($tempPath);
fclose($lockH);
@unlink($lockFile);