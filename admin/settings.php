<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $keys = ['store_name','whatsapp','support_phone','facebook_handle','fb_pixel_id','tiktok_pixel_id','gtm_id','ga_id',
             'sheetdb_enabled','sheetdb_url','sheetdb_token','accent_color',
             'show_footer_phone','show_footer_whatsapp','show_footer_facebook',
             'policy_privacy','policy_terms','policy_refund'];
    $checkboxKeys = ['sheetdb_enabled','show_footer_phone','show_footer_whatsapp','show_footer_facebook'];
    foreach ($keys as $k) {
        $v = $_POST[$k] ?? '';
        if (in_array($k, $checkboxKeys, true)) $v = isset($_POST[$k]) ? '1' : '0';
        Settings::set($k, $v);
    }
    // Logo upload
    $logo = admin_upload_image('store_logo_file', settings_get('store_logo'));
    if ($logo) Settings::set('store_logo', $logo);

    // Change password (optional)
    if (!empty($_POST['new_password'])) {
        $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $st = db()->prepare("UPDATE admins SET password_hash=:h WHERE id=:i");
        $st->execute([':h'=>$hash, ':i'=>admin_id()]);
    }
    redirect(base_url('admin/settings.php?saved=1'));
}

admin_render('settings', [
    'title' => 'الإعدادات',
    'settings' => Settings::all(),
    'saved' => isset($_GET['saved']),
]);
