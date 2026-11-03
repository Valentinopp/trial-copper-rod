<?php

namespace Tests\Feature;

use App\Modules\MasterData\Models\Item;
use App\Modules\Ppic\Livewire\InventoryControlPage;
use App\Modules\Ppic\Models\InventoryReceipt;
use App\Modules\Ppic\Models\PurchaseRequest;
use App\Modules\Purchasing\Livewire\PengajuanPage;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\UsesMasterSales;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    use RefreshDatabase;
    use UsesMasterSales;

    private function product(string $uom = 'KG'): Item
    {
        return $this->masterMaterial('Kawat tembaga', $uom);
    }

    private function submit(array $overrides = []): PurchaseRequest
    {
        return app(ProcurementService::class)->submit(array_merge([
            'product_id' => $this->product()->id, 'quantity' => '10.500',
            'required_on' => now()->addDays(5)->format('Y-m-d'),
        ], $overrides));
    }

    private function order(): PurchaseOrder
    {
        return app(ProcurementService::class)->approve($this->submit()->id, now()->format('Y-m-d'), now()->addDays(3)->format('Y-m-d'));
    }

    public function test_ic_to_purchasing_to_partial_receipts_and_stock(): void
    {
        $product = $this->product();
        $ic = Livewire::test(InventoryControlPage::class)
            ->set('product_id', (string) $product->id)->set('quantity', '10.500')
            ->set('required_on', now()->addDays(5)->format('Y-m-d'))
            ->call('submitRequest')->assertHasNoErrors()->assertSee('PR-000001');
        $request = PurchaseRequest::firstOrFail();
        $this->assertSame(0, InventoryReceipt::count());
        $purchasing = Livewire::test(PengajuanPage::class)->assertSee('Kawat tembaga')
            ->call('selectRequest', $request->id)
            ->set('ordered_on', now()->format('Y-m-d'))->set('expected_on', now()->addDays(3)->format('Y-m-d'))
            ->call('approveOrder')->assertHasNoErrors()->assertRedirect(route('purchasing.orders'));
        $order = PurchaseOrder::firstOrFail();
        $this->assertSame(0, InventoryReceipt::count());
        $ic->call('setTab', 'in')->assertSee('Terima barang')
            ->call('selectReceipt', $order->id)->set('received_quantity', '4.250')
            ->call('receiveGoods')->assertHasNoErrors()->assertSee('Diterima sebagian');
        $this->assertSame(6.25, $order->fresh()->remaining);
        $ic->call('selectReceipt', $order->id)->call('receiveGoods')->assertHasNoErrors()->assertSee('Diterima lengkap')
            ->call('setTab', 'stock')->assertSee('Kawat tembaga')->assertSee('10,500');
        $this->assertSame(10.5, (float) InventoryReceipt::sum('quantity'));
        $this->assertSame(0.0, $order->fresh()->remaining);
        $this->get(route('purchasing.orders'))->assertOk()->assertSee('Diterima lengkap');
    }

    public function test_invalid_request_does_not_create_product_or_request(): void
    {
        Livewire::test(InventoryControlPage::class)->set('quantity', '0')
            ->set('required_on', now()->subDay()->format('Y-m-d'))->call('submitRequest')
            ->assertHasErrors(['product_id', 'quantity', 'required_on']);
        $this->assertDatabaseCount('purchase_requests', 0);
        $this->assertDatabaseCount('inventory_products', 0);
    }

    public function test_approval_validates_dates_and_cannot_duplicate_order(): void
    {
        $request = $this->submit();
        $component = Livewire::test(PengajuanPage::class)->call('selectRequest', $request->id)
            ->set('expected_on', now()->subDay()->format('Y-m-d'))->call('approveOrder')
            ->assertHasErrors('expected_on');
        $this->assertDatabaseCount('purchase_orders', 0);
        $component->set('expected_on', now()->format('Y-m-d'))->call('approveOrder')->assertHasNoErrors();
        try {
            app(ProcurementService::class)->approve($request->id, now()->format('Y-m-d'), now()->format('Y-m-d'));
            $this->fail('Duplicate order must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ordered_on', $exception->errors());
        }
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    public function test_receiving_more_than_remaining_does_not_change_stock(): void
    {
        $order = $this->order();
        Livewire::test(InventoryControlPage::class)->call('setTab', 'in')
            ->call('selectReceipt', $order->id)->set('received_quantity', '11')
            ->call('receiveGoods')->assertHasErrors('received_quantity');
        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertSame(10.5, $order->fresh()->remaining);
    }

    public function test_retried_receipt_does_not_double_stock(): void
    {
        $order = $this->order();
        $token = (string) Str::uuid();
        $service = app(ProcurementService::class);
        $first = $service->receive($order->id, '3.500', now()->format('Y-m-d'), $token);
        $second = $service->receive($order->id, '3.500', now()->format('Y-m-d'), $token);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('inventory_receipts', 1);
        $this->assertSame(3.5, (float) InventoryReceipt::sum('quantity'));
    }

    public function test_receipt_cannot_be_recorded_before_purchase_date(): void
    {
        $order = $this->order();
        Livewire::test(InventoryControlPage::class)->call('setTab', 'in')
            ->call('selectReceipt', $order->id)->set('received_on', now()->subDay()->format('Y-m-d'))
            ->call('receiveGoods')->assertHasErrors('received_on');
        $this->assertDatabaseCount('inventory_receipts', 0);
    }

    public function test_request_uses_selected_master_record_and_ignores_client_supplied_name_and_uom(): void
    {
        $kg = $this->product();
        $roll = $this->product('ROLL');
        $request = $this->submit(['product_id' => $roll->id, 'product_name' => 'Barang palsu', 'uom' => 'KG']);
        $this->assertSame($roll->id, $request->product_id);
        $this->assertSame('ROLL', $request->unit->code);
        $this->assertSame('Kawat tembaga', $request->product->name);
        $this->assertSame('KG', $kg->fresh()->unit->code);
        $this->assertSame(2, Item::count());
    }

    public function test_selecting_master_updates_quantity_unit_and_reset_clears_selection(): void
    {
        $kg = $this->product();
        $roll = $this->product('ROLL');
        Livewire::test(InventoryControlPage::class)->assertSeeHtml('wire:model.live="product_id"')
            ->set('product_id', (string) $kg->id)->assertSeeHtml('aria-live="polite">KG</span>')
            ->set('product_id', (string) $roll->id)->assertSeeHtml('aria-live="polite">ROLL</span>')
            ->set('quantity', '3')->set('required_on', now()->format('Y-m-d'))
            ->call('submitRequest')->assertHasNoErrors()->assertSet('product_id', '');
        $this->assertDatabaseHas('purchase_requests', ['product_id' => $roll->id, 'quantity' => 3]);
        $this->assertDatabaseCount('master_data_items', 2);
    }

    public function test_invalid_master_id_is_rejected_without_creating_product(): void
    {
        Livewire::test(InventoryControlPage::class)->set('product_id', '999999')->set('quantity', '10')
            ->set('required_on', now()->format('Y-m-d'))->call('submitRequest')->assertHasErrors('product_id');
        $this->assertDatabaseCount('purchase_requests', 0);
        $this->assertDatabaseCount('inventory_products', 0);
    }

    public function test_micro_quantity_receipt_preserves_precision_and_rejects_overreceipt(): void
    {
        $request = $this->submit(['quantity' => '0.000003']);
        $service = app(ProcurementService::class);
        $order = $service->approve($request->id, now()->format('Y-m-d'), now()->format('Y-m-d'));
        $service->receive($order->id, '0.000001', now()->format('Y-m-d'), (string) Str::uuid());
        $this->assertSame(0.000002, $order->fresh()->remaining);
        Livewire::test(InventoryControlPage::class)->call('selectReceipt', $order->id)->set('received_quantity', '0.000003')
            ->call('receiveGoods')->assertHasErrors('received_quantity');
        $service->receive($order->id, '0.000002', now()->format('Y-m-d'), (string) Str::uuid());
        $this->assertSame(0.0, $order->fresh()->remaining);
    }
}
