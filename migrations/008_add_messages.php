<?php

declare(strict_types=1);

/**
 * Migration 008 — Admin messages (Notifications tab).
 *
 * One row per announcement an admin sends to the validated participants.
 * Messages are read in-app on the Notifications tab; nothing is emailed.
 * Idempotent like 004–007: skips creation if the table already exists.
 *
 * @return Closure(PDO): void
 */
return static function (PDO $db): void {
    $exists = false;
    foreach ($db->query("SELECT name FROM sqlite_master WHERE type = 'table'") ?: [] as $row) {
        if ($row['name'] === 'messages') {
            $exists = true;
            break;
        }
    }

    if (!$exists) {
        $db->exec(
            "CREATE TABLE messages (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                subject    TEXT NOT NULL,
                body       TEXT NOT NULL,
                created_by INTEGER,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )"
        );
    }
};
