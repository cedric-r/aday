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
        'window_open' => false,
        'event_date'  => null,
        'message'     => 'Event not yet scheduled.',
    ]);
    return;
}

$user       = Auth::currentUser();
$windowOpen = null;

if ($user !== null) {
    $windowOpen = WindowCheck::isPostingOpen((string) $user['timezone'], $eventDate);
}

$message = match (true) {
    $windowOpen === true  => 'Posting window is open.',
    $windowOpen === false => 'Posting window is closed.',
    default               => 'Posting window status unknown (not authenticated).',
};

echo json_encode([
    'window_open' => $windowOpen,
    'event_date'  => $eventDate,
    'message'     => $message,
]);
