#!/usr/bin/env php
<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

db()->prepare('DELETE FROM settings WHERE key = :key')
    ->execute([':key' => 'setup_complete']);

echo "Admin setup reset. Visit /setup.php to reconfigure.\n";
