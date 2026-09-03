<?php
/**
 * Signed form timestamps.
 *
 * Separate from helpers.php so it can be exercised without a database or a
 * session: it depends on nothing but app_secret(), which the caller provides.
 *
 * Bots either post the order form without ever rendering it, or render and
 * submit within milliseconds. A signed render time catches both, and signing is
 * what stops a script from simply inventing a plausible one.
 */

/** Stamp for a form being rendered now. */
function form_token(): string {
    $ts = (string)time();
    return $ts . '.' . hash_hmac('sha256', $ts, app_secret());
}

/**
 * @return array{ok: bool, age: int} age is seconds since the form was rendered.
 */
function form_token_check(?string $token): array {
    if (!is_string($token) || !str_contains($token, '.')) return ['ok' => false, 'age' => 0];

    [$ts, $sig] = explode('.', $token, 2);
    if (!ctype_digit($ts)) return ['ok' => false, 'age' => 0];

    $expected = hash_hmac('sha256', $ts, app_secret());
    if (!hash_equals($expected, $sig)) return ['ok' => false, 'age' => 0];

    return ['ok' => true, 'age' => time() - (int)$ts];
}
