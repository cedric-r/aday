<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

// me.php always returns 200 — never 401 — to avoid browser auth dialogs.
$user = Auth::currentUser();

if ($user === null) {
    echo json_encode(['authenticated' => false]);
} else {
    echo json_encode([
        'authenticated' => true,
        'username'      => $user['username'],
        'name'          => $user['name'],
        'is_admin'      => (bool) $user['is_admin'],
        'status'        => $user['status'],
        'timezone'      => $user['timezone'],
    ]);
}
