<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Method not allowed.');
}

$result = WrapUp::notify();

echo json_encode([
    'message' => match ($result['status']) {
        'sent'           => "Wrap-up email sent to {$result['count']} participant(s).",
        'already_sent'   => 'Wrap-up email was already sent for this event.',
        'no_recipients'  => 'No validated participants with an email address.',
        'failed'         => 'Wrap-up email could not be sent (mail relay error). The flag was reset — try again.',
    },
    'status' => $result['status'],
    'count'  => $result['count'],
]);