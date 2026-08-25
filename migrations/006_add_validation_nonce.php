<?php

declare(strict_types=1);

/**
 * Migration 006 — Single-use validation nonce for admin validation links.
 *
 * The validation token used to be HMAC(id) only, which meant anyone who knew
 * the (often placeholder) APP_SECRET could forge a link and self-approve.
 * Each validation email now mints a random nonce stored here; the token is
 * bound to id|email|expiry|nonce and the nonce is cleared on use, so links
 * are unforgeable, expiring, and single-use.
 *
 * Idempotent — skips if the column already exists.
 *
 * @return Closure(PDO): void
 */
return static function (PDO $db): void {
    $hasNonce = false;
    foreach ($db->query('PRAGMA table_info(users)') ?: [] as $col) {
        if ($col['name'] === 'validation_nonce') {
            $hasNonce = true;
            break;
        }
    }

    if (!$hasNonce) {
        $db->exec('ALTER TABLE users ADD COLUMN validation_nonce TEXT');
    }
};