<?php

namespace Tests\Feature;

use App\Modules\MasterData\Models\Item;
use App\Modules\Ppic\Livewire\AttentionIndicator;
use App\Modules\Ppic\Livewire\InventoryControlPage;
use App\Modules\Ppic\Services\PpicAttentionService;
use App\Modules\Ppic\Services\SalesReadinessService;
use App\Modules\Purchasing\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\UsesMasterSales;
use Tests\TestCase;

class PpicAttentionTest extends TestCase
{
    use RefreshDatabase;
    use UsesMasterSales;

    private function material(): Item
    {
        return $this->masterMaterial('Copper');
    }

    public function test_no_red_indicators_when_there_are_no_pending_tasks(): void
    {
        foreach (['sales', 'incoming', 'inventory'] as $kind) {
            Livewire::test(AttentionIndicator::class, ['kind' => $kind])->assertDontSeeHtml('data-ppic-attention=');
        }
        $this->get('/ppic/inventory-control')->assertOk()->assertDontSee('data-ppic-attention=', false);
    }

    public function test_sale_indicator_remains_after_check_and_clears_after_handover(): void
    {
        $material = $this->material();
        $product = $this->masterProduct($material);
        $indicator = Livewire::test(AttentionIndicator::class, ['kind' => 'sales'])->assertDontSeeHtml('data-ppic-attention=');
        $sale = $this->masterSale($product, '10');
        $indicator->dispatch('ppic-attention-updated')->assertSeeHtml('data-ppic-attention="sales"');
        $this->get('/marketing/sales')->assertOk()->assertSee('data-ppic-attention="inventory"', false);
        $readiness = app(SalesReadinessService::class);
        $readiness->prepare($sale->id);
        $this->assertSame(1, app(PpicAttentionService::class)->for('sales')['count']);
        $procurement = app(ProcurementService::class);
        $request = $procurement->submit(['product_id' => $material->id, 'quantity' => '10', 'required_on' => now()->format('Y-m-d')]);
        $order = $procurement->approve($request->id, now()->format('Y-m-d'), now()->format('Y-m-d'));
        $procurement->receive($order->id, '10', now()->format('Y-m-d'), (string) Str::uuid());
        Livewire::test(InventoryControlPage::class)->call('checkSale', $sale->id)->call('handOverSale')
            ->assertHasNoErrors()->assertDispatched('ppic-attention-updated');
        $indicator->dispatch('ppic-attention-updated')->assertDontSeeHtml('data-ppic-attention=');
        $this->assertSame(0, app(PpicAttentionService::class)->for('inventory')['count']);
    }

    public function test_partial_receipt_keeps_incoming_badge_until_fully_received(): void
    {
        $material = $this->material();
        $procurement = app(ProcurementService::class);
        $request = $procurement->submit(['product_id' => $material->id, 'quantity' => '10', 'required_on' => now()->format('Y-m-d')]);
        $this->assertSame(0, app(PpicAttentionService::class)->for('incoming')['count']);
        $order = $procurement->approve($request->id, now()->format('Y-m-d'), now()->format('Y-m-d'));
        $indicator = Livewire::test(AttentionIndicator::class, ['kind' => 'incoming'])->assertSeeHtml('data-ppic-attention="incoming"');
        $procurement->receive($order->id, '4', now()->format('Y-m-d'), (string) Str::uuid());
        $indicator->dispatch('ppic-attention-updated')->assertSeeHtml('data-ppic-attention="incoming"');
        $this->assertSame(1, app(PpicAttentionService::class)->for('inventory')['count']);
        Livewire::test(InventoryControlPage::class)->call('setTab', 'in')->call('selectReceipt', $order->id)
            ->call('receiveGoods')->assertHasNoErrors()->assertDispatched('ppic-attention-updated');
        $indicator->dispatch('ppic-attention-updated')->assertDontSeeHtml('data-ppic-attention=');
        $this->assertSame(0, app(PpicAttentionService::class)->for('incoming')['count']);
    }
}
