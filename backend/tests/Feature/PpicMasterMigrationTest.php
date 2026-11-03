<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PpicMasterMigrationTest extends TestCase
{
    protected bool $authenticateByDefault = false;

    public function test_stock_history_is_rekeyed_without_id_collision_or_new_tables(): void
    {
        $previous = DB::getDefaultConnection();
        config(['database.connections.ppic_migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('ppic_migration_test');
        try {
            foreach (glob(database_path('migrations/*.php')) as $path) {
                if (strcmp(basename($path), '2026_10_28_000014_connect_ppic_to_master_data.php') < 0) {
                    (require $path)->up();
                }
            }
            $unit = DB::table('master_data_units')->where('code', 'KG')->value('id');
            $category = DB::table('master_data_categories')->where('code', 'MAT')->value('id');
            $item = DB::table('master_data_items')->insertGetId(['code' => 'MAT-1', 'name' => 'Cathode', 'uom_id' => $unit, 'category_id' => $category]);
            // First legacy ID equals an unrelated master ID; second maps to the existing master.
            $legacy1 = DB::table('inventory_products')->insertGetId(['identity_key' => 'first', 'name' => 'Arang', 'uom' => 'KG']);
            $legacy2 = DB::table('inventory_products')->insertGetId(['identity_key' => 'second', 'name' => 'Cathode', 'uom' => 'KG']);
            $request1 = DB::table('purchase_requests')->insertGetId(['product_id' => $legacy1, 'quantity' => 3, 'required_on' => '2026-10-28']);
            $request2 = DB::table('purchase_requests')->insertGetId(['product_id' => $legacy2, 'quantity' => 20, 'required_on' => '2026-10-28']);
            $order = DB::table('purchase_orders')->insertGetId(['purchase_request_id' => $request2, 'ordered_on' => '2026-10-28', 'expected_on' => '2026-10-28']);
            DB::table('inventory_receipts')->insert(['purchase_order_id' => $order, 'product_id' => $legacy2,
                'receipt_token' => 'delivery-token', 'quantity' => 20, 'received_on' => '2026-10-28']);
            $sale = DB::table('marketing_sales_orders')->insertGetId(['submission_token' => 'sale-token', 'quantity' => 1,
                'sold_on' => '2026-10-28', 'ship_by' => '2026-10-28']);
            DB::table('ic_sale_material_requirements')->insert(['sales_order_id' => $sale, 'material_id' => $legacy1,
                'material_name' => 'Arang', 'uom' => 'KG', 'quantity' => 3]);
            DB::table('ic_sale_material_requirements')->insert(['sales_order_id' => $sale, 'material_id' => $legacy2,
                'material_name' => 'Cathode', 'uom' => 'KG', 'quantity' => 20]);
            $handoff = DB::table('production_handoffs')->insertGetId(['sales_order_id' => $sale, 'handed_over_at' => '2026-10-28 12:00:00']);
            DB::table('production_material_reservations')->insert(['handoff_id' => $handoff, 'material_id' => $legacy1, 'quantity' => 3]);
            DB::table('production_material_reservations')->insert(['handoff_id' => $handoff, 'material_id' => $legacy2, 'quantity' => 20]);
            $before = Schema::getTableListing();

            (require database_path('migrations/2026_10_28_000014_connect_ppic_to_master_data.php'))->up();

            $this->assertSame($before, Schema::getTableListing());
            $newArang = DB::table('master_data_items')->where('code', 'LEGACY-INV-'.$legacy1)->first();
            $this->assertNotNull($newArang);
            $this->assertFalse((bool) $newArang->is_active);
            $this->assertDatabaseHas('purchase_requests', ['id' => $request1, 'product_id' => $newArang->id, 'uom_id' => $unit]);
            $this->assertDatabaseHas('purchase_requests', ['id' => $request2, 'product_id' => $item]);
            $this->assertDatabaseHas('inventory_receipts', ['product_id' => $item, 'quantity' => 20, 'uom_id' => $unit]);
            $this->assertDatabaseHas('ic_sale_material_requirements', ['material_id' => $newArang->id, 'quantity' => 3, 'uom_id' => $unit]);
            $this->assertDatabaseHas('production_material_reservations', ['material_id' => $newArang->id, 'quantity' => 3, 'uom_id' => $unit]);
            $this->assertDatabaseCount('master_data_items', 2);
            foreach (['purchase_requests', 'inventory_receipts', 'ic_sale_material_requirements', 'production_material_reservations'] as $table) {
                $this->assertNotContains('inventory_products', array_column(Schema::getForeignKeys($table), 'foreign_table'));
                $this->assertContains('master_data_items', array_column(Schema::getForeignKeys($table), 'foreign_table'));
            }
        } finally {
            DB::setDefaultConnection($previous);
            DB::purge('ppic_migration_test');
        }
    }
}
