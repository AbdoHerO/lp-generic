<?php
require __DIR__ . '/_bootstrap.php';
admin_require_admin();

$filters = [
    'entity'   => $_GET['entity']   ?? '',
    'action'   => $_GET['action']   ?? '',
    'admin_id' => $_GET['admin_id'] ?? '',
];

admin_render('activity', [
    'title'   => 'سجل النشاط',
    'res'     => Activity::paginate($filters, max(1, (int)($_GET['page'] ?? 1)), 50),
    'filters' => $filters,
    'admins'  => Admin::all(),
    'logs'    => Log::recent(15, Log::WARNING),
]);
