<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketingSalesMigrationTest extends TestCase
{
    protected bool $authenticateByDefault = false;

    #[\PHPUnit\Framework\Attributes\DataProvider('snapshotSchemas')]
    public function test_consolidation_preserves_existing_orders_and_imports_master_sales(bool $existingSnapshot): void
    {
        $previous = DB::getDefaultConnection();
        config(['database.connections.sales_migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('sales_migration_test');
        try {
            foreach (glob(database_path('migrations/*.php')) as $path) {
                if (strcmp(basename($path), '2026_10_28_000013_consolidate_marketing_sales_orders.php') < 0) {
                    (require $path)->up();
                }
            }
            $oldProduct = DB::table('marketing_products')->insertGetId(['name' => 'Rod lama', 'diameter_mm' => 8, 'purity_percent' => 99.99, 'bom_output_kg' => 1000]);
            $oldOrder = DB::table('marketing_sales_orders')->insertGetId(['submission_token' => 'old-token', 'product_id' => $oldProduct,
                'customer_name' => 'Customer lama', 'product_name' => 'Rod lama', 'diameter_mm' => 8, 'purity_percent' => 99.99,
                'quantity_kg' => '2500.125', 'sold_on' => '2026-10-28', 'ship_by' => '2026-10-30']);
            DB::table('production_handoffs')->insert(['sales_order_id' => $oldOrder, 'handed_over_at' => '2026-10-28 12:00:00']);
            if ($existingSnapshot) {
                Schema::table('marketing_sales_orders', fn ($table) => $table->json('product_snapshot')->nullable());
                DB::table('marketing_sales_orders')->where('id', $oldOrder)->update(['product_snapshot' => json_encode(['description' => 'Spesifikasi tersimpan'])]);
            }
            $category = DB::table('master_data_categories')->insertGetId(['code' => 'PRD', 'name' => 'Produk']);
            $unit = DB::table('master_data_units')->where('code', 'PCS')->value('id');
            $product = DB::table('master_data_items')->insertGetId(['code' => 'ROD', 'name' => 'Rod baru', 'category_id' => $category, 'uom_id' => $unit]);
            $customer = DB::table('master_data_customers')->insertGetId(['code' => 'CUS', 'name' => 'Customer baru']);
            DB::table('marketing_sales')->insert(['submission_token' => 'new-token', 'customer_id' => $customer, 'product_id' => $product, 'uom_id' => $unit,
                'customer_snapshot' => json_encode(['code' => 'CUS', 'name' => 'Customer baru']),
                'product_snapshot' => json_encode(['code' => 'ROD', 'name' => 'Rod baru']),
                'unit_snapshot' => json_encode(['code' => 'PCS', 'name' => 'Piece']),
                'quantity' => '1.123456', 'sold_on' => '2026-10-28', 'ship_by' => '2026-10-30']);

            (require database_path('migrations/2026_10_28_000013_consolidate_marketing_sales_orders.php'))->up();

            $this->assertFalse(Schema::hasTable('marketing_sales'));
            $this->assertFalse(Schema::hasColumn('marketing_sales_orders', 'quantity_kg'));
            $this->assertDatabaseCount('marketing_sales_orders', 2);
            $this->assertDatabaseHas('production_handoffs', ['sales_order_id' => $oldOrder]);
            $old = DB::table('marketing_sales_orders')->find($oldOrder);
            $this->assertNull($old->product_id);
            $this->assertSame('Rod lama', json_decode($old->product_snapshot, true)['name']);
            if ($existingSnapshot) {
                $this->assertSame('Spesifikasi tersimpan', json_decode($old->product_snapshot, true)['description']);
            }
            $this->assertEquals(2500.125, $old->quantity);
            $this->assertDatabaseHas('marketing_sales_orders', ['submission_token' => 'new-token', 'product_id' => $product, 'customer_id' => $customer, 'quantity' => '1.123456']);
            $foreignKeys = Schema::getForeignKeys('marketing_sales_orders');
            $this->assertContains('master_data_items', array_column($foreignKeys, 'foreign_table'));
            $this->assertNotContains('marketing_products', array_column($foreignKeys, 'foreign_table'));
        } finally {
            DB::setDefaultConnection($previous);
            DB::purge('sales_migration_test');
        }
    }

    public static function snapshotSchemas(): array
    {
        return ['standard' => [false], 'with-existing-snapshot' => [true]];
    }
}
