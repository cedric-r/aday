<?php

declare(strict_types=1);

/**
 * Migration 004 — Add highlight, gear, and EXIF capture columns to photos.
 *
 * Adds:
 *  - highlight      1 = admin-picked highlight (shown in the home highlights strip)
 *  - gear           Free-text gear note (film photographers type their setup)
 *  - exif_*         Camera metadata captured at upload for digital files
 *
 * Idempotent: skips columns that already exist (the migration runner re-runs
 * every file on each invocation).
 *
 * @return Closure(PDO): void
 */
return static function (PDO $db): void {
    $existing = [];
    foreach ($db->query('PRAGMA table_info(photos)') ?: [] as $col) {
        $existing[(string) $col['name']] = true;
    }

    $columns = [
        'highlight'      => "ALTER TABLE photos ADD COLUMN highlight INTEGER NOT NULL DEFAULT 0",
        'gear'           => "ALTER TABLE photos ADD COLUMN gear TEXT",
        'exif_make'      => "ALTER TABLE photos ADD COLUMN exif_make TEXT",
        'exif_model'     => "ALTER TABLE photos ADD COLUMN exif_model TEXT",
        'exif_focal'     => "ALTER TABLE photos ADD COLUMN exif_focal TEXT",
        'exif_aperture'  => "ALTER TABLE photos ADD COLUMN exif_aperture TEXT",
        'exif_shutter'   => "ALTER TABLE photos ADD COLUMN exif_shutter TEXT",
        'exif_iso'       => "ALTER TABLE photos ADD COLUMN exif_iso TEXT",
    ];

    foreach ($columns as $name => $sql) {
        if (!isset($existing[$name])) {
            $db->exec($sql);
        }
    }

    $db->exec(
        'CREATE INDEX IF NOT EXISTS idx_photos_highlight ON photos(highlight)'
    );
};