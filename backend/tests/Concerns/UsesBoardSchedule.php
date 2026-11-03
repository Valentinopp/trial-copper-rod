<?php

namespace Tests\Concerns;

use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\ProductionReference;
use App\Modules\MasterData\Models\Unit;
use App\Modules\Ppic\Models\ProductionHandoff;
use App\Modules\Ppic\Models\ProductionSchedule;
use Illuminate\Support\Str;

trait UsesBoardSchedule
{
    private function boardSchedule(string $diameter = '8'): ProductionSchedule
    {
        $rod = ProductionReference::where('section', 'rod-specifications')->where('diameter_mm', is_numeric($diameter) ? $diameter : '8')->first()
            ?? ProductionReference::where('section', 'rod-specifications')->where('diameter_mm', 8)->firstOrFail();
        $unit = Unit::firstOrCreate(['code' => 'KG'], ['name' => 'Kilogram', 'factor' => 1, 'is_active' => true]);
        $category = Category::firstOrCreate(['code' => 'PRD'], ['name' => 'Produk', 'is_active' => true]);
        $product = Item::create(['code' => 'TEST-'.Str::uuid(), 'name' => 'Cu Rod Test', 'category_id' => $category->id,
            'uom_id' => $unit->id, 'rod_specification_id' => $rod->id, 'is_active' => true]);
        $sale = SalesOrder::create(['submission_token' => (string) Str::uuid(), 'product_id' => $product->id, 'uom_id' => $unit->id,
            'customer_snapshot' => ['code' => 'TEST', 'name' => 'Test Customer'], 'product_snapshot' => ['code' => $product->code, 'name' => $product->name],
            'unit_snapshot' => ['code' => 'KG', 'name' => 'Kilogram'], 'quantity' => '1000000', 'sold_on' => now()->toDateString(), 'ship_by' => now()->addDays(10)->toDateString()]);
        $handoff = ProductionHandoff::create(['sales_order_id' => $sale->id, 'handed_over_at' => now()]);
        return ProductionSchedule::create(['handoff_id' => $handoff->id, 'submission_token' => (string) Str::uuid(),
            'planned_on' => now()->toDateString(), 'quantity' => '1000000', 'status' => 'scheduled', 'rod_specification_id' => $rod->id,
            'rod_diameter_mm' => $rod->diameter_mm, 'rod_weight_ton_per_meter' => $rod->weight_ton_per_meter, 'kg_per_unit' => '1']);
    }
}
