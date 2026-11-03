<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StandaloneMasterDataMigrationTest extends TestCase
{
    protected bool $authenticateByDefault = false;

    public function test_up_and_down_only_change_master_data_tables(): void
    {
        $previous = DB::getDefaultConnection();
        config(['database.connections.master_isolation_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('master_isolation_test');
        try {
            foreach (glob(database_path('migrations/*.php')) as $path) {
                if (strcmp(basename($path), '2026_10_28_000009_create_standalone_master_data.php') < 0) {
                    (require $path)->up();
                }
            }
            DB::table('inventory_products')->insert(['id' => 3, 'identity_key' => hash('sha256', 'legacy'), 'name' => 'Material lama', 'uom' => 'KG']);
            DB::table('marketing_products')->insert(['id' => 4, 'name' => 'Rod lama', 'diameter_mm' => 8, 'purity_percent' => 99.99, 'bom_output_kg' => 1000]);
            $materialBefore = DB::table('inventory_products')->get()->toArray();
            $productsBefore = DB::table('marketing_products')->get()->toArray();
            $columnsBefore = Schema::getColumnListing('inventory_products');
            $migration = require database_path('migrations/2026_10_28_000009_create_standalone_master_data.php');
            $migration->up();
            $this->assertSame(3, DB::table('master_data_categories')->count());
            $this->assertSame(4, DB::table('master_data_units')->count());
            $this->assertSame(0, DB::table('master_data_items')->count());
            $this->assertEquals($materialBefore, DB::table('inventory_products')->get()->toArray());
            $this->assertEquals($productsBefore, DB::table('marketing_products')->get()->toArray());
            $migration->down();
            $this->assertFalse(Schema::hasTable('master_data_items'));
            $this->assertFalse(Schema::hasTable('master_data_units'));
            $this->assertFalse(Schema::hasTable('master_data_categories'));
            $this->assertSame($columnsBefore, Schema::getColumnListing('inventory_products'));
            $this->assertEquals($materialBefore, DB::table('inventory_products')->get()->toArray());
            $this->assertEquals($productsBefore, DB::table('marketing_products')->get()->toArray());
        } finally {
            DB::setDefaultConnection($previous);
            DB::purge('master_isolation_test');
        }
    }
}
