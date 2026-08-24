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
 */

$_secure = env('APP_ENV', 'production') !== 'development';

session_name('aday_session');

session_set_cookie_params([
    'secure'   => $_secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

unset($_secure);
