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
 * @return Closure(PDO): void
 */
return static function (PDO $db): void {
    $db->exec(
        "ALTER TABLE photos ADD COLUMN highlight INTEGER NOT NULL DEFAULT 0"
    );
    $db->exec(
        "ALTER TABLE photos ADD COLUMN gear TEXT"
    );
    $db->exec(
        "ALTER TABLE photos ADD COLUMN exif_make TEXT"
    );
    $db->exec(
        "ALTER TABLE photos ADD COLUMN exif_model TEXT"
    );
    $db->exec(
        "ALTER TABLE photos ADD COLUMN exif_focal TEXT"
    );
    $db->exec(
        "ALTER TABLE photos ADD COLUMN exif_aperture TEXT"
    );
    $db->exec(
        "ALTER TABLE photos ADD COLUMN exif_shutter TEXT"
    );
    $db->exec(
        "ALTER TABLE photos ADD COLUMN exif_iso TEXT"
    );

    $db->exec(
        'CREATE INDEX IF NOT EXISTS idx_photos_highlight ON photos(highlight)'
    );
};