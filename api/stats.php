<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, 'Method not allowed.');
}

// Total photo count.
$total = (int) db()->query('SELECT COUNT(*) FROM photos')->fetchColumn();

// Hourly posting histogram — UTC hour buckets for the last 48 hours, so the
// "pulse" of the event (across timezones) is visible. Buckets with no photos
// are returned as 0 so the client can render a continuous axis.
$byHourRaw = [];
$stmt = db()->query(
    "SELECT strftime('%Y-%m-%d %H:00', posted_at) AS hour, COUNT(*) AS cnt
     FROM photos
     WHERE posted_at >= datetime('now', '-48 hours')
     GROUP BY hour"
);
if ($stmt !== false) {
    foreach ($stmt->fetchAll() as $row) {
        $byHourRaw[(string) $row['hour']] = (int) $row['cnt'];
    }
}

$byHour = [];
for ($i = 47; $i >= 0; $i--) {
    $hourKey = gmdate('Y-m-d H:00', time() - ($i * 3600));
    $byHour[] = [
        'hour'  => $hourKey,
        'count' => $byHourRaw[$hourKey] ?? 0,
    ];
}

echo json_encode(['total' => $total, 'by_hour' => $byHour]);