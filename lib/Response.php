<?php

declare(strict_types=1);

/**
 * Send a JSON HTTP response and terminate (or throw in test mode).
 *
 * In production (APP_ENV != 'testing'): sets the HTTP status code, writes the
 * JSON body to stdout, and calls exit.
 *
 * In test mode (APP_ENV=testing): throws ResponseException so PHPUnit can
 * capture status + body without the process terminating.
 *
 * @param  int                       $code     HTTP status code.
 * @param  string|array<mixed>|null  $payload  String → wrapped in {"error":"..."}.
 *                                             Array → JSON-encoded as-is.
 *                                             Null  → empty body with status only.
 * @return never
 * @throws ResponseException  In test mode only.
 */
function respond(int $code, mixed $payload = null): never
{
    $body = match (true) {
        is_string($payload) => (string) json_encode(['error' => $payload]),
        is_array($payload)  => (string) json_encode($payload),
        default             => '',
    };

    http_response_code($code);

    if (env('APP_ENV') === 'testing') {
        throw new ResponseException($code, $body);
    }

    echo $body;
    exit;
}
