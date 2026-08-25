<?php

declare(strict_types=1);

/**
 * Migration 005 — Add hidden (unlist) flag to photos.
 *
 * Set to 1 to pull a photo out of all public surfaces (feed, highlights,
 * photographer profile, single-photo and embed endpoints) without deleting
 * the file or the row.
 *
 * Idempotent — skips if the column already exists.
 *
 * @return Closure(PDO): void
 */
return static function (PDO $db): void {
    $hasHidden = false;
    foreach ($db->query('PRAGMA table_info(photos)') ?: [] as $col) {
        if ($col['name'] === 'hidden') {
            $hasHidden = true;
            break;
        }
    }

    if (!$hasHidden) {
        $db->exec("ALTER TABLE photos ADD COLUMN hidden INTEGER NOT NULL DEFAULT 0");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_photos_hidden ON photos(hidden)');
    }
};