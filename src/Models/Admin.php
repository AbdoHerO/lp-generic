<?php
/**
 * Admin — panel accounts and what each one may do.
 *
 * Two roles, deliberately:
 *   admin — everything
 *   agent — the order queue only: view orders, change status, add notes
 *
 * An agent is the person who phones customers to confirm. They need the leads
 * screens and nothing else; today they can also delete a product and cascade
 * away every order attached to it. Two roles removes that without inventing a
 * permission matrix nobody will maintain.
 */
class Admin {
    public const ROLES = ['admin' => 'مدير', 'agent' => 'موظف طلبات'];

    /** Pages an agent may open. Everything else is admin-only. */
    private const AGENT_PAGES = ['dashboard', 'leads', 'lead-detail', 'reports', 'drafts'];

    public static function all(): array {
        return db()->query(
            "SELECT id, username, role, status, last_login_at, created_at
             FROM admins ORDER BY role, username"
        )->fetchAll();
    }

    public static function find(int $id): ?array {
        $st = db()->prepare("SELECT * FROM admins WHERE id = :i LIMIT 1");
        $st->execute([':i' => $id]);
        return $st->fetch() ?: null;
    }

    public static function findByUsername(string $username): ?array {
        $st = db()->prepare("SELECT * FROM admins WHERE username = :u LIMIT 1");
        $st->execute([':u' => $username]);
        return $st->fetch() ?: null;
    }

    public static function role(): string {
        return $_SESSION['admin_role'] ?? 'admin';
    }

    public static function isAdmin(): bool {
        return self::role() === 'admin';
    }

    public static function canAccess(string $view): bool {
        return self::isAdmin() || in_array($view, self::AGENT_PAGES, true);
    }

    /**
     * How many enabled admins remain besides this one.
     *
     * Used to refuse the last-admin footgun: demoting or disabling the only
     * remaining admin locks everyone out of products, pixels and settings with
     * no way back through the UI.
     */
    public static function otherActiveAdmins(int $excludeId): int {
        $st = db()->prepare("SELECT COUNT(*) FROM admins WHERE role = 'admin' AND status = 1 AND id <> :i");
        $st->execute([':i' => $excludeId]);
        return (int)$st->fetchColumn();
    }

    /**
     * @return array{ok: bool, error: ?string, id: ?int}
     */
    public static function save(array $d, ?int $id = null): array {
        $username = strtolower(trim((string)($d['username'] ?? '')));
        $username = preg_replace('/[^a-z0-9._-]/', '', $username) ?? '';
        $role     = in_array($d['role'] ?? '', array_keys(self::ROLES), true) ? $d['role'] : 'agent';
        $status   = !empty($d['status']) ? 1 : 0;
        $password = (string)($d['password'] ?? '');

        if (strlen($username) < 3) {
            return ['ok' => false, 'error' => 'اسم المستخدم قصير جداً (3 أحرف على الأقل)', 'id' => null];
        }
        if (!$id && strlen($password) < 8) {
            return ['ok' => false, 'error' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل', 'id' => null];
        }
        if ($password !== '' && strlen($password) < 8) {
            return ['ok' => false, 'error' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل', 'id' => null];
        }

        // Do not let the panel be locked out of its own settings.
        if ($id) {
            $current = self::find($id);
            if ($current && $current['role'] === 'admin'
                && ($role !== 'admin' || !$status)
                && self::otherActiveAdmins($id) === 0) {
                return ['ok' => false, 'error' => 'لا يمكن تعطيل أو تخفيض آخر حساب مدير', 'id' => null];
            }
        }

        try {
            if ($id) {
                $sql = "UPDATE admins SET username = :u, role = :r, status = :s";
                $params = [':u' => $username, ':r' => $role, ':s' => $status, ':i' => $id];
                if ($password !== '') {
                    $sql .= ", password_hash = :p";
                    $params[':p'] = password_hash($password, PASSWORD_DEFAULT);
                }
                db()->prepare($sql . " WHERE id = :i")->execute($params);
            } else {
                db()->prepare("INSERT INTO admins (username, password_hash, role, status)
                               VALUES (:u, :p, :r, :s)")
                    ->execute([
                        ':u' => $username,
                        ':p' => password_hash($password, PASSWORD_DEFAULT),
                        ':r' => $role,
                        ':s' => $status,
                    ]);
                $id = (int)db()->lastInsertId();
            }
            return ['ok' => true, 'error' => null, 'id' => $id];
        } catch (PDOException $e) {
            return [
                'ok'    => false,
                'error' => (int)$e->getCode() === 23000 ? 'اسم المستخدم مستعمل بالفعل' : 'تعذر الحفظ',
                'id'    => null,
            ];
        }
    }

    /** @return array{ok: bool, error: ?string} */
    public static function delete(int $id, int $currentAdminId): array {
        if ($id === $currentAdminId) {
            return ['ok' => false, 'error' => 'لا يمكنك حذف حسابك الحالي'];
        }
        $row = self::find($id);
        if (!$row) return ['ok' => false, 'error' => 'الحساب غير موجود'];
        if ($row['role'] === 'admin' && self::otherActiveAdmins($id) === 0) {
            return ['ok' => false, 'error' => 'لا يمكن حذف آخر حساب مدير'];
        }
        db()->prepare("DELETE FROM admins WHERE id = :i")->execute([':i' => $id]);
        return ['ok' => true, 'error' => null];
    }

    public static function touchLogin(int $id): void {
        try {
            db()->prepare("UPDATE admins SET last_login_at = NOW() WHERE id = :i")->execute([':i' => $id]);
        } catch (Throwable $e) {
            // The column arrives with a migration; a login must not depend on it.
        }
    }
}
