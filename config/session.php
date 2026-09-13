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

    // Server-side idle timeout for sessions (a fresh cookie still dies after 2h).
    ini_set('session.gc_maxlifetime', '7200');

    session_name('aday_session');

    session_set_cookie_params([
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    unset($secure);
}

// Origin / Sec-Fetch-Site check on state-changing requests (CSRF defense in
// depth on top of SameSite=Lax — audit m2). Same-origin browser requests carry
// Origin or Sec-Fetch-Site; cross-origin ones are rejected before any handler
// runs. Non-browser clients that omit both headers are unaffected.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
    $host   = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
    if ($origin !== '' && $host !== '') {
        // Compare the FULL authority (host + non-default port), not just the
        // host: HTTP_HOST carries the port for non-default ports, so a
        // host-only comparison rejected legitimate same-origin writes (and,
        // worse, accepted cross-origin ones from a different port on the same
        // host). Default ports are normalised away because browsers omit them
        // from Host for http://:80 and https://:443.
        $originHost   = parse_url($origin, PHP_URL_HOST);
        $originPort   = parse_url($origin, PHP_URL_PORT);
        $originScheme = parse_url($origin, PHP_URL_SCHEME);
        if ($originPort !== null
            && (($originScheme === 'http' && $originPort === 80) || ($originScheme === 'https' && $originPort === 443))
        ) {
            $originPort = null;
        }
        $originAuthority = $originHost === null
            ? null
            : ($originPort !== null ? "{$originHost}:{$originPort}" : $originHost);

        if ($originAuthority !== null && $originAuthority !== $host) {
            respond(403, ['message' => 'Cross-origin request rejected.']);
        }
    }
    $fetchSite = isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? (string) $_SERVER['HTTP_SEC_FETCH_SITE'] : '';
    if ($fetchSite === 'cross-site') {
        respond(403, ['message' => 'Cross-origin request rejected.']);
    }
}
