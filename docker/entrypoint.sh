#!/bin/sh
# lp_tifaw container entrypoint.
#
# Three jobs, in order: render the config the application already expects,
# wait for MySQL, and make sure the seeded admin password is not admin123 on a
# public domain.
set -eu

APP_ROOT=/var/www/html
export APP_ROOT
CONFIG_FILE="$APP_ROOT/config/config.prod.php"

# ---------------------------------------------------------------------------
# 1. Render config/config.prod.php from the environment.
#
# config/config.php already prefers this file when it exists, so nothing in the
# committed application source has to change to run in Docker. It is written at
# container start rather than baked into the image, so no password is ever
# stored in an image layer.
#
# PHP writes it instead of a shell heredoc because the store name is Arabic and
# the passwords are random base64 — var_export() escapes both correctly and a
# heredoc does not.
# ---------------------------------------------------------------------------
php -r '
$str = static function (string $k, string $default): string {
    $v = getenv($k);
    return ($v === false || $v === "") ? $default : $v;
};
$bool = static function (string $k, bool $default) : bool {
    $v = getenv($k);
    if ($v === false || $v === "") { return $default; }
    return in_array(strtolower($v), ["1", "true", "yes", "on"], true);
};

$config = [
    "app" => [
        "name" => $str("APP_NAME", "متجر تيفاو"),
        // Served at the domain root, so this is deliberately empty: Router
        // strips base_url as a prefix and "" means "strip nothing".
        "base_url" => getenv("APP_BASE_URL") === false ? "" : (string) getenv("APP_BASE_URL"),
        "env" => $str("APP_ENV", "production"),
        "timezone" => $str("APP_TIMEZONE", "Africa/Casablanca"),
    ],
    "db" => [
        "host" => $str("DB_HOST", "db"),
        "port" => (int) $str("DB_PORT", "3306"),
        "name" => $str("DB_NAME", "lp_tifaw"),
        "user" => $str("DB_USER", "lp_tifaw"),
        "pass" => $str("DB_PASSWORD", ""),
        "charset" => $str("DB_CHARSET", "utf8mb4"),
    ],
    "security" => [
        "session_name" => $str("SESSION_NAME", "LPTIFAW_SESS"),
        "cookie_secure" => $bool("COOKIE_SECURE", true),
    ],
];

$out = "<?php\n"
     . "// Generated at container start from the environment. Do not edit — every\n"
     . "// restart overwrites it. Change the values in the CloudForge environment\n"
     . "// credential instead.\n"
     . "return " . var_export($config, true) . ";\n";

file_put_contents(getenv("APP_ROOT") . "/config/config.prod.php", $out);
'

# Readable by Apache, writable by nobody.
chown root:www-data "$CONFIG_FILE"
chmod 640 "$CONFIG_FILE"

# ---------------------------------------------------------------------------
# 2. Wait for MySQL.
#
# Compose already gates startup on the db healthcheck, but a database container
# that restarts on its own outlives this one, and PDO's failure mode there is a
# fatal 500 rather than a retry.
# ---------------------------------------------------------------------------
printf 'lp_tifaw: waiting for database at %s:%s ' "${DB_HOST:-db}" "${DB_PORT:-3306}"
attempt=0
until php -r '
$h = getenv("DB_HOST") ?: "db";
$p = (int) (getenv("DB_PORT") ?: 3306);
try {
    new PDO("mysql:host={$h};port={$p}", getenv("DB_USER"), getenv("DB_PASSWORD"),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    exit(0);
} catch (Throwable $e) {
    exit(1);
}
' 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        echo
        echo "lp_tifaw: database unreachable after 2 minutes — aborting." >&2
        exit 1
    fi
    printf '.'
    sleep 2
done
echo ' ok'

# ---------------------------------------------------------------------------
# 3. Create the schema, then set the admin password.
#
# db() runs _auto_migrate(), which imports sql/schema.sql and sql/seed.sql the
# first time the admins table is absent. Doing it here rather than on the first
# HTTP request means the health gate in the pipeline is testing a migrated
# database, not racing one.
#
# seed.sql ships admin/admin123. That is fine on XAMPP and unacceptable on a
# public domain, so ADMIN_PASSWORD is applied whenever it is set. Verified
# first, so an unchanged password does not rotate the stored hash on every
# restart.
# ---------------------------------------------------------------------------
php -r '
require getenv("APP_ROOT") . "/config/database.php";

$pdo = db();

$password = getenv("ADMIN_PASSWORD");
if ($password === false || $password === "") {
    fwrite(STDERR, "lp_tifaw: ADMIN_PASSWORD not set — leaving the seeded admin password unchanged.\n");
    exit(0);
}

$username = getenv("ADMIN_USERNAME") ?: "admin";
$row = null;
$select = $pdo->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
$select->execute([$username]);
$row = $select->fetch();

if (!$row) {
    $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)")
        ->execute([$username, password_hash($password, PASSWORD_BCRYPT)]);
    echo "lp_tifaw: created admin \"{$username}\".\n";
} elseif (!password_verify($password, $row["password_hash"])) {
    $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")
        ->execute([password_hash($password, PASSWORD_BCRYPT), $row["id"]]);
    echo "lp_tifaw: updated the password for admin \"{$username}\".\n";
}
'

# The volume mount owns these paths, not the image, so re-assert on every start.
chown www-data:www-data "$APP_ROOT/uploads" /var/lib/php/sessions
chmod 755 "$APP_ROOT/uploads"

exec "$@"
