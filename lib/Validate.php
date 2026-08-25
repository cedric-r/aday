<?php

declare(strict_types=1);

/**
 * Shared input validation helpers.
 */
final class Validate
{
    /**
     * Accepts only http/https URLs — used for substack_url so that
     * javascript:, data:, vbscript: or file: values can never be rendered
     * as live links (stored link-injection / XSS-class, audit M1/M4).
     */
    public static function httpUrl(string $value): bool
    {
        if ($value === '' || $value === 'null') {
            return false;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}