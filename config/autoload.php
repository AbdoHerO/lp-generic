<?php
/**
 * Class autoloader.
 *
 * Every controller used to `require_once` its models by hand, which is how a
 * refactor turns into "Class not found" on one page and not the others. The
 * classes are flat and unnamespaced, so a directory scan is enough — no
 * Composer, no vendor directory, nothing to install.
 *
 * The explicit require_once calls elsewhere are deliberately left in place:
 * they are harmless (require_once is idempotent) and they document each file's
 * real dependencies. This is the safety net, not a replacement for them.
 */

spl_autoload_register(static function (string $class): void {
    // No namespaces in this codebase; anything with a separator is not ours.
    if (str_contains($class, '\\')) return;

    // Only bare class names, so a crafted string can never escape the roots.
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) return;

    static $roots = null;
    if ($roots === null) {
        $base  = dirname(__DIR__);
        $roots = [$base . '/src/Models/', $base . '/src/Controllers/', $base . '/src/'];
    }

    foreach ($roots as $dir) {
        $file = $dir . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
