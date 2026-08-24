<?php

declare(strict_types=1);

/**
 * Migration 001 — Create users and settings tables.
 *
 * Returns a closure so the migration runner can load multiple migrations
 * in the same process without function-redeclaration conflicts.
 *
 * @return Closure(PDO): void
 */
return static function (PDO $db): void {
    $db->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT    NOT NULL UNIQUE,
            name          TEXT    NOT NULL,
            substack_url  TEXT,
            email         TEXT    NOT NULL UNIQUE,
            password_hash TEXT    NOT NULL,
            timezone      TEXT    NOT NULL,
            status        TEXT    NOT NULL DEFAULT 'pending',
            is_admin      INTEGER NOT NULL DEFAULT 0,
            created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
        )
        SQL
    );

    $db->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )
        SQL
    );
};
