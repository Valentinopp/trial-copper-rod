<?php

namespace Tests\Feature;

use App\Modules\MasterData\Livewire\BomPage;
use App\Modules\MasterData\Models\Bom;
use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Unit;
use App\Modules\MasterData\Services\BomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterBomTest extends TestCase
{
    protected \App\Enums\UserRole $authenticatedRole = \App\Enums\UserRole::SuperAdmin;

    use RefreshDatabase;

    private function item(string $code, string $category): Item
    {
        $group = Category::firstOrCreate(['name' => $category], ['code' => strtoupper($category), 'is_active' => true]);

        return Item::create(['code' => $code, 'name' => $code, 'category_id' => $group->id,
            'uom_id' => Unit::where('code', 'KG')->value('id'), 'is_active' => true]);
    }

    private function data(): array
    {
        $product = $this->item('ROD-008', 'Produk');
        $cathode = $this->item('CATHODE', 'Material');
        $charcoal = $this->item('ARANG', 'Material');

        return ['code' => 'BOM-001', 'name' => 'Copper Rod 8 mm', 'product_id' => $product->id,
            'output_quantity' => '1000', 'is_active' => true, 'notes' => 'Resep acuan',
            'lines' => [['material_id' => $cathode->id, 'quantity' => '1000'], ['material_id' => $charcoal->id, 'quantity' => '5.25']]];
    }

    public function test_create_edit_and_search_bom_from_master_items(): void
    {
        $data = $this->data();
        $this->get('/master-data/boms')->assertOk()->assertSee('Master BoM');
        $page = Livewire::test(BomPage::class)->call('openForm')->set('form', $data)
            ->call('save')->assertHasNoErrors()->assertSet('showForm', false)
            ->assertSee('BOM-001')->assertSee('ROD-008')->assertSee('2 material');
        $bom = Bom::with('lines')->firstOrFail();
        $this->assertSame('1000.000000', $bom->output_quantity);
        $this->assertSame('5.250000', $bom->lines[1]->quantity);
        $page->call('openForm', $bom->id)->assertSet('form.lines.1.material_id', $data['lines'][1]['material_id'])
            ->set('form.lines.1.quantity', '4')->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('master_data_boms', 1);
        $this->assertDatabaseCount('master_data_bom_lines', 2);
        $this->assertDatabaseHas('master_data_bom_lines', ['bom_id' => $bom->id, 'material_id' => $data['lines'][1]['material_id'], 'quantity' => 4]);
        $page->set('search', 'ROD-008')->assertSee('BOM-001')->set('search', 'Tidak ada')->assertDontSee('BOM-001');
        $this->assertDatabaseCount('inventory_receipts', 0);
    }

    public function test_duplicate_material_invalid_quantity_and_wrong_categories_are_rejected(): void
    {
        $data = $this->data();
        Livewire::test(BomPage::class)->call('openForm')->set('form', $data)
            ->set('form.lines.1.material_id', $data['lines'][0]['material_id'])
            ->set('form.output_quantity', '0')->set('form.lines.0.quantity', '-1')
            ->call('save')->assertHasErrors(['form.output_quantity', 'form.lines.0.quantity', 'form.lines.1.material_id'])
            ->assertSet('showForm', true);
        Livewire::test(BomPage::class)->call('openForm')->set('form', $data)
            ->set('form.product_id', $data['lines'][0]['material_id'])
            ->set('form.lines', [$data['lines'][1]])->call('save')->assertHasErrors('form.product_id');
        $sparepart = $this->item('SP-01', 'Sparepart');
        Livewire::test(BomPage::class)->call('openForm')->set('form', $data)
            ->set('form.lines.1.material_id', $sparepart->id)->call('save')->assertHasErrors('form.lines.1.material_id');
        $this->assertDatabaseCount('master_data_boms', 0);
    }

    public function test_inactive_references_are_rejected_and_failed_edit_preserves_saved_lines(): void
    {
        $data = $this->data();
        $bom = app(BomService::class)->save($data);
        Item::findOrFail($data['lines'][1]['material_id'])->update(['is_active' => false]);
        Livewire::test(BomPage::class)->call('openForm', $bom->id)
            ->set('form.lines.0.quantity', '99')->call('save')->assertHasErrors('form.lines.1.material_id');
        $this->assertDatabaseHas('master_data_bom_lines', ['bom_id' => $bom->id, 'material_id' => $data['lines'][0]['material_id'], 'quantity' => 1000]);
        Livewire::test(BomPage::class)->call('openForm', $bom->id)
            ->set('form.is_active', false)->call('save')->assertHasNoErrors()->assertSee('Nonaktif');
    }

    public function test_material_subcategory_and_each_items_own_unit_are_supported(): void
    {
        $data = $this->data();
        $subcategory = Category::create(['code' => 'AUX', 'name' => 'Bahan pendukung',
            'parent_id' => Item::findOrFail($data['lines'][1]['material_id'])->category_id, 'is_active' => true]);
        Item::findOrFail($data['lines'][1]['material_id'])->update(['category_id' => $subcategory->id,
            'uom_id' => Unit::where('code', 'PCS')->value('id')]);
        Livewire::test(BomPage::class)->call('openForm')->set('form', $data)->assertSee('PCS')
            ->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('master_data_bom_lines', ['material_id' => $data['lines'][1]['material_id'], 'quantity' => 5.25]);
    }

    public function test_unique_code_and_at_least_one_material_are_required(): void
    {
        $data = $this->data();
        app(BomService::class)->save($data);
        Livewire::test(BomPage::class)->call('openForm')->set('form', $data)->set('form.code', ' bom-001 ')
            ->set('form.lines', [])->call('save')->assertHasErrors(['form.code', 'form.lines']);
        Livewire::test(BomPage::class)->call('openForm')->call('addLine')->assertCount('form.lines', 2)
            ->call('removeLine', 0)->assertCount('form.lines', 1)->call('removeLine', 0)->assertCount('form.lines', 1);
    }

    public function test_output_requires_product_category_even_when_material_has_product_parent(): void
    {
        $data = $this->data();
        $product = Item::findOrFail($data['product_id']);
        $material = Item::findOrFail($data['lines'][0]['material_id']);
        $material->category->update(['parent_id' => $product->category_id]);
        $sparepart = $this->item('SP-01', 'Sparepart');
        $sparepart->category->update(['parent_id' => $product->category_id]);

        $page = Livewire::test(BomPage::class)->call('openForm');
        $this->assertSame([$product->id], $page->viewData('products')->modelKeys());
        $this->assertContains($material->id, $page->viewData('materials')->modelKeys());

        $page->set('form', $data)->set('form.product_id', $material->id)
            ->set('form.lines', [$data['lines'][1]])->call('save')->assertHasErrors('form.product_id');
        $this->assertDatabaseCount('master_data_boms', 0);
        $page->set('form', $data)->call('save')->assertHasNoErrors();
    }
}
