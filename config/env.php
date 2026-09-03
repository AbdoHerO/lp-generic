<?php
/**
 * Environment variable reader.
 *
 * Lets a config file name its secrets instead of containing them:
 *
 *     'pass' => env('DB_PASSWORD'),
 *
 * Resolution order, first hit wins:
 *   1. a real environment variable  (Docker, systemd, Apache SetEnv)
 *   2. config/.env                  (shared hosting, where 1 is not available)
 *   3. the default passed in
 *
 * config/.env lives inside config/, which .htaccess and the production vhost
 * both deny outright — one directory rule already protects it, rather than a
 * separate rule per file. It is gitignored, like the config files themselves.
 */

/** Parse config/.env once. Returns an empty array when the file is absent. */
function env_file(): array {
    static $vars = null;
    if ($vars !== null) return $vars;

    $vars = [];
    $file = __DIR__ . '/.env';
    if (!is_readable($file)) return $vars;

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip one layer of matching quotes so a password containing '#' or a
        // trailing space survives.
        $len = strlen($value);
        if ($len >= 2 && (
            ($value[0] === '"'  && $value[$len - 1] === '"') ||
            ($value[0] === "'"  && $value[$len - 1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '') $vars[$key] = $value;
    }
    return $vars;
}

/** @return string|int|bool|null */
function env(string $key, $default = null) {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;

    $file = env_file();
    if (isset($file[$key]) && $file[$key] !== '') return $file[$key];

    return $default;
}

function env_bool(string $key, bool $default): bool {
    $v = env($key);
    if ($v === null) return $default;
    return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on'], true);
}

function env_int(string $key, int $default): int {
    $v = env($key);
    return $v === null ? $default : (int)$v;
}

/**
 * Fail loudly for a value that has no safe default.
 *
 * A missing database password must stop the request with a message that says
 * which key is missing — not connect as an empty-password user and produce an
 * access-denied trace with the username in it.
 */
function env_required(string $key) {
    $v = env($key);
    if ($v === null || $v === '') {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Configuration error: {$key} is not set.\n"
           . "Set it as an environment variable, or add it to config/.env "
           . "(copy config/.env.example).\n");
    }
    return $v;
}
