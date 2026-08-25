<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

// Load event date.
$stmt = db()->prepare('SELECT value FROM settings WHERE key = :key');
$stmt->execute([':key' => 'event_date']);
$row       = $stmt->fetch();
$eventDate = $row !== false ? (string) $row['value'] : null;

if ($eventDate === null) {
    echo json_encode([
        'window_open'            => false,
        'event_date'             => null,
        'allow_late_submissions' => false,
        'message'                => 'Event not yet scheduled.',
    ]);
    return;
}

$user       = Auth::currentUser();
$windowOpen = null;
$allowLate  = false;

if ($user !== null) {
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->execute([':key' => 'allow_late_submissions']);
    $lateRow   = $stmt->fetch();
    $allowLate = $lateRow !== false && $lateRow['value'] === '1';

    $windowOpen = WindowCheck::isPostingOpen((string) $user['timezone'], $eventDate, allowLate: $allowLate);
}

$message = match (true) {
    $windowOpen === true  => 'Posting window is open.',
    $windowOpen === false => 'Posting window is closed.',
    default               => 'Posting window status unknown (not authenticated).',
};

echo json_encode([
    'window_open'            => $windowOpen,
    'event_date'             => $eventDate,
    'allow_late_submissions' => $allowLate,
    'message'                => $message,
]);
