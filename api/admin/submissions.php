<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, 'Method not allowed.');
}

$stmt = db()->query(
    'SELECT p.id, u.username, u.name, p.filename, p.description, p.posted_at
     FROM photos p
     JOIN users u ON u.id = p.user_id
     ORDER BY p.posted_at DESC'
);

echo json_encode($stmt->fetchAll());
