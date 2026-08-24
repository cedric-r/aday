<?php

declare(strict_types=1);

/**
 * Migration 003 — Create photos table with indexes.
 *
 * Depends on migration 001 (users table must exist).
 *
 * Returns a closure so the migration runner can load multiple migrations
 * in the same process without function-redeclaration conflicts.
 *
 * @return Closure(PDO): void
 */
return static function (PDO $db): void {
    $db->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS photos (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            filename    TEXT    NOT NULL,
            description TEXT    NOT NULL DEFAULT '',
            posted_at   TEXT    NOT NULL DEFAULT (datetime('now'))
        )
        SQL
    );

    $db->exec(
        'CREATE INDEX IF NOT EXISTS idx_photos_posted_at ON photos(posted_at DESC)'
    );

    $db->exec(
        'CREATE INDEX IF NOT EXISTS idx_photos_user_id ON photos(user_id)'
    );
};
