<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/session.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

/** @var array<int, array{q: string, a: string}> $captcha */
$captcha = require dirname(__DIR__) . '/config/captcha.php';

$index = random_int(0, 49);
$_SESSION['captcha_index'] = $index;

echo json_encode([
    'index'    => $index,
    'question' => $captcha[$index]['q'],
]);
