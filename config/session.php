<?php

declare(strict_types=1);

/**
 * Session configuration — SHARED DEPENDENCY (owned by PHP Developer).
 *
 * Include this file at the top of every API entry point BEFORE session_start().
 * Sets secure, HttpOnly, SameSite=Lax cookie flags and a consistent session name.
 *
 * Dev override: set APP_ENV=development in .env to disable the Secure flag
 * (required when running without HTTPS locally).
 *
 * In test mode (APP_ENV=testing) the session is already started by the test
 * bootstrap, so cookie params and session name cannot be changed; this block
 * is intentionally skipped.
 */

if (session_status() === PHP_SESSION_NONE) {
    $secure = env('APP_ENV', 'production') !== 'development';

    session_name('aday_session');

    session_set_cookie_params([
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    unset($secure);
}
