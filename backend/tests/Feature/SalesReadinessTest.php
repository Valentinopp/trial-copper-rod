<?php

namespace Tests\Feature;

use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\MasterData\Models\Bom;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Unit;
use App\Modules\Ppic\Livewire\InventoryControlPage;
use App\Modules\Ppic\Livewire\ProductionPlanPage;
use App\Modules\Ppic\Models\MaterialReservation;
use App\Modules\Ppic\Models\ProductionHandoff;
use App\Modules\Ppic\Models\PurchaseRequest;
use App\Modules\Ppic\Services\SalesReadinessService;
use App\Modules\Purchasing\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\UsesMasterSales;
use Tests\TestCase;

class SalesReadinessTest extends TestCase
{
    use RefreshDatabase;
    use UsesMasterSales;

    private function material(string $name = 'Copper scrap'): Item
    {
        return $this->masterMaterial($name);
    }

    private function product(Item $material, string $base = '1000', string $quantity = '1000'): Item
    {
        return $this->masterProduct($material, $base, $quantity);
    }

    private function sale(Item $product, string $quantity = '1000'): SalesOrder
    {
        return $this->masterSale($product, $quantity);
    }

    private function receive(PurchaseRequest $request): void
    {
        $service = app(ProcurementService::class);
        $order = $service->approve($request->id, now()->format('Y-m-d'), now()->format('Y-m-d'));
        $service->receive($order->id, (string) $request->quantity, now()->format('Y-m-d'), (string) Str::uuid());
    }

    private function stock(Item $material, string $quantity): void
    {
        $this->receive(app(ProcurementService::class)->submit([
            'product_id' => $material->id,
            'quantity' => $quantity, 'required_on' => now()->format('Y-m-d'),
        ]));
    }

    public function test_shortages_create_linked_requests_then_received_materials_enable_handover(): void
    {
        $copper = $this->material();
        $charcoal = $this->material('Arang');
        $product = $this->product($copper);
        $product->boms()->firstOrFail()->lines()->create(['material_id' => $charcoal->id, 'quantity' => '10']);
        $sale = $this->sale($product, '2000');
        $this->stock($copper, '1500');
        $this->stock($charcoal, '15');
        $ic = Livewire::test(InventoryControlPage::class)->call('setTab', 'sales')
            ->assertSee($sale->reference)->call('checkSale', $sale->id)->assertHasNoErrors()->assertSee('Bahan belum mencukupi')
            ->call('requestShortages')->assertHasNoErrors();
        $requests = PurchaseRequest::where('sales_order_id', $sale->id)->orderBy('id')->get();
        $this->assertCount(2, $requests);
        $this->assertSame('500.000000', $requests->firstWhere('product_id', $copper->id)->quantity);
        $this->assertSame('5.000000', $requests->firstWhere('product_id', $charcoal->id)->quantity);
        $ic->call('requestShortages')->assertHasNoErrors();
        $this->assertSame(2, PurchaseRequest::where('sales_order_id', $sale->id)->count());
        $ic->call('handOverSale')->assertHasErrors('readiness');
        $this->assertDatabaseCount('production_handoffs', 0);
        foreach ($requests as $request) {
            $this->receive($request);
        }
        $ic->call('checkSale', $sale->id)->assertSee('Bahan mencukupi')->call('handOverSale')->assertHasNoErrors()->assertSee('Sudah diserahkan');
        $this->assertDatabaseCount('production_handoffs', 1);
        $this->assertDatabaseCount('production_material_reservations', 2);
        Livewire::test(ProductionPlanPage::class)->assertSee($sale->reference)->assertSee('Menunggu perencanaan')->assertSee('Arang');
        $ic->call('setTab', 'stock')->assertSee('Dicadangkan')->assertSee('2.000,000000');
    }

    public function test_reserved_material_cannot_be_promised_to_another_sale_and_handover_is_idempotent(): void
    {
        $material = $this->material();
        $product = $this->product($material);
        $first = $this->sale($product, '60');
        $second = $this->sale($product, '60');
        $this->stock($material, '100');
        $service = app(SalesReadinessService::class);
        $handoff = $service->handOver($first->id);
        $this->assertSame($handoff->id, $service->handOver($first->id)->id);
        $this->assertDatabaseCount('production_material_reservations', 1);
        $service->prepare($second->id);
        $check = $service->assess($second->id);
        $this->assertSame('40.000000', $check['rows'][0]['available']);
        $this->assertSame('20.000000', $check['rows'][0]['shortage']);
        $this->assertFalse($check['ready']);
        $this->assertTrue($service->assess($first->id)['ready']);
        try {
            $service->handOver($second->id);
            $this->fail('Shared stock must not be reserved twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('readiness', $exception->errors());
        }
        $this->assertSame(1, ProductionHandoff::count());
        $this->assertSame(60.0, (float) MaterialReservation::sum('quantity'));
    }

    public function test_requirement_rounds_up_and_remains_stable_after_catalog_recipe_changes(): void
    {
        $material = $this->material();
        $product = $this->product($material, '3', '1');
        $sale = $this->sale($product, '1');
        $this->stock($material, '0.333');
        $service = app(SalesReadinessService::class);
        $service->prepare($sale->id);
        $product->boms()->firstOrFail()->lines()->update(['quantity' => '100']);
        $service->prepare($sale->id);
        $check = $service->assess($sale->id);
        $this->assertSame('0.333334', $check['rows'][0]['required']);
        $this->assertSame('0.000334', $check['rows'][0]['shortage']);
        $this->assertFalse($check['ready']);
    }

    public function test_pending_purchase_order_is_not_stock_and_invalid_request_date_is_rejected(): void
    {
        $sale = $this->sale($this->product($this->material()));
        $service = app(SalesReadinessService::class);
        $ic = Livewire::test(InventoryControlPage::class)->call('checkSale', $sale->id)
            ->set('materials_required_on', now()->subDay()->format('Y-m-d'))
            ->call('requestShortages')->assertHasErrors('materials_required_on');
        $this->assertDatabaseCount('purchase_requests', 0);
        $ic->set('materials_required_on', now()->format('Y-m-d'))->call('requestShortages')->assertHasNoErrors();
        $request = PurchaseRequest::firstOrFail();
        app(ProcurementService::class)->approve($request->id, now()->format('Y-m-d'), now()->addDay()->format('Y-m-d'));
        $check = $service->assess($sale->id);
        $this->assertFalse($check['ready']);
        $this->assertSame('1000.000000', $check['rows'][0]['outstanding']);
        $this->assertSame('0.000000', $check['rows'][0]['requestable']);
        $this->assertSame(0, $service->requestShortages($sale->id, now()->format('Y-m-d')));
    }

    public function test_missing_bom_or_changed_product_unit_blocks_check(): void
    {
        $product = $this->product($this->material());
        $sale = $this->sale($product);
        $originalUnit = $product->uom_id;
        $product->update(['uom_id' => Unit::where('code', 'PCS')->value('id')]);
        Livewire::test(InventoryControlPage::class)->call('checkSale', $sale->id)->assertHasErrors('readiness')->assertSet('selectedSaleId', null);
        $product->update(['uom_id' => $originalUnit]);
        $product->boms()->firstOrFail()->lines()->delete();
        Livewire::test(InventoryControlPage::class)->call('checkSale', $sale->id)->assertHasErrors('readiness');
        $this->assertDatabaseCount('ic_sale_material_requirements', 0);
        $this->assertDatabaseCount('production_handoffs', 0);
    }

    public function test_multiple_boms_require_selection_and_cannot_use_another_products_bom(): void
    {
        $material = $this->material();
        $product = $this->product($material);
        $sale = $this->sale($product, '2');
        $alternate = Bom::create(['code' => 'ALT', 'name' => 'Alternatif',
            'product_id' => $product->id, 'output_quantity' => '1', 'is_active' => true]);
        $alternate->lines()->create(['material_id' => $material->id, 'quantity' => '3']);
        $other = $this->product($material);
        $page = Livewire::test(InventoryControlPage::class)->call('setTab', 'sales')
            ->assertSee('Alternatif')->call('checkSale', $sale->id)->assertHasErrors('readiness');
        $page->set('bomSelections.'.$sale->id, $other->boms()->first()->id)
            ->call('checkSale', $sale->id)->assertHasErrors('readiness');
        $page->set('bomSelections.'.$sale->id, $alternate->id)->call('checkSale', $sale->id)->assertHasNoErrors();
        $this->assertSame($alternate->id, $sale->fresh()->bom_id);
        $this->assertSame('6.000000', $sale->materialRequirements()->first()->quantity);
        $alternate->update(['name' => 'Nama baru', 'is_active' => false]);
        $alternate->lines()->update(['quantity' => '99']);
        app(SalesReadinessService::class)->prepare($sale->id);
        $this->assertSame('Alternatif', $sale->fresh()->bom_snapshot['name']);
        $this->assertSame('6.000000', $sale->materialRequirements()->first()->quantity);
        $page->set('bomSelections.'.$sale->id, $product->boms()->orderBy('id')->first()->id)
            ->call('checkSale', $sale->id)->assertHasErrors('readiness');
    }

    public function test_ton_output_and_kg_material_use_bom_ratio_without_extra_conversion(): void
    {
        $material = $this->material();
        $product = $this->masterProduct($material, '1', '1000', 'TON');
        $sale = $this->sale($product, '2.5');
        $service = app(SalesReadinessService::class);
        $service->prepare($sale->id);
        $requirement = $sale->materialRequirements()->firstOrFail();
        $this->assertSame('2500.000000', $requirement->quantity);
        $this->assertSame('KG', $requirement->uom);
        $this->assertSame('TON', $sale->fresh()->bom_snapshot['uom']);
        $this->stock($material, '2500');
        $this->assertTrue($service->assess($sale->id)['ready']);
        $service->handOver($sale->id);
        Livewire::test(ProductionPlanPage::class)->assertSee('TON')->assertSee('PT Pelanggan')->assertSee('Copper Rod 8 mm');
    }

    public function test_inactive_material_blocks_preparation_and_different_unit_stock_is_not_available(): void
    {
        $material = $this->material();
        $sale = $this->sale($this->product($material), '1');
        $material->update(['is_active' => false]);
        Livewire::test(InventoryControlPage::class)->call('checkSale', $sale->id)->assertHasErrors('readiness');
        $this->assertDatabaseCount('ic_sale_material_requirements', 0);
        $material->update(['is_active' => true]);
        $service = app(SalesReadinessService::class);
        $service->prepare($sale->id);
        $material->update(['uom_id' => Unit::where('code', 'PCS')->value('id')]);
        $this->stock($material, '100');
        $this->assertSame('0.000000', $service->assess($sale->id)['rows'][0]['available']);
        $this->assertFalse($service->assess($sale->id)['ready']);
    }

    public function test_master_item_unit_cannot_change_after_bom_usage(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['role' => \App\Enums\UserRole::SuperAdmin]));
        $material = $this->material();
        $this->product($material);
        $originalUnit = $material->uom_id;
        try {
            app(\App\Modules\MasterData\Services\MasterDataService::class)->saveItem(array_replace(
                $material->only(['code', 'name', 'category_id', 'uom_id', 'description', 'is_active']),
                ['uom_id' => \App\Modules\MasterData\Models\Unit::where('code', 'PCS')->value('id')]
            ), $material->id);
            $this->fail('Changing the unit would reinterpret the existing recipe.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('uom_id', $exception->errors());
        }
        $this->assertSame($originalUnit, $material->fresh()->uom_id);
    }
}
