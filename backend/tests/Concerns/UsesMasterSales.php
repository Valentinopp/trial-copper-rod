<?php

namespace Tests\Concerns;

use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\Marketing\Services\SalesService;
use App\Modules\MasterData\Models\Bom;
use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Customer;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Unit;
use Illuminate\Support\Str;

trait UsesMasterSales
{
    private function masterMaterial(string $name = 'Copper scrap', string $uom = 'KG'): Item
    {
        $unit = Unit::firstOrCreate(['code' => $uom], ['name' => $uom, 'factor' => 1, 'is_active' => true]);
        $category = Category::firstOrCreate(['code' => 'MAT'], ['name' => 'Material', 'is_active' => true]);

        return Item::firstOrCreate(['code' => 'MAT-'.substr(hash('sha256', $name.'|'.$uom), 0, 12)],
            ['name' => $name, 'category_id' => $category->id, 'uom_id' => $unit->id, 'is_active' => true]);
    }

    private function masterProduct(Item $material, string $base = '1000', string $quantity = '1000', string $uom = 'KG'): Item
    {
        $category = Category::firstOrCreate(['code' => 'PRD'], ['name' => 'Produk', 'is_active' => true]);
        $unit = Unit::firstOrCreate(['code' => $uom], ['name' => $uom, 'factor' => 1, 'is_active' => true]);
        $product = Item::create(['code' => 'ROD-'.Str::random(8), 'name' => 'Copper Rod 8 mm', 'category_id' => $category->id,
            'uom_id' => $unit->id, 'is_active' => true]);
        $bom = Bom::create(['code' => 'BOM-'.$product->id, 'name' => 'Resep Rod', 'product_id' => $product->id,
            'output_quantity' => $base, 'is_active' => true]);
        $bom->lines()->create(['material_id' => $material->id, 'quantity' => $quantity]);

        return $product;
    }

    private function masterSale(Item $product, string $quantity = '1000'): SalesOrder
    {
        $customer = Customer::firstOrCreate(['code' => 'CUS'], ['name' => 'PT Pelanggan', 'is_active' => true]);

        return app(SalesService::class)->record(['customer_id' => $customer->id, 'product_id' => $product->id,
            'quantity' => $quantity, 'sold_on' => now()->format('Y-m-d'), 'ship_by' => now()->addDays(10)->format('Y-m-d')], (string) Str::uuid());
    }
}
