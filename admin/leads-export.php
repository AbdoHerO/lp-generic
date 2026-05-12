<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$rows = db()->query("SELECT l.*, p.title AS product_title FROM leads l LEFT JOIN products p ON p.id=l.product_id ORDER BY l.id DESC")->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leads-' . date('Ymd-His') . '.csv');
$out = fopen('php://output', 'w');
// BOM for Excel UTF-8
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['id','date','product','offer','quantity','total','fullname','phone','city','address','notes','status','source']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'], $r['created_at'], $r['product_title'], $r['offer_label'], $r['quantity'], $r['total_price'],
        $r['fullname'], $r['phone'], $r['city'], $r['address'], $r['notes'], $r['status'], $r['source'],
    ]);
}
fclose($out);
