#!/usr/bin/env php
<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the CLI.');
}

require_once __DIR__ . '/../vendor/autoload.php';

$migrations = glob(__DIR__ . '/0*.php');
if ($migrations === false || $migrations === []) {
    echo "No migration files found.\n";
    exit(0);
}

sort($migrations);

$db = db();

foreach ($migrations as $file) {
    $name = basename($file);
    echo "Running {$name} ... ";

    /** @var Closure(PDO): void $migration */
    $migration = require $file;
    $migration($db);

    echo "OK\n";
}

echo "All migrations complete.\n";
