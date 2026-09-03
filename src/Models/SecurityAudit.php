<?php
/**
 * SecurityAudit — the handful of checks worth showing an operator every day.
 *
 * Deliberately small and cheap: it runs on the dashboard, so it must not add a
 * noticeable query cost. Each finding names the exact fix, because a warning
 * that does not say what to do gets dismissed and never revisited.
 */
class SecurityAudit {
    /**
     * The SheetDB token that was published in `_diag.php` on the live domain.
     * Anyone who fetched that file still holds it, so it must be rotated even
     * though the file is gone.
     */
    private const LEAKED_SHEETDB_TOKEN = 'c4pbl6r3lwr8r0bossphcnv02tpic1dqlp40ifla';

    /** @return list<array{level:string, title:string, detail:string, action:?string}> */
    public static function findings(): array {
        $out = [];

        // 1. The shipped admin password.
        try {
            $rows = db()->query("SELECT username, password_hash FROM admins")->fetchAll();
            foreach ($rows as $r) {
                if (password_verify('admin123', $r['password_hash'])) {
                    $out[] = [
                        'level'  => 'critical',
                        'title'  => 'كلمة مرور الإدارة ما زالت الافتراضية',
                        'detail' => 'الحساب «' . $r['username'] . '» يستعمل admin123، وهي منشورة في ملفات المشروع.',
                        'action' => 'admin/settings.php',
                    ];
                }
            }
        } catch (Throwable $e) { /* never block the dashboard on an audit */ }

        // 2. The leaked SheetDB token.
        $token = trim((string)settings_get('sheetdb_token', ''));
        if ($token !== '' && hash_equals(self::LEAKED_SHEETDB_TOKEN, $token)) {
            $out[] = [
                'level'  => 'critical',
                'title'  => 'مفتاح SheetDB مكشوف — غيّره الآن',
                'detail' => 'هذا المفتاح كان منشوراً في ملف _diag.php على الدومين العمومي. '
                          . 'أنشئ مفتاحاً جديداً من لوحة SheetDB ثم ضعه هنا.',
                'action' => 'admin/settings.php',
            ];
        }

        // 3. A Secure cookie over plain HTTP means nobody can stay signed in.
        global $CONFIG;
        if (!empty($CONFIG['security']['cookie_secure']) && !request_is_https()) {
            $out[] = [
                'level'  => 'warning',
                'title'  => 'إعداد الكوكيز لا يطابق البروتوكول',
                'detail' => 'cookie_secure مفعّل لكن الاتصال الحالي HTTP وليس HTTPS، '
                          . 'وهذا يمنع بقاء الجلسة. اضبط COOKIE_SECURE=false محلياً.',
                'action' => null,
            ];
        }

        // 4. Errors on screen in production leak paths and query fragments.
        if (($CONFIG['app']['env'] ?? '') === 'development' && request_is_https()) {
            $out[] = [
                'level'  => 'warning',
                'title'  => 'وضع التطوير مفعّل على موقع مباشر',
                'detail' => 'APP_ENV=development يعرض رسائل الأخطاء للزوار. اضبطه على production.',
                'action' => null,
            ];
        }

        // 5. A landing page that is live with no pixel at all is money spent blind.
        try {
            require_once __DIR__ . '/Pixel.php';
            $noPixel = [];
            $st = db()->query("SELECT id, title, fb_pixel_id, tt_pixel_id FROM products WHERE status = 1");
            foreach ($st as $p) {
                $px = Pixel::resolve($p);
                if (!$px['facebook'] && !$px['tiktok']) $noPixel[] = $p['title'];
            }
            if ($noPixel) {
                $out[] = [
                    'level'  => 'info',
                    'title'  => count($noPixel) . ' صفحة هبوط نشطة بدون أي بكسل',
                    'detail' => implode('، ', array_slice($noPixel, 0, 5))
                              . (count($noPixel) > 5 ? ' وغيرها' : '')
                              . ' — لن تُسجَّل أي تحويلات لهذه الصفحات.',
                    'action' => 'admin/pixels.php',
                ];
            }
        } catch (Throwable $e) { /* pixels table may predate the migration */ }

        return $out;
    }
}
