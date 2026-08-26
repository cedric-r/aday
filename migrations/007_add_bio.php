<?php

declare(strict_types=1);

/**
 * Migration 007 — Photographer bio.
 *
 * Adds users.bio (optional short blurb shown on the photographer profile
 * page). Idempotent like 004/005/006: skips the column if it already exists.
 *
 * @return Closure(PDO): void
 */
return static function (PDO $db): void {
    $hasBio = false;
    foreach ($db->query('PRAGMA table_info(users)') ?: [] as $col) {
        if ($col['name'] === 'bio') {
            $hasBio = true;
            break;
        }
    }

    if (!$hasBio) {
        $db->exec('ALTER TABLE users ADD COLUMN bio TEXT');
    }
};