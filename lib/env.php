<?php
declare(strict_types=1);

/**
 * Load simple KEY=VALUE pairs from a local .env file.
 * Values already supplied by the web server/OS always take precedence.
 */
function appLoadEnv(string $path): void
{
    if (!is_readable($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_starts_with($line, 'export ')) $line = trim(substr($line, 7));

        $separator = strpos($line, '=');
        if ($separator === false) continue;

        $key = trim(substr($line, 0, $separator));
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) continue;
        if (getenv($key) !== false || array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER)) continue;

        $value = trim(substr($line, $separator + 1));
        $length = strlen($value);
        if ($length >= 2 && (($value[0] === '"' && $value[$length - 1] === '"') || ($value[0] === "'" && $value[$length - 1] === "'"))) {
            $quote = $value[0];
            $value = substr($value, 1, -1);
            if ($quote === '"') $value = stripcslashes($value);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function appEnv(string $key, ?string $default = null): ?string
{
    $systemValue = getenv($key);
    if ($systemValue !== false) return $systemValue;
    if (array_key_exists($key, $_ENV)) return (string) $_ENV[$key];
    if (array_key_exists($key, $_SERVER)) return (string) $_SERVER[$key];
    return $default;
}

