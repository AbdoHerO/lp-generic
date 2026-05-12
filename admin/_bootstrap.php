<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../src/Models/Product.php';
require_once __DIR__ . '/../src/Models/Lead.php';
require_once __DIR__ . '/../src/Models/Settings.php';

function admin_require_auth(): void {
    if (empty($_SESSION['admin_id'])) {
        redirect(base_url('admin/login.php'));
    }
}
function admin_id(): ?int { return $_SESSION['admin_id'] ?? null; }
function admin_username(): ?string { return $_SESSION['admin_username'] ?? null; }

function admin_require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        die('CSRF token mismatch');
    }
}

function admin_render(string $view, array $data = []): void {
    $data['_view'] = $view;
    extract($data, EXTR_SKIP);
    $store = settings_get('store_name', 'متجر');
    $title = $data['title'] ?? 'لوحة التحكم';
    ob_start();
    include __DIR__ . '/views/' . $view . '.php';
    $content = ob_get_clean();
    include __DIR__ . '/views/_layout.php';
}

// Upload helper — accepts a file upload OR a URL text field.
// Priority: file upload wins over URL field wins over $existing.
function admin_upload_image(string $fileField, ?string $existing = null, ?string $urlField = null): ?string {
    // 1. Try file upload
    if (!empty($_FILES[$fileField]['tmp_name']) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES[$fileField];
        if ($f['size'] > 5 * 1024 * 1024) goto try_url; // 5 MB limit
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($f['tmp_name']);
        if (!isset($allowed[$mime])) goto try_url;
        $ext  = $allowed[$mime];
        $name = 'p_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = __DIR__ . '/../uploads/' . $name;
        if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
        if (move_uploaded_file($f['tmp_name'], $dest)) return 'uploads/' . $name;
    }
    try_url:
    // 2. Try URL field
    if ($urlField && !empty($_POST[$urlField])) {
        $url = trim($_POST[$urlField]);
        if (filter_var($url, FILTER_VALIDATE_URL)) return $url;
    }
    // 3. Keep existing value
    return $existing;
}

// Upload multiple images — accepts uploaded files AND/OR newline-separated URLs.
function admin_upload_multi(string $fileField, ?string $urlField = null): array {
    $out    = [];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $finfo  = new finfo(FILEINFO_MIME_TYPE);

    // A. Newline-separated URLs
    if ($urlField && !empty($_POST[$urlField])) {
        foreach (explode("\n", $_POST[$urlField]) as $line) {
            $url = trim($line);
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) $out[] = $url;
        }
    }

    // B. File uploads (input[multiple])
    if (!empty($_FILES[$fileField]['tmp_name'])) {
        $files = $_FILES[$fileField];
        $names = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error'])    ? $files['error']    : [$files['error']];
        $sizes  = is_array($files['size'])     ? $files['size']     : [$files['size']];

        foreach ($names as $i => $tmp) {
            if ($errors[$i] !== UPLOAD_ERR_OK)       continue;
            if ($sizes[$i]  > 5 * 1024 * 1024)       continue;
            $mime = $finfo->file($tmp);
            if (!isset($allowed[$mime]))              continue;
            $ext  = $allowed[$mime];
            $name = 'p_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = __DIR__ . '/../uploads/' . $name;
            if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
            if (move_uploaded_file($tmp, $dest)) $out[] = 'uploads/' . $name;
        }
    }

    return $out;
}
