<?php

namespace Tests\Feature;

use App\Modules\Marketing\Livewire\SalesPage;
use App\Modules\Marketing\Models\Product;
use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\Marketing\Services\SalesService;
use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Customer;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingSalesTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        $customer = Customer::create(['code' => 'CUS-01', 'name' => 'PT Pelanggan', 'is_active' => true]);
        $category = Category::create(['code' => 'PRD', 'name' => 'Produk', 'is_active' => true]);
        $unit = Unit::create(['code' => 'TON', 'name' => 'Ton', 'factor' => 1, 'is_active' => true]);
        $product = Item::create(['code' => 'ROD-008', 'name' => 'Copper Rod 8 mm', 'category_id' => $category->id,
            'uom_id' => $unit->id, 'is_active' => true]);

        return ['customer_id' => (string) $customer->id, 'product_id' => (string) $product->id, 'quantity' => '2.500125',
            'sold_on' => now()->format('Y-m-d'), 'ship_by' => now()->addDays(5)->format('Y-m-d'), 'notes' => 'Kirim sesuai jadwal.'];
    }

    private function form(array $data)
    {
        $page = Livewire::test(SalesPage::class)->call('openSale');
        foreach ($data as $field => $value) {
            $page->set($field, $value);
        }

        return $page;
    }

    public function test_sale_uses_master_customer_product_and_its_unit_without_conversion(): void
    {
        $data = $this->data();
        $this->form($data)->assertSee('TON')->call('saveSale')->assertHasNoErrors()->assertSet('showForm', false)
            ->assertSee('SO-000001')->assertSee('CUS-01')->assertSee('ROD-008')->assertSee('2,500125')->assertSee('TON');
        $sale = SalesOrder::firstOrFail();
        $this->assertEquals($data['customer_id'], $sale->customer_id);
        $this->assertEquals($data['product_id'], $sale->product_id);
        $this->assertSame('2.500125', $sale->quantity);
        $this->assertSame('TON', $sale->unit_snapshot['code']);
        $this->assertDatabaseCount('purchase_requests', 0);
        $this->assertDatabaseCount('inventory_receipts', 0);
    }

    public function test_sale_snapshots_survive_master_changes_and_ignore_forged_fields(): void
    {
        $data = $this->data();
        $sale = app(SalesService::class)->record($data + ['uom_id' => 99999, 'customer_name' => 'Forged'], (string) Str::uuid());
        Customer::findOrFail($data['customer_id'])->update(['name' => 'Customer Diubah']);
        Item::findOrFail($data['product_id'])->update(['name' => 'Produk Diubah']);
        $sale->unit->update(['code' => 'TON-BARU']);
        Livewire::test(SalesPage::class)->assertSee('PT Pelanggan')->assertSee('Copper Rod 8 mm')->assertSee('TON')
            ->assertDontSee('Customer Diubah')->assertDontSee('Produk Diubah')->assertDontSee('TON-BARU')->assertDontSee('Forged');
    }

    public function test_non_product_and_inactive_customer_or_product_are_rejected(): void
    {
        $data = $this->data();
        Customer::findOrFail($data['customer_id'])->update(['is_active' => false]);
        $this->form($data)->call('saveSale')->assertHasErrors('customer_id')->assertSet('showForm', true);
        Customer::findOrFail($data['customer_id'])->update(['is_active' => true]);
        $product = Item::findOrFail($data['product_id']);
        $product->update(['is_active' => false]);
        $this->form($data)->call('saveSale')->assertHasErrors('product_id');
        $product->update(['is_active' => true, 'category_id' => Category::where('code', 'MAT')->value('id')]);
        $this->form($data)->call('saveSale')->assertHasErrors('product_id');
        $this->assertDatabaseCount('marketing_sales_orders', 0);
    }

    public function test_inactive_category_or_unit_cannot_be_sold(): void
    {
        $data = $this->data();
        $product = Item::findOrFail($data['product_id']);
        $product->category->update(['is_active' => false]);
        $this->form($data)->call('saveSale')->assertHasErrors('product_id');
        $product->category->update(['is_active' => true]);
        $product->unit->update(['is_active' => false]);
        $this->form($data)->call('saveSale')->assertHasErrors('product_id');
        $this->assertDatabaseCount('marketing_sales_orders', 0);
    }

    public function test_invalid_quantity_dates_and_missing_master_references_do_not_save(): void
    {
        $data = $this->data();
        $this->form(array_replace($data, ['customer_id' => '99999', 'product_id' => '99999', 'quantity' => '0',
            'ship_by' => now()->subDay()->format('Y-m-d')]))->call('saveSale')
            ->assertHasErrors(['customer_id', 'product_id', 'quantity', 'ship_by']);
        $this->form(array_replace($data, ['sold_on' => now()->addDay()->format('Y-m-d'), 'quantity' => '1.0000001']))
            ->call('saveSale')->assertHasErrors(['sold_on', 'quantity']);
        $this->assertDatabaseCount('marketing_sales_orders', 0);
    }

    public function test_retried_submission_preserves_original_sale_even_after_archiving_master(): void
    {
        $data = $this->data();
        $service = app(SalesService::class);
        $token = (string) Str::uuid();
        $first = $service->record($data, $token);
        Item::findOrFail($data['product_id'])->update(['is_active' => false]);
        $second = $service->record(array_replace($data, ['quantity' => '99']), $token);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('2.500125', $second->quantity);
        $this->assertDatabaseCount('marketing_sales_orders', 1);
    }

    public function test_empty_master_links_and_cancelling_does_not_save(): void
    {
        Product::create(['name' => 'Produk Lama', 'diameter_mm' => 8, 'purity_percent' => 99, 'bom_output_kg' => 1000]);
        Livewire::test(SalesPage::class)->call('openSale')->assertSee('Buka Master Barang')->assertSee('Buka Master Customer')
            ->assertDontSee('Produk Lama')->call('closeSale')->assertSet('showForm', false)
            ->assertDontSeeHtml('wire:submit="saveSale"');
        $this->assertDatabaseCount('marketing_sales_orders', 0);
    }
}
