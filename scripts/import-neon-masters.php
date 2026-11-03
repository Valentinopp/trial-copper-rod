<?php

// Import an existing private master snapshot into a newly migrated Neon database.
// Existing user/product/transaction data is never overwritten.
require __DIR__.'/../backend/vendor/autoload.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$order = ['master_data_categories', 'master_data_units', 'master_data_production_references',
    'master_data_job_titles', 'master_data_divisions', 'master_data_suppliers', 'master_data_customers',
    'master_data_items', 'master_data_supplier_items', 'master_data_boms', 'master_data_bom_lines', 'users', 'access_profiles'];
$path = $argv[1] ?? '';
if (!$path || !is_file($path)) throw new RuntimeException('Supply the private master-data snapshot path.');
$snapshot = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
if (array_diff($order, array_keys($snapshot['tables'] ?? [])) || array_diff(array_keys($snapshot['tables'] ?? []), $order)) {
    throw new RuntimeException('Unexpected snapshot tables.');
}
$db = DB::connection('pgsql_direct');
if (!str_ends_with((string) $db->getConfig('host'), '.neon.tech')) throw new RuntimeException('Target must be a Neon database.');

$counts = $db->transaction(function () use ($db, $order, $snapshot) {
    $db->statement('LOCK TABLE '.implode(', ', $order).' IN EXCLUSIVE MODE');
    // Only defaults created by migrations may be replaced on the first import.
    $seeded = ['master_data_categories', 'master_data_units', 'master_data_production_references'];
    $tables = $db->select("select tablename from pg_tables where schemaname='public'");
    foreach ($tables as $table) {
        if ($table->tablename === 'migrations' || in_array($table->tablename, $seeded, true)) continue;
        if ($db->table($table->tablename)->exists()) throw new RuntimeException('Target is not fresh: '.$table->tablename.'. No data imported.');
    }
    $initial = [];
    foreach ($order as $table) $initial[$table] = $db->table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    $backup = storage_path('app/private/backups/neon-initial-masters-'.date('Ymd-His').'.json');
    if (!is_dir(dirname($backup))) mkdir(dirname($backup), 0700, true);
    $json = json_encode($initial, JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (file_put_contents($backup, $json, LOCK_EX) !== strlen($json) || !hash_equals(hash('sha256', $json), hash_file('sha256', $backup))) {
        throw new RuntimeException('Initial target backup verification failed.');
    }
    foreach (array_reverse($order) as $table) $db->table($table)->delete();
    $selfReferences = ['master_data_categories' => 'parent_id', 'master_data_units' => 'related_uom_id'];
    foreach ($order as $table) {
        $rows = $snapshot['tables'][$table];
        foreach (array_chunk($rows, 100) as $chunk) {
            if (isset($selfReferences[$table])) {
                $chunk = array_map(fn ($row) => array_replace($row, [$selfReferences[$table] => null]), $chunk);
            }
            $db->table($table)->insert($chunk);
        }
        if (isset($selfReferences[$table])) {
            $field = $selfReferences[$table];
            foreach ($rows as $row) if ($row[$field] !== null) $db->table($table)->where('id', $row['id'])->update([$field => $row[$field]]);
        }
        $sequence = $db->selectOne("select pg_get_serial_sequence(?, 'id') as value", [$table])->value;
        if ($sequence) $db->select('select setval(?::regclass, ?, false)', [$sequence, ((int) $db->table($table)->max('id')) + 1]);
    }
    $counts = [];
    foreach ($order as $table) {
        $actual = $db->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        if ($actual != $snapshot['tables'][$table]) throw new RuntimeException('Imported data mismatch: '.$table.'. Changes rolled back.');
        $counts[$table] = count($actual);
    }
    return $counts;
});
echo json_encode(['status' => 'MASTER_IMPORT_OK', 'verified_tables' => count($counts), 'counts' => $counts], JSON_PRETTY_PRINT).PHP_EOL;
