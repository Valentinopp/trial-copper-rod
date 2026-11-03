<?php

namespace Tests\Feature;

use App\Modules\MasterData\Livewire\MasterDataPage;
use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    protected \App\Enums\UserRole $authenticatedRole = \App\Enums\UserRole::SuperAdmin;

    use RefreshDatabase;

    private function itemData(array $overrides = []): array
    {
        return array_replace([
            'code' => 'MAT-CATHODE', 'name' => 'Copper Cathode',
            'category_id' => Category::where('code', 'MAT')->value('id'),
            'uom_id' => Unit::where('code', 'KG')->value('id'),
            'description' => 'Barang standar dari supplier.', 'is_active' => true,
        ], $overrides);
    }

    public function test_item_create_edit_deactivate_and_search_remain_generic(): void
    {
        $page = Livewire::test(MasterDataPage::class)->assertDontSeeHtml('wire:submit="save"')
            ->call('openForm', 'item')->set('form', $this->itemData())->call('save')
            ->assertHasNoErrors()->assertSet('modal', '')->assertSee('MAT-CATHODE');
        $item = Item::firstOrFail();
        $this->assertSame('Barang standar dari supplier.', $item->description);
        $page->call('openForm', 'item', $item->id)->set('form.name', 'Cathode Standar')
            ->set('form.description', 'Referensi spesifikasi pembelian')->set('form.is_active', false)
            ->call('save')->assertHasNoErrors()->assertSee('Nonaktif');
        $this->assertSame('Cathode Standar', $item->fresh()->name);
        $this->assertFalse($item->fresh()->is_active);
        $page->set('search', 'cathode')->assertSee('MAT-CATHODE')->set('search', 'tidak-ada')->assertDontSee('MAT-CATHODE');
        $page->call('setTab', 'units')->assertSet('search', '')->assertSee('Kilogram');
    }

    public function test_required_fields_and_case_insensitive_unique_codes_are_validated(): void
    {
        Livewire::test(MasterDataPage::class)->call('openForm', 'item')->call('save')
            ->assertHasErrors(['form.code', 'form.name', 'form.category_id', 'form.uom_id'])->assertSet('modal', 'item');
        $page = Livewire::test(MasterDataPage::class)->call('openForm', 'item')
            ->set('form', $this->itemData(['code' => ' mat-cathode ', 'name' => '  Copper   Cathode  ']))
            ->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('master_data_items', ['code' => 'MAT-CATHODE', 'name' => 'Copper Cathode']);
        $page->call('openForm', 'item')->set('form', $this->itemData(['code' => 'mat-cathode']))->call('save')->assertHasErrors('form.code');
        $page->set('form.code', 'MAT-CATHODE-2')->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('master_data_items', 2);
    }

    public function test_item_can_link_to_a_rod_specification_once_and_rejects_wrong_sections(): void
    {
        $rod = \App\Modules\MasterData\Models\ProductionReference::where('section', 'rod-specifications')->where('diameter_mm', 8)->firstOrFail();
        $page = Livewire::test(MasterDataPage::class)->call('openForm', 'item')
            ->set('form', $this->itemData(['rod_specification_id' => $rod->id]))->call('save')->assertHasNoErrors();
        $item = Item::firstOrFail();
        $this->assertSame($rod->id, $item->rodSpecification->id);
        $wrong = \App\Modules\MasterData\Models\ProductionReference::where('section', 'take-ups')->firstOrFail();
        $page->call('openForm', 'item', $item->id)->set('form.rod_specification_id', $wrong->id)
            ->call('save')->assertHasErrors('form.rod_specification_id');
        $page->set('form.rod_specification_id', '')->call('save')->assertHasNoErrors();
        $this->assertNull($item->fresh()->rod_specification_id);
    }

    public function test_categories_can_be_added_and_edited_without_business_type_rules(): void
    {
        $material = Category::where('code', 'MAT')->firstOrFail();
        $page = Livewire::test(MasterDataPage::class)->call('setTab', 'categories')->call('openForm', 'category')
            ->set('form', ['code' => 'SM', 'name' => 'Bahan pendukung', 'parent_id' => $material->id, 'description' => '', 'is_active' => true])
            ->call('save')->assertHasNoErrors()->assertSee('Bahan pendukung');
        $child = Category::where('code', 'SM')->firstOrFail();
        $page->call('openForm', 'category', $child->id)->set('form.name', 'Material furnace')->call('save')->assertHasNoErrors();
        $page->call('openForm', 'category', $material->id)->set('form.parent_id', (string) $child->id)->call('save')->assertHasErrors('form.parent_id');
        $page->set('form.parent_id', (string) $material->id)->call('save')->assertHasErrors('form.parent_id');
        $this->assertNull($material->fresh()->parent_id);
    }

    public function test_uom_related_factor_is_configuration_not_tonnage_logic(): void
    {
        $pcs = Unit::where('code', 'PCS')->firstOrFail();
        $page = Livewire::test(MasterDataPage::class)->call('setTab', 'units')->call('openForm', 'unit')
            ->set('form', ['code' => 'BOX', 'name' => 'Box', 'related_uom_id' => $pcs->id, 'factor' => '12', 'is_active' => true])
            ->call('save')->assertHasNoErrors()->assertSee('BOX');
        $box = Unit::where('code', 'BOX')->firstOrFail();
        $this->assertSame('12.000000000', $box->factor);
        $this->assertSame($pcs->id, $box->related_uom_id);
        $page->call('openForm', 'unit', $box->id)->set('form.factor', '24')->call('save')->assertHasNoErrors();
        $page->call('openForm', 'unit', $pcs->id)->set('form.related_uom_id', (string) $box->id)->call('save')->assertHasErrors('form.related_uom_id');
        $page->call('openForm', 'unit')->set('form', ['code' => 'BAD', 'name' => 'Invalid', 'related_uom_id' => '', 'factor' => '0', 'is_active' => true])
            ->call('save')->assertHasErrors('form.factor');
        $this->assertDatabaseMissing('master_data_units', ['code' => 'MT']);
    }

    public function test_inactive_and_missing_references_cannot_create_active_items(): void
    {
        $category = Category::where('code', 'MAT')->firstOrFail();
        $unit = Unit::where('code', 'KG')->firstOrFail();
        $category->update(['is_active' => false]);
        $page = Livewire::test(MasterDataPage::class)->call('openForm', 'item')->set('form', $this->itemData())
            ->call('save')->assertHasErrors('form.category_id');
        $category->update(['is_active' => true]);
        $unit->update(['is_active' => false]);
        $page->call('save')->assertHasErrors('form.uom_id');
        $page->set('form.uom_id', '999999')->call('save')->assertHasErrors('form.uom_id');
        $this->assertDatabaseCount('master_data_items', 0);
    }

    public function test_master_item_writes_do_not_create_or_modify_operational_records(): void
    {
        DB::table('inventory_products')->insert(['identity_key' => hash('sha256', 'legacy-cathode'), 'name' => 'Cathode katalog lama', 'uom' => 'KG']);
        $tables = ['inventory_products', 'marketing_products', 'marketing_bom_items', 'marketing_sales_orders', 'purchase_requests', 'purchase_orders', 'inventory_receipts', 'production_handoffs', 'production_material_reservations', 'board_entries'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = ['columns' => Schema::getColumnListing($table), 'rows' => DB::table($table)->get()->toArray()];
        }
        Livewire::test(MasterDataPage::class)->call('openForm', 'item')->set('form', $this->itemData())
            ->call('save')->assertHasNoErrors();
        foreach ($tables as $table) {
            $this->assertSame($before[$table]['columns'], Schema::getColumnListing($table));
            $this->assertEquals($before[$table]['rows'], DB::table($table)->get()->toArray());
        }
        $this->assertDatabaseCount('master_data_items', 1);
        $this->assertFalse(Schema::hasColumn('master_data_items', 'diameter_mm'));
        $this->assertFalse(Schema::hasColumn('master_data_items', 'purity_percent'));
    }

    public function test_module_renders_and_handles_updates_on_a_fresh_request(): void
    {
        $page = $this->get('/master-data/items')->assertOk()->assertSee('Master Barang')->assertSee('Master Kategori')->assertSee('Master UoM');
        preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
        $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES, 'UTF-8'))
            ->first(fn ($value) => json_decode($value, true)['memo']['name'] === 'master-data.master-data-page');
        $this->assertNotNull($snapshot);
        $pdo = $this->app['db']->connection()->getPdo();
        $authenticatedUserId = auth()->id();
        $this->refreshApplication();
        $this->app['db']->connection()->setPdo($pdo);
        $this->actingAs(\App\Models\User::findOrFail($authenticatedUserId));
        $this->postJson('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]]], ['X-Livewire' => 'true'])
            ->assertOk()->assertJsonStructure(['components']);
    }

    public function test_sidebar_master_links_open_their_own_pages_without_tab_buttons(): void
    {
        $this->get('/master-data')->assertRedirect('/master-data/items');
        foreach (['items' => 'Daftar barang', 'categories' => 'Daftar kategori', 'uoms' => 'Daftar UoM'] as $path => $heading) {
            $this->get('/master-data/'.$path)->assertOk()->assertSee($heading)
                ->assertSee('href="'.url('/master-data/items').'"', false)
                ->assertSee('href="'.url('/master-data/categories').'"', false)
                ->assertSee('href="'.url('/master-data/uoms').'"', false)
                ->assertDontSee('wire:click="setTab(', false);
        }
    }
}
