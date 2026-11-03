<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $references = ['purchase_requests' => 'product_id', 'inventory_receipts' => 'product_id',
            'ic_sale_material_requirements' => 'material_id', 'production_material_reservations' => 'material_id'];
        Schema::table('ic_sale_material_requirements', fn (Blueprint $table) => $table->dropUnique(['sales_order_id', 'material_id']));
        Schema::table('production_material_reservations', fn (Blueprint $table) => $table->dropUnique(['handoff_id', 'material_id']));
        foreach ($references as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
                $blueprint->foreignId('uom_id')->nullable()->constrained('master_data_units')->restrictOnDelete();
                $blueprint->decimal('quantity', 20, 6)->change();
            });
        }
        $category = DB::table('master_data_categories')->where('code', 'MAT')->first();
        if (! $category) {
            $categoryId = DB::table('master_data_categories')->insertGetId(['code' => 'MAT', 'name' => 'Material', 'is_active' => true]);
        } else {
            $categoryId = $category->id;
        }
        $mapping = [];
        $used = [];
        foreach (DB::table('inventory_products')->orderBy('id')->get() as $legacy) {
            $unitId = $this->unit($legacy->uom);
            $matches = DB::table('master_data_items')->where('name', $legacy->name)->where('uom_id', $unitId)->pluck('id');
            $itemId = $matches->count() === 1 && ! in_array($matches->first(), $used, true) ? $matches->first() : DB::table('master_data_items')->insertGetId([
                'code' => 'LEGACY-INV-'.$legacy->id, 'name' => $legacy->name, 'uom_id' => $unitId,
                'category_id' => $categoryId, 'description' => 'Migrasi stok lama; periksa sebelum diaktifkan.',
                'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $mapping[$legacy->id] = ['item' => $itemId, 'unit' => $unitId];
            $used[] = $itemId;
        }
        // Update by row ID, rather than by material ID: master and legacy IDs can overlap.
        foreach ($references as $table => $column) {
            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $column, $mapping) {
                foreach ($rows as $row) {
                    $mapped = $mapping[$row->$column];
                    DB::table($table)->where('id', $row->id)->update([
                        $column => $mapped['item'],
                        'uom_id' => $table === 'ic_sale_material_requirements' ? $this->unit($row->uom) : $mapped['unit'],
                    ]);
                }
            });
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreign($column)->references('id')->on('master_data_items')->restrictOnDelete());
        }
        Schema::table('ic_sale_material_requirements', fn (Blueprint $table) => $table->unique(['sales_order_id', 'material_id']));
        Schema::table('production_material_reservations', fn (Blueprint $table) => $table->unique(['handoff_id', 'material_id']));
        Schema::table('marketing_sales_orders', function (Blueprint $table) {
            $table->foreignId('bom_id')->nullable()->constrained('master_data_boms')->restrictOnDelete();
            $table->json('bom_snapshot')->nullable();
        });
    }

    private function unit(string $code): int
    {
        $existing = DB::table('master_data_units')->where('code', $code)->value('id');

        return $existing ?? DB::table('master_data_units')->insertGetId(['code' => $code, 'name' => $code,
            'factor' => 1, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        throw new RuntimeException('Pemetaan stok ke Master Barang tidak dapat dibatalkan otomatis. Pulihkan backup untuk skema sebelumnya.');
    }
};
