<?php

declare(strict_types=1);

/**
 * Return (or override) the shared PDO database connection.
 *
 * Passing a non-null $override replaces the singleton — used in tests to
 * inject an in-memory SQLite connection.
 *
 * @param  PDO|null $override  Inject a custom PDO instance (testing only).
 * @return PDO
 */
function db(?PDO $override = null): PDO
{
    static $pdo = null;

    if ($override !== null) {
        $pdo = $override;
        return $pdo;
    }

    if ($pdo !== null) {
        return $pdo;
    }

    $path = (string) env('DB_PATH', 'data/aday.sqlite');

    // Resolve relative DB_PATH against the project root, not the PHP process
    // CWD. Under Apache/mod_php the CWD follows the script directory (e.g.
    // api/status.php runs with CWD=.../api), so a bare relative path would
    // create a second, empty database in the wrong place.
    if ($path !== ':memory:' && !str_starts_with($path, '/')) {
        $path = dirname(__DIR__) . '/' . $path;
    }

    if ($path !== ':memory:') {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    $pdo = new PDO(
        "sqlite:{$path}",
        options: [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    return $pdo;
}

/**
 * Reset the singleton (testing only).
 */
function db_reset(): void
{
    db(new PDO('sqlite::memory:', options: [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]));
}
