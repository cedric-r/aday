<?php

declare(strict_types=1);

/**
 * Application authentication guard — SHARED DEPENDENCY (owned by PHP Developer).
 *
 * Provides three static methods:
 *   - requireAdmin()     — asserts logged-in admin; terminates on failure
 *   - requireValidated() — asserts logged-in validated user; terminates on failure
 *   - currentUser()      — returns user row or null; no side effects
 *
 * All methods assume that config/session.php has been included and
 * session_start() has been called before they are invoked.
 */
final class Auth
{
    /**
     * Assert that the current session belongs to a validated admin user.
     *
     * Sends 403 JSON and terminates if the assertion fails.
     *
     * @return array<string, mixed>  The authenticated admin user row.
     */
    public static function requireAdmin(): array
    {
        $user = self::loadCurrentUser();

        if ($user === null) {
            respond(403, 'Authentication required.');
        }

        if ((int) $user['is_admin'] !== 1) {
            respond(403, 'Admin access required.');
        }

        return $user;
    }

    /**
     * Assert that the current session belongs to a validated user (any role).
     *
     * Sends 401 when unauthenticated, 403 when account is not validated.
     *
     * @return array<string, mixed>  The authenticated validated user row.
     */
    public static function requireValidated(): array
    {
        $user = self::loadCurrentUser();

        if ($user === null) {
            respond(401, 'Authentication required.');
        }

        if ($user['status'] !== 'validated') {
            respond(403, 'Account is pending approval.');
        }

        return $user;
    }

    /**
     * Return the current session user row, or null if not authenticated.
     *
     * No side effects — never calls respond() or exit.
     *
     * @return array<string, mixed>|null
     */
    public static function currentUser(): ?array
    {
        $user = self::loadCurrentUser();
        return $user;
    }

    /**
     * Load user from DB using the session user_id.
     *
     * @return array<string, mixed>|null
     */
    private static function loadCurrentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $stmt = db()->prepare(
            'SELECT id, username, name, substack_url, email, timezone, status, is_admin, created_at
             FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }
}
