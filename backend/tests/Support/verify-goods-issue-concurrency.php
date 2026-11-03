<?php

// Isolated PostgreSQL verification. No existing application tables are changed.
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Division;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Unit;
use App\Modules\Ppic\Models\InventoryIssue;
use App\Modules\Ppic\Services\InventoryIssueService;
use App\Modules\Ppic\Services\InventoryStockService;
use App\Modules\Purchasing\Services\ProcurementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'pgsql', 'hashing.bcrypt.rounds' => 4]);
$worker = ($argv[1] ?? '') === 'worker';
$schema = $worker ? $argv[2] : 'codex_goods_issue_'.bin2hex(random_bytes(6));
if (! preg_match('/^codex_goods_issue_[a-f0-9]{12}$/', $schema)) {
    throw new RuntimeException('Unexpected schema');
}
if (! $worker) {
    DB::statement('CREATE SCHEMA "'.$schema.'"');
}
config(['database.connections.pgsql.search_path' => $schema]);
DB::purge('pgsql');
if ($worker) {
    auth()->loginUsingId((int) $argv[3]);
    file_put_contents($argv[6], 'ready');
    try {
        $service = app(InventoryIssueService::class);
        if ($argv[4] === 'reverse') {
            $service->reverse((int) $argv[5], 'Isolated concurrent reversal');
        } else {
            $service->confirm((int) $argv[5]);
        }
        echo 'ok';
        exit(0);
    } catch (ValidationException) {
        echo 'denied';
        exit(2);
    }
}
$scratch = $backend.'/storage/framework/testing/'.$schema;
mkdir($scratch, 0777, true);
function race(array $ids, string $action, int $itemId, int $userId): array
{
    global $schema,$scratch;
    DB::beginTransaction();
    Item::whereKey($itemId)->lockForUpdate()->firstOrFail();
    $children = [];
    try {
        foreach ($ids as $n => $id) {
            $ready = $scratch.'/'.$action.'-'.$id.'-'.$n.'.ready';
            $pipes = [];
            $process = proc_open([PHP_BINARY, __FILE__, 'worker', $schema, (string) $userId, $action, (string) $id, $ready],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start worker');
            }
            fclose($pipes[0]);
            $children[] = [$process, $pipes, $ready];
        }
        $deadline = microtime(true) + 15;
        while (count(array_filter($children, fn ($child) => is_file($child[2]))) !== count($children)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not reach barrier');
            }
            usleep(50000);
        }
        // Both transactions are now ready to contend for the same stock row.
        DB::commit();
        $results = [];
        foreach ($children as [$process,$pipes]) {
            $out = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            if (! in_array($exit, [0, 2], true)) {
                throw new RuntimeException('Worker failed: '.$error.' '.$out);
            }
            $results[] = trim($out);
        }

        return $results;
    } finally {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }
    }
}
try {
    Artisan::call('migrate', ['--force' => true]);
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    auth()->login($user);
    $unit = Unit::create(['code' => 'TEST', 'name' => 'Test', 'factor' => 1, 'is_active' => true]);
    $category = Category::where('code', 'SP')->firstOrFail();
    $item = Item::create(['code' => 'ISOLATED-BEARING', 'name' => 'Test Bearing', 'uom_id' => $unit->id, 'category_id' => $category->id, 'is_active' => true]);
    $division = Division::create(['code' => 'ISOLATED', 'name' => 'Test Maintenance', 'is_active' => true]);
    $procurement = app(ProcurementService::class);
    $request = $procurement->submit(['product_id' => $item->id, 'quantity' => '10', 'required_on' => now()->format('Y-m-d')]);
    $order = $procurement->approve($request->id, now()->format('Y-m-d'), now()->format('Y-m-d'));
    $procurement->receive($order->id, '10', now()->format('Y-m-d'), (string) Str::uuid());
    $data = ['issued_on' => now()->format('Y-m-d'), 'purpose' => 'maintenance', 'division_id' => $division->id, 'recipient' => 'Test',
        'lines' => [['product_id' => $item->id, 'quantity' => '7']]];
    $service = app(InventoryIssueService::class);
    $a = $service->save($data, (string) Str::uuid());
    $b = $service->save($data, (string) Str::uuid());
    $results = race([$a->id, $b->id], 'confirm', $item->id, $user->id);
    sort($results);
    if ($results !== ['denied', 'ok']) {
        throw new RuntimeException('Expected one successful competing issue');
    }
    if (app(InventoryStockService::class)->balance($item->id, $unit->id)['available'] !== '3.000000') {
        throw new RuntimeException('Unexpected stock');
    }
    echo "PASS: concurrent competing issues -> one confirmed, one denied; stock 3.000000\n";
    $confirmed = InventoryIssue::where('status', 'confirmed')->firstOrFail();
    $results = race([$confirmed->id, $confirmed->id], 'confirm', $item->id, $user->id);
    if ($results !== ['ok', 'ok'] || app(InventoryStockService::class)->balance($item->id, $unit->id)['available'] !== '3.000000') {
        throw new RuntimeException('Retry changed stock');
    }
    echo "PASS: concurrent confirmation retries -> stock remains 3.000000\n";
    $results = race([$confirmed->id, $confirmed->id], 'reverse', $item->id, $user->id);
    if ($results !== ['ok', 'ok'] || app(InventoryStockService::class)->balance($item->id, $unit->id)['available'] !== '10.000000') {
        throw new RuntimeException('Concurrent reversal changed stock twice');
    }
    echo "PASS: concurrent reversal retries -> stock restored once to 10.000000\n";
} finally {
    // Exact generated schema only, never public/application tables.
    DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
    foreach (glob($scratch.'/*.ready') as $file) {
        unlink($file);
    }
    rmdir($scratch);
    echo "Isolated verification schema removed.\n";
}
