<?php

declare(strict_types=1);

(static function (): void {
    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile)) {
        $values = parse_ini_file($envFile, false, INI_SCANNER_RAW);
        if ($values !== false) {
            foreach ($values as $key => $value) {
                if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
                    $_ENV[$key] = $value;
                    putenv("{$key}={$value}");
                }
            }
        }
    }
})();

/**
 * Retrieve an environment variable value.
 *
 * @param  string $key     The environment variable name.
 * @param  mixed  $default Default value when the key is not set.
 * @return mixed
 */
function env(string $key, mixed $default = null): mixed
{
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    return $default;
}
