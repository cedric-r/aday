#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI entry point for the post-event wrap-up email.
 *
 * Intended to be run from cron after the event window closes:
 *   php scripts/send_wrapup.php
 *
 * It is a no-op (exit 0) once the email has been sent, so it is safe to run
 * nightly.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the CLI.');
}

require_once __DIR__ . '/../vendor/autoload.php';

$result = WrapUp::notify();

echo match ($result['status']) {
    'sent'          => "Wrap-up email sent to {$result['count']} participant(s).\n",
    'already_sent'  => "Wrap-up email already sent.\n",
    'no_recipients' => "No validated participants found — nothing sent.\n",
};