<?php
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/superadmin_sql_dump_lib.php';

$fn = 'mydentalph_full_backup_' . date('Y-m-d_His') . '.sql';
header('Content-Type: application/octet-stream; charset=utf-8');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fn) . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo "-- MyDental platform full SQL backup\n";
echo '-- Generated (server time): ' . date('c') . "\n";

superadmin_stream_mysql_dump($pdo);
