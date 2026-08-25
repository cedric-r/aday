<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

Auth::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// ── GET — return current event date ──────────────────────────────────────────

if ($method === 'GET') {
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->execute([':key' => 'event_date']);
    $row = $stmt->fetch();

    $stmt = db()->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->execute([':key' => 'allow_late_submissions']);
    $lateRow = $stmt->fetch();

    echo json_encode([
        'event_date'             => $row !== false ? $row['value'] : null,
        'allow_late_submissions' => $lateRow !== false && $lateRow['value'] === '1',
    ]);
    return;
}

// ── POST — upsert event date ──────────────────────────────────────────────────

if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw  = (string) file_get_contents('php://input');
        $data = (array) (json_decode($raw, true) ?? []);
    } else {
        $data = $_POST;
    }

    $eventDate = trim((string) ($data['event_date'] ?? ''));

    // Validate YYYY-MM-DD format.
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        respond(422, 'event_date must be in YYYY-MM-DD format.');
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $eventDate);
    if ($parsed === false || $parsed->format('Y-m-d') !== $eventDate) {
        respond(422, 'event_date is not a valid date.');
    }

    $allowLate = isset($data['allow_late_submissions'])
        ? (bool) $data['allow_late_submissions']
        : false;

    db()->prepare(
        'INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)'
    )->execute([':key' => 'event_date', ':value' => $eventDate]);

    db()->prepare(
        'INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)'
    )->execute([':key' => 'allow_late_submissions', ':value' => $allowLate ? '1' : '0']);

    echo json_encode([
        'message'                => 'Event date saved.',
        'event_date'             => $eventDate,
        'allow_late_submissions' => $allowLate,
    ]);
    return;
}

respond(405, 'Method not allowed.');
