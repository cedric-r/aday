<?php

declare(strict_types=1);

/**
 * PHPUnit test bootstrap.
 *
 * Sets up an in-memory SQLite database and runs all migrations against it.
 * Provides TestHelper for DB setup, HTTP endpoint simulation, and fixtures.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// CLI-safe session configuration: use files, no cookies.
ini_set('session.save_handler', 'files');
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_trans_sid', '0');

// Start the session once for the whole test process so that manual $_SESSION
// assignment is preserved when API files call session_start() (which becomes
// a no-op when a session is already active).
if (session_status() === PHP_SESSION_NONE) {
    session_name('aday_session');
    session_start();
}

final class TestHelper
{
    /**
     * Reset the shared database singleton to a fresh in-memory SQLite instance
     * and apply all schema migrations.
     *
     * Call in PHPUnit setUp() for each test that touches the database.
     */
    public static function resetDb(): PDO
    {
        $fresh = new PDO(
            'sqlite::memory:',
            options: [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $fresh->exec('PRAGMA foreign_keys=ON');

        // Register as the singleton used by all db() calls.
        db($fresh);

        // Run migrations in order.
        $files = glob(__DIR__ . '/../migrations/0*.php');
        if ($files !== false) {
            sort($files);
            foreach ($files as $file) {
                /** @var Closure(PDO): void $migration */
                $migration = require $file;
                $migration($fresh);
            }
        }

        return $fresh;
    }

    /**
     * Simulate an HTTP request to a PHP API endpoint file.
     *
     * Sets superglobals, executes the file in a closure, captures output,
     * and returns the response. ResponseException (thrown by respond() in
     * test mode) is caught to capture status + body without process exit.
     *
     * The PHP session is already started in bootstrap; API files' session_start()
     * calls are therefore no-ops and $_SESSION is preserved as set here.
     *
     * @param  string                $file        Absolute path to the PHP entrypoint.
     * @param  string                $method      HTTP method (GET, POST, PUT, DELETE).
     * @param  array<string, mixed>  $body        Request body fields.
     * @param  array<string, mixed>  $query       Query string parameters ($_GET).
     * @param  array<string, mixed>  $sessionData Session values to merge into $_SESSION.
     * @param  array<string, mixed>  $files       $_FILES entries.
     * @param  string                $contentType Request Content-Type header.
     * @return array{status: int, body: string, json: mixed}
     */
    public static function request(
        string $file,
        string $method = 'GET',
        array  $body = [],
        array  $query = [],
        array  $sessionData = [],
        array  $files = [],
        string $contentType = 'application/x-www-form-urlencoded',
    ): array {
        http_response_code(200);

        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['CONTENT_TYPE']   = $contentType;
        $_GET                      = $query;
        $_FILES                    = $files;
        $_POST                     = strtoupper($method) === 'GET' ? [] : $body;

        // Merge caller-supplied session data (preserve existing keys such as
        // captcha_index set by the test before calling request()).
        foreach ($sessionData as $k => $v) {
            $_SESSION[$k] = $v;
        }

        $output     = '';
        $statusCode = 200;

        try {
            ob_start();
            (static function (string $f): void {
                require $f;
            })($file);
            $raw        = ob_get_clean();
            $output     = $raw === false ? '' : $raw;
            $code       = http_response_code();
            $statusCode = $code === false ? 200 : $code;
        } catch (ResponseException $e) {
            ob_end_clean();
            $output     = $e->responseBody;
            $statusCode = $e->statusCode;
        } catch (Throwable $e) {
            ob_end_clean();
            $output     = (string) json_encode(['error' => $e->getMessage()]);
            $statusCode = 500;
        }

        return [
            'status' => $statusCode,
            'body'   => $output,
            'json'   => json_decode($output, true),
        ];
    }

    /**
     * Insert a user row directly, bypassing the API layer.
     *
     * @param  array<string, mixed> $overrides  Fields to override from defaults.
     * @return array<string, mixed>
     */
    public static function createUser(array $overrides = []): array
    {
        $defaults = [
            'username'      => 'testuser',
            'name'          => 'Test User',
            'substack_url'  => null,
            'email'         => 'test@example.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'timezone'      => 'UTC',
            'status'        => 'validated',
            'is_admin'      => 0,
        ];

        $data = array_merge($defaults, $overrides);

        db()->prepare(
            'INSERT INTO users (username, name, substack_url, email, password_hash, timezone, status, is_admin)
             VALUES (:username, :name, :substack_url, :email, :password_hash, :timezone, :status, :is_admin)'
        )->execute($data);

        $data['id'] = (int) db()->lastInsertId();
        return $data;
    }

    /**
     * Insert a photo row directly, bypassing the API layer.
     *
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createPhoto(array $overrides = []): array
    {
        $defaults = [
            'user_id'     => 1,
            'filename'    => 'test.jpg',
            'description' => 'Test photo',
            'posted_at'   => date('Y-m-d H:i:s'),
        ];

        $data = array_merge($defaults, $overrides);

        db()->prepare(
            'INSERT INTO photos (user_id, filename, description, posted_at)
             VALUES (:user_id, :filename, :description, :posted_at)'
        )->execute($data);

        $data['id'] = (int) db()->lastInsertId();
        return $data;
    }

    /**
     * Set a settings key/value pair.
     */
    public static function setSetting(string $key, string $value): void
    {
        db()->prepare(
            'INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)'
        )->execute(['key' => $key, 'value' => $value]);
    }
}
