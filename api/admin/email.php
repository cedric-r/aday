<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/lib/Auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

Auth::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

/** Resolve the recipient scope: 'validated' participants or 'all' registered users. */
$scopeOf = static function (): string {
    $scope = trim((string) ($_GET['scope'] ?? 'validated'));
    return $scope === 'all' ? 'all' : 'validated';
};

/** Distinct, non-empty, non-admin emails for a given scope. */
$recipientEmails = static function (string $scope): array {
    $where = "email IS NOT NULL AND email != '' AND is_admin = 0";
    if ($scope === 'validated') {
        $where .= " AND status = 'validated'";
    }
    $stmt = db()->query("SELECT DISTINCT email FROM users WHERE {$where}");
    if ($stmt === false) {
        return [];
    }
    return array_values(array_filter(array_map(
        static fn (array $r): string => trim((string) $r['email']),
        $stmt->fetchAll()
    ), static fn (string $e): bool => $e !== ''));
};

// ── GET — recipient counts for a preview (no sending) ────────────────────────

if ($method === 'GET') {
    $scope = $scopeOf();
    $emails = $recipientEmails($scope);
    echo json_encode(['scope' => $scope, 'recipients' => count($emails)]);
    return;
}

// ── POST — send the email ────────────────────────────────────────────────────

if ($method !== 'POST') {
    respond(405, 'Method not allowed.');
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $raw  = (string) file_get_contents('php://input');
    $data = (array) (json_decode($raw, true) ?? []);
} else {
    $data = $_POST;
}

$subject = trim((string) ($data['subject'] ?? ''));
$body    = trim((string) ($data['body'] ?? ''));
$scope   = (($data['scope'] ?? 'validated') === 'all') ? 'all' : 'validated';

if ($subject === '') {
    respond(422, 'subject is required.');
}
if (mb_strlen($subject) > 200) {
    respond(422, 'subject must be 200 characters or fewer.');
}
if ($body === '') {
    respond(422, 'body is required.');
}
if (mb_strlen($body) > 5000) {
    respond(422, 'body must be 5000 characters or fewer.');
}
if (preg_match('/[\r\n]/', $subject)) {
    respond(422, 'subject must be a single line (no newlines).');
}

$emails = $recipientEmails($scope);
if ($emails === []) {
    respond(200, ['status' => 'no_recipients', 'recipients' => 0, 'sent' => 0, 'failed' => 0]);
}

$result = Mailer::make()->sendBroadcast($emails, $subject, $body);

echo json_encode([
    'status'      => 'sent',
    'recipients'  => count($emails),
    'sent'        => $result['sent'],
    'failed'      => $result['failed'],
]);
