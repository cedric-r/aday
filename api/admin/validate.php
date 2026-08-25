<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, 'Method not allowed.');
}

$id      = (int) ($_GET['id'] ?? 0);
$token   = (string) ($_GET['token'] ?? '');
$expires = (int) ($_GET['expires'] ?? 0);

if ($id <= 0 || $token === '' || $expires <= 0) {
    respond(400, 'id, expires and token are required.');
}

// Fail closed: same secret policy as the minting side (lib/Mailer.php).
try {
    $secret = Mailer::requiredSecret();
} catch (RuntimeException $e) {
    error_log(
        date('Y-m-d H:i:s') . " [validate] Refusing to validate: " . $e->getMessage() . "\n",
        3,
        dirname(__DIR__, 2) . '/logs/mail.log'
    );
    respond(500, 'Validation is not configured. Contact the administrator.');
}

// Load the user with its single-use nonce.
$stmt = db()->prepare('SELECT id, username, email, status, validation_nonce FROM users WHERE id = :id');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if ($user === false) {
    respond(401, 'Invalid validation link.');
}

// A NULL nonce means the link was already used (or never minted).
if ($user['validation_nonce'] === null || $user['validation_nonce'] === '') {
    respond(401, 'Invalid validation link.');
}

// Expiry check (server time vs the timestamp embedded in the link).
if (time() > $expires) {
    respond(401, 'Validation link has expired.');
}

// Verify HMAC with constant-time comparison. The token is bound to the
// current id, email, expiry and the single-use nonce — it cannot be forged
// without the nonce + APP_SECRET, and it dies once used.
$expected = Mailer::buildToken(
    (int) $user['id'],
    (string) $user['email'],
    (string) $user['validation_nonce'],
    $expires,
    $secret
);

if (!hash_equals($expected, $token)) {
    respond(401, 'Invalid validation link.');
}

// Set status to validated and consume the nonce (single-use).
db()->prepare('UPDATE users SET status = :status, validation_nonce = NULL WHERE id = :id')
    ->execute([':status' => 'validated', ':id' => $id]);

// In production this would redirect; in test mode return 200 JSON.
if (env('APP_ENV') === 'testing') {
    echo json_encode(['message' => 'User validated.', 'username' => $user['username']]);
} else {
    header('Location: /admin/?validated=1');
    http_response_code(302);
}