<?php
require __DIR__ . '/_bootstrap.php';
admin_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $keys = ['store_name','whatsapp','support_phone','facebook_handle','fb_pixel_id','tiktok_pixel_id','gtm_id','ga_id','capi_enabled',
             'sheetdb_enabled','sheetdb_url','sheetdb_token','accent_color',
             'show_footer_phone','show_footer_whatsapp','show_footer_facebook','protect_images','capture_drafts',
             'policy_privacy','policy_terms','policy_refund'];
    $checkboxKeys = ['sheetdb_enabled','capi_enabled','protect_images','capture_drafts',
                     'show_footer_phone','show_footer_whatsapp','show_footer_facebook'];

    // Naming the changed keys is what makes the trail useful — "settings were
    // saved" tells nobody why the pixel stopped firing last Tuesday.
    $changed = [];
    foreach ($keys as $k) {
        $v = $_POST[$k] ?? '';
        if (in_array($k, $checkboxKeys, true)) $v = isset($_POST[$k]) ? '1' : '0';
        if ((string)settings_get($k, '') !== (string)$v) $changed[] = $k;
        Settings::set($k, $v);
    }
    // Brand assets. Each accepts an upload or an external URL, and keeps the
    // current value when neither is supplied.
    foreach ([
        'store_logo'       => 'store_logo_file',
        'store_logo_light' => 'store_logo_light_file',
        'store_favicon'    => 'store_favicon_file',
    ] as $key => $field) {
        $v = admin_upload_image($field, settings_get($key), $key . '_url');
        if ($v) Settings::set($key, $v);
    }

    // One click back to the shipped tujjar.store artwork.
    if (!empty($_POST['brand_reset'])) {
        Settings::set('store_logo',       'public/assets/img/logo.svg');
        Settings::set('store_logo_light', 'public/assets/img/logo-light.svg');
        Settings::set('store_favicon',    'public/assets/img/favicon.svg');
    }

    // Change password (optional)
    if (!empty($_POST['new_password'])) {
        $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $st = db()->prepare("UPDATE admins SET password_hash=:h WHERE id=:i");
        $st->execute([':h'=>$hash, ':i'=>admin_id()]);
    }
    if (!empty($_POST['new_password'])) $changed[] = 'admin_password';
    Activity::log('update', 'settings', null,
                  $changed ? implode(', ', array_slice($changed, 0, 12)) : 'لا تغيير');

    redirect(base_url('admin/settings.php?saved=1'));
}

admin_render('settings', [
    'title' => 'الإعدادات',
    'settings' => Settings::all(),
    'saved' => isset($_GET['saved']),
]);
