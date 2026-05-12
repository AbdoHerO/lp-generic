<?php
class Settings {
    public static function all(): array {
        $rows = db()->query("SELECT k, v FROM settings")->fetchAll();
        $out = [];
        foreach ($rows as $r) $out[$r['k']] = $r['v'];
        return $out;
    }
    public static function set(string $k, ?string $v): void {
        $st = db()->prepare("INSERT INTO settings (k,v) VALUES (:k,:v)
            ON DUPLICATE KEY UPDATE v=VALUES(v)");
        $st->execute([':k'=>$k, ':v'=>$v]);
    }
}
