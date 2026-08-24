<?php

declare(strict_types=1);

/**
 * Flow-control exception thrown by respond() in test mode.
 *
 * In production respond() calls exit; in test mode (APP_ENV=testing) it
 * throws this exception so PHPUnit can capture status + body without the
 * process terminating.
 */
final class ResponseException extends RuntimeException
{
    public function __construct(
        public readonly int    $statusCode,
        public readonly string $responseBody,
    ) {
        parent::__construct($responseBody, $statusCode);
    }
}
