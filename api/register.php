<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/session.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Method not allowed.');
}

// ── Parse input ──────────────────────────────────────────────────────────────

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $raw  = (string) file_get_contents('php://input');
    $data = (array) (json_decode($raw, true) ?? []);
} else {
    $data = $_POST;
}

/** @param string $key */
$get = static fn (string $key): string => trim((string) ($data[$key] ?? ''));

// ── Check event open (423 if today is the event date) ────────────────────────

$stmt = db()->prepare('SELECT value FROM settings WHERE key = :key');
$stmt->execute([':key' => 'event_date']);
$row       = $stmt->fetch();
$eventDate = $row !== false ? (string) $row['value'] : null;

if ($eventDate !== null && $eventDate === date('Y-m-d')) {
    respond(423, 'Registration is closed on the event day.');
}

// ── Validate fields ───────────────────────────────────────────────────────────

$errors = [];

$username    = $get('username');
$name        = $get('name');
$substackUrl = $get('substack_url');
$bio         = trim((string) $get('bio'));
// Strip markup (defense-in-depth — React escapes on render, but the API is
// public) and cap length.
$bio         = $bio === '' ? null : mb_substr(trim(strip_tags($bio)), 0, 500);
$bio         = $bio === '' ? null : $bio;
$email       = $get('email');
$password    = $get('password');
$timezone    = $get('timezone');

if ($username === '' || !preg_match('/^[a-z0-9_]{3,30}$/i', $username)) {
    $errors['username'] = 'Username must be 3–30 alphanumeric characters or underscores.';
}

if ($name === '') {
    $errors['name'] = 'Name is required.';
}

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors['email'] = 'A valid email address is required.';
}

if (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}

if ($timezone === '' || !in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
    $errors['timezone'] = 'A valid IANA timezone is required.';
}

if ($substackUrl !== '' && !Validate::httpUrl($substackUrl)) {
    $errors['substack_url'] = 'Substack URL must be a valid http(s) link.';
}

// ── Validate captcha ─────────────────────────────────────────────────────────

/** @var array<int, array{q: string, a: string}> $captcha */
$captcha        = require dirname(__DIR__) . '/config/captcha.php';
$captchaIndex   = isset($_SESSION['captcha_index']) ? (int) $_SESSION['captcha_index'] : -1;
$captchaAnswer  = strtolower(trim($get('captcha_answer')));
$expectedAnswer = ($captchaIndex >= 0 && $captchaIndex <= 49)
    ? strtolower(trim($captcha[$captchaIndex]['a']))
    : null;

if ($expectedAnswer === null || $captchaAnswer !== $expectedAnswer) {
    $errors['captcha_answer'] = 'Incorrect captcha answer.';
}

if ($errors !== []) {
    respond(422, ['errors' => $errors]);
}

// ── Uniqueness check ──────────────────────────────────────────────────────────

$stmt = db()->prepare('SELECT id FROM users WHERE username = :username');
$stmt->execute([':username' => $username]);
if ($stmt->fetch() !== false) {
    respond(409, ['field' => 'username', 'error' => 'Username already taken.']);
}

$stmt = db()->prepare('SELECT id FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);
if ($stmt->fetch() !== false) {
    respond(409, ['field' => 'email', 'error' => 'Email address already registered.']);
}

// ── Insert user ───────────────────────────────────────────────────────────────

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$stmt = db()->prepare(
    'INSERT INTO users (username, name, substack_url, bio, email, password_hash, timezone, status)
     VALUES (:username, :name, :substack_url, :bio, :email, :password_hash, :timezone, :status)'
);
$stmt->execute([
    ':username'      => $username,
    ':name'          => $name,
    ':substack_url'  => $substackUrl !== '' ? $substackUrl : null,
    ':bio'           => $bio,
    ':email'         => $email,
    ':password_hash' => $passwordHash,
    ':timezone'      => $timezone,
    ':status'        => 'pending',
]);

$userId = (int) db()->lastInsertId();
$user   = [
    'id'       => $userId,
    'username' => $username,
    'name'     => $name,
    'email'    => $email,
    'timezone' => $timezone,
];

// ── Send admin validation email (failure is non-fatal) ────────────────────────

try {
    Mailer::make()->sendAdminValidation($user);
} catch (Throwable) {
    // Logged inside Mailer; registration succeeds regardless.
}

// ── Success ───────────────────────────────────────────────────────────────────

http_response_code(201);
echo json_encode(['message' => 'Registration submitted. Awaiting admin approval.']);
