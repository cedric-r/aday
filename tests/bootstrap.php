<?php

declare(strict_types=1);

/**
 * PHPUnit test bootstrap.
 *
 * Sets up an in-memory SQLite database and runs all migrations against it
 * before the test suite starts. Each test class that needs a clean DB
 * should call TestHelper::resetDb() in setUp().
 */

require_once __DIR__ . '/../vendor/autoload.php';

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

        // Register as singleton
        db($fresh);

        // Run migrations in order
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
     * Sets up superglobals, captures output, and returns the response body
     * plus the last set HTTP status code.
     *
     * @param  string                $file     Absolute path to the PHP entrypoint.
     * @param  string                $method   HTTP method (GET, POST, PUT, DELETE).
     * @param  array<string, mixed>  $body     Request body (decoded — will be JSON-encoded for application/json).
     * @param  array<string, mixed>  $query    Query string parameters ($_GET).
     * @param  array<string, mixed>  $session  Session values ($_SESSION).
     * @param  array<string, mixed>  $files    $_FILES entries.
     * @param  string                $contentType  Request Content-Type.
     * @return array{status: int, body: string, json: mixed}
     */
    public static function request(
        string $file,
        string $method = 'GET',
        array  $body = [],
        array  $query = [],
        array  $session = [],
        array  $files = [],
        string $contentType = 'application/json',
    ): array {
        // Reset HTTP status tracking
        http_response_code(200);

        // Set up superglobals
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['CONTENT_TYPE']   = $contentType;
        $_GET                      = $query;
        $_FILES                    = $files;
        $_SESSION                  = $session;

        if (strtoupper($method) === 'GET') {
            $_POST = [];
        } elseif ($contentType === 'application/json') {
            $_POST = $body;
            // Also set raw input simulation via a stream wrapper if needed
            // (most endpoints will decode JSON from $_POST in tests)
        } else {
            $_POST = $body;
        }

        ob_start();
        (static function (string $file): void {
            // Isolate includes but share global state (superglobals, functions)
            require $file;
        })($file);
        $output = (string) ob_get_clean();

        $status = http_response_code();

        return [
            'status' => $status === false ? 200 : $status,
            'body'   => $output,
            'json'   => json_decode($output, true),
        ];
    }

    /**
     * Insert a user row directly, bypassing API layer. Returns inserted user.
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

        $id   = (int) db()->lastInsertId();
        $data['id'] = $id;
        return $data;
    }

    /**
     * Set a settings key/value.
     */
    public static function setSetting(string $key, string $value): void
    {
        db()->prepare(
            'INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)'
        )->execute(['key' => $key, 'value' => $value]);
    }
}
