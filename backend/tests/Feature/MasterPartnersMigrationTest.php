<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterPartnersMigrationTest extends TestCase
{
    protected bool $authenticateByDefault = false;

    public function test_partner_migration_up_and_down_preserve_existing_items_and_catalog(): void
    {
        $previous = DB::getDefaultConnection();
        config(['database.connections.partner_migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('partner_migration_test');
        try {
            $target = '2026_10_28_000010_create_supplier_customer_masters.php';
            foreach (glob(database_path('migrations/*.php')) as $path) {
                if (strcmp(basename($path), $target) < 0) {
                    (require $path)->up();
                }
            }
            DB::table('inventory_products')->insert(['identity_key' => hash('sha256', 'legacy'), 'name' => 'Material lama', 'uom' => 'KG']);
            $itemId = DB::table('master_data_items')->insertGetId([
                'code' => 'MAT-001', 'name' => 'Cathode', 'category_id' => DB::table('master_data_categories')->value('id'),
                'uom_id' => DB::table('master_data_units')->value('id'),
            ]);
            $before = [];
            foreach (['inventory_products', 'master_data_items', 'master_data_categories', 'master_data_units'] as $table) {
                $before[$table] = ['columns' => Schema::getColumnListing($table), 'rows' => DB::table($table)->get()->toArray()];
            }
            $migration = require database_path('migrations/'.$target);
            $migration->up();
            $this->assertSame(0, DB::table('master_data_suppliers')->count());
            $this->assertSame(0, DB::table('master_data_customers')->count());
            DB::table('master_data_suppliers')->insert(['id' => 1, 'code' => 'SUP-001', 'name' => 'Supplier']);
            DB::table('master_data_supplier_items')->insert(['supplier_id' => 1, 'item_id' => $itemId]);
            DB::table('master_data_customers')->insert(['code' => 'CUS-001', 'name' => 'Customer']);
            $migration->down();
            foreach (['master_data_supplier_items', 'master_data_suppliers', 'master_data_customers'] as $table) {
                $this->assertFalse(Schema::hasTable($table));
            }
            foreach ($before as $table => $state) {
                $this->assertSame($state['columns'], Schema::getColumnListing($table));
                $this->assertEquals($state['rows'], DB::table($table)->get()->toArray());
            }
        } finally {
            DB::setDefaultConnection($previous);
            DB::purge('partner_migration_test');
        }
    }
}
