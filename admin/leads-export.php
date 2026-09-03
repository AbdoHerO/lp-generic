<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

// Export what is on screen, not the whole table. Exporting everything and
// filtering in Excel was the workaround; this removes the need for it.
$filters = [
    'phone'      => $_GET['phone']  ?? '',
    'status'     => $_GET['status'] ?? '',
    'product_id' => $_GET['product_id'] ?? '',
    'source'     => $_GET['source'] ?? '',
    'from'       => $_GET['from']   ?? '',
    'to'         => $_GET['to']     ?? '',
];

// A single page big enough to hold any realistic export, rather than a second
// query path that could disagree with the list's own filtering.
$res  = Lead::paginate($filters, 1, 100000);
$rows = $res['rows'];

$name = 'leads';
if ($filters['status'] !== '')     $name .= '-' . preg_replace('/[^a-z_]/', '', $filters['status']);
if ($filters['from'] !== '')       $name .= '-from' . preg_replace('/[^0-9-]/', '', $filters['from']);
if ($filters['to'] !== '')         $name .= '-to' . preg_replace('/[^0-9-]/', '', $filters['to']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $name . '-' . date('Ymd-His') . '.csv');

$out = fopen('php://output', 'w');
fwrite($out, "ï»¿");   // BOM, so Excel reads the Arabic correctly

fputcsv($out, ['id','date','product','offer','quantity','total','fullname','phone',
               'city','address','notes','status','source','utm_source','utm_medium',
               'utm_campaign','options']);

// Per-unit option choices, fetched in one query rather than one per row.
$options = [];
if ($rows) {
    $ids = implode(',', array_map('intval', array_column($rows, 'id')));
    foreach (db()->query("SELECT lead_id, unit_index, options_json FROM lead_items
                          WHERE lead_id IN ($ids) ORDER BY lead_id, unit_index") as $it) {
        $opts = json_decode($it['options_json'] ?? '{}', true) ?: [];
        $parts = [];
        foreach ($opts as $k => $v) $parts[] = "$k: $v";
        if ($parts) $options[$it['lead_id']][] = '#' . $it['unit_index'] . ' ' . implode(', ', $parts);
    }
}

foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'], $r['created_at'], $r['product_title'], $r['offer_label'], $r['quantity'],
        $r['total_price'], $r['fullname'], $r['phone'], $r['city'], $r['address'], $r['notes'],
        $r['status'], $r['source'], $r['utm_source'], $r['utm_medium'], $r['utm_campaign'],
        implode(' | ', $options[$r['id']] ?? []),
    ]);
}
fclose($out);
