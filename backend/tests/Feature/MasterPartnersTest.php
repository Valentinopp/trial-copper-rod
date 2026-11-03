<?php

namespace Tests\Feature;

use App\Modules\MasterData\Livewire\CustomerPage;
use App\Modules\MasterData\Livewire\SupplierPage;
use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Customer;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Supplier;
use App\Modules\MasterData\Models\Unit;
use App\Modules\MasterData\Services\PartnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class MasterPartnersTest extends TestCase
{
    protected \App\Enums\UserRole $authenticatedRole = \App\Enums\UserRole::SuperAdmin;

    use RefreshDatabase;

    private function data(array $overrides = []): array
    {
        return array_replace(['code' => 'SUP-001', 'name' => 'PT Supplier', 'contact_name' => 'Kontak supplier',
            'phone' => '+6208123456', 'email' => 'supplier@example.com', 'address' => 'Alamat supplier', 'notes' => '', 'is_active' => true], $overrides);
    }

    private function item(string $code = 'MAT-CATHODE', bool $active = true): Item
    {
        return Item::create(['code' => $code, 'name' => $code, 'category_id' => Category::where('code', 'MAT')->value('id'),
            'uom_id' => Unit::where('code', 'KG')->value('id'), 'is_active' => $active]);
    }

    public function test_supplier_and_customer_pages_have_vertical_sidebar_links_and_survive_fresh_update_requests(): void
    {
        foreach (['suppliers' => 'supplier', 'customers' => 'customer'] as $path => $kind) {
            $page = $this->get('/master-data/'.$path)->assertOk()->assertSee('Master Supplier')->assertSee('Master Customer')
                ->assertSee('Daftar '.$kind)->assertDontSee('wire:click="setTab(', false);
            preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
            $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES, 'UTF-8'))
                ->first(fn ($value) => json_decode($value, true)['memo']['name'] === 'master-data.'.$kind.'-page');
            $this->assertNotNull($snapshot);
            $pdo = $this->app['db']->connection()->getPdo();
            $authenticatedUserId = auth()->id();
            $this->refreshApplication();
            $this->app['db']->connection()->setPdo($pdo);
            $this->actingAs(\App\Models\User::findOrFail($authenticatedUserId));
            $this->postJson('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]]], ['X-Livewire' => 'true'])
                ->assertOk()->assertJsonStructure(['components']);
        }
    }

    public function test_supplier_creates_edits_searches_and_deactivates_with_contacts_and_no_items(): void
    {
        $page = Livewire::test(SupplierPage::class)->assertDontSeeHtml('wire:submit="save"')->call('openCreate')
            ->set('form', $this->data(['code' => ' sup-001 ', 'name' => '  PT   Supplier  ', 'phone' => '08123456']))
            ->call('save')->assertHasNoErrors()->assertSet('showForm', false)->assertSee('SUP-001')->assertSee('0 barang');
        $supplier = Supplier::firstOrFail();
        $this->assertSame('PT Supplier', $supplier->name);
        $this->assertSame('08123456', $supplier->phone);
        $page->call('openEdit', $supplier->id)->assertSet('form.email', 'supplier@example.com')
            ->set('form.name', 'PT Supplier Baru')->set('form.is_active', false)->call('save')->assertHasNoErrors()->assertSee('Nonaktif');
        $page->set('search', 'supplier baru')->assertSee('SUP-001')->set('search', 'tidak ada')->assertDontSee('SUP-001');
        $this->assertSame('PT Supplier Baru', $supplier->fresh()->name);
        $this->assertFalse($supplier->fresh()->is_active);
    }

    public function test_two_suppliers_share_items_and_detaching_does_not_delete_other_supplier_relations_or_items(): void
    {
        $cathode = $this->item();
        $charcoal = $this->item('MAT-ARANG');
        $service = app(PartnerService::class);
        $a = $service->saveSupplier($this->data() + ['item_ids' => [$cathode->id, $charcoal->id]]);
        $b = $service->saveSupplier($this->data(['code' => 'SUP-002']) + ['item_ids' => [$cathode->id]]);
        $this->assertSame(2, $cathode->suppliers()->count());
        $this->assertDatabaseCount('master_data_supplier_items', 3);
        $page = Livewire::test(SupplierPage::class)->call('openEdit', $a->id)->assertSet('selectedItemIds', [$cathode->id, $charcoal->id])
            ->call('toggleItem', $cathode->id)->call('save')->assertHasNoErrors();
        $this->assertSame([$charcoal->id], $a->items()->pluck('master_data_items.id')->all());
        $this->assertSame([$cathode->id], $b->items()->pluck('master_data_items.id')->all());
        $this->assertDatabaseCount('master_data_items', 2);
        $page->call('openEdit', $a->id)->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('master_data_supplier_items', 2);
        $service->saveSupplier($this->data(['is_active' => false]), $a->id);
        $this->assertSame(1, $a->items()->count());
    }

    public function test_supplier_picker_keeps_draft_and_only_saves_relations_on_save(): void
    {
        $item = $this->item();
        $page = Livewire::test(SupplierPage::class)->call('openCreate')->set('form', $this->data())
            ->call('toggleItem', $item->id)->assertSet('selectedItemIds', [$item->id])->assertSet('form.name', 'PT Supplier');
        $this->assertDatabaseCount('master_data_supplier_items', 0);
        $page->call('closeForm')->assertSet('showForm', false);
        $this->assertDatabaseCount('master_data_suppliers', 0);
        $this->assertDatabaseCount('master_data_supplier_items', 0);
    }

    public function test_existing_inactive_item_is_visible_preserved_then_can_be_explicitly_removed(): void
    {
        $item = $this->item();
        $supplier = app(PartnerService::class)->saveSupplier($this->data() + ['item_ids' => [$item->id]]);
        $item->update(['is_active' => false, 'name' => 'Cathode nonaktif']);
        $page = Livewire::test(SupplierPage::class)->call('openEdit', $supplier->id)->assertSee('Cathode nonaktif')
            ->assertSee('Nonaktif · hubungan tetap tersimpan')->set('form.phone', '+620899')->call('save')->assertHasNoErrors();
        $this->assertSame(1, $supplier->items()->count());
        $page->call('openEdit', $supplier->id)->call('toggleItem', $item->id)->call('save')->assertHasNoErrors();
        $this->assertSame(0, $supplier->items()->count());
        $page->call('openCreate')->set('form', $this->data(['code' => 'SUP-NEW']))->set('selectedItemIds', [$item->id])
            ->call('save')->assertHasErrors('item_ids')->assertSet('showForm', true);
        $this->assertDatabaseCount('master_data_suppliers', 1);
        $this->assertDatabaseCount('master_data_items', 1);
    }

    public function test_invalid_item_ids_duplicates_and_inactive_new_items_do_not_partially_save(): void
    {
        $item = $this->item();
        $supplier = app(PartnerService::class)->saveSupplier($this->data() + ['item_ids' => [$item->id]]);
        foreach ([[999999], ['not-an-id'], [$item->id, $item->id]] as $ids) {
            Livewire::test(SupplierPage::class)->call('openEdit', $supplier->id)->set('form.name', 'Must not save')
                ->set('selectedItemIds', $ids)->call('save')->assertHasErrors();
            $this->assertSame('PT Supplier', $supplier->fresh()->name);
            $this->assertSame([$item->id], $supplier->items()->pluck('master_data_items.id')->all());
        }
        Livewire::test(SupplierPage::class)->call('openCreate')->set('form', $this->data(['code' => 'SUP-NEW']))
            ->set('selectedItemIds', [999999])->call('save')->assertHasErrors();
        $this->assertDatabaseCount('master_data_suppliers', 1);
    }

    public function test_customer_contacts_validation_unique_codes_and_status_are_independent_from_suppliers(): void
    {
        app(PartnerService::class)->saveSupplier($this->data());
        $page = Livewire::test(CustomerPage::class)->call('openCreate')->call('save')
            ->assertHasErrors(['form.code', 'form.name'])->assertSet('showForm', true);
        $page->set('form', $this->data(['email' => 'invalid-email']))->call('save')->assertHasErrors('form.email');
        $page->set('form', $this->data(['phone' => '+6208123456']))->call('save')->assertHasNoErrors()->assertSet('showForm', false);
        $customer = Customer::firstOrFail();
        $this->assertSame('+6208123456', $customer->phone);
        $page->call('openCreate')->set('form', $this->data(['code' => 'sup-001']))->call('save')->assertHasErrors('form.code');
        $page->call('openEdit', $customer->id)->set('form.name', 'PT Customer')->set('form.is_active', false)
            ->call('save')->assertHasNoErrors()->assertSee('Nonaktif');
        $page->set('search', 'customer')->assertSee('SUP-001')->set('search', 'absent')->assertDontSee('SUP-001');
        $this->assertDatabaseCount('master_data_customers', 1);
        $this->assertDatabaseCount('master_data_supplier_items', 0);
        Livewire::test(CustomerPage::class)->call('openCreate')->assertDontSeeHtml('wire:model.live.debounce.300ms="itemSearch"');
    }

    public function test_supplier_rejects_duplicate_codes_and_invalid_contact_without_creating_record(): void
    {
        app(PartnerService::class)->saveSupplier($this->data());
        Livewire::test(SupplierPage::class)->call('openCreate')->set('form', $this->data(['code' => 'sup-001', 'email' => 'bad']))
            ->call('save')->assertHasErrors(['form.code', 'form.email']);
        $this->assertDatabaseCount('master_data_suppliers', 1);
    }

    public function test_catalog_and_selected_items_are_searched_and_paginated_separately(): void
    {
        $ids = [];
        for ($i = 1; $i <= 20; $i++) {
            $ids[] = $this->item('ITEM-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT))->id;
        }
        $page = Livewire::test(SupplierPage::class)->call('openCreate');
        $this->assertCount(8, $page->viewData('catalogItems')->items());
        $this->assertSame(20, $page->viewData('catalogItems')->total());
        $page->set('itemSearch', 'item-020')->assertSee('ITEM-020')->assertDontSee('ITEM-001');
        $page->set('selectedItemIds', array_slice($ids, 0, 12))->set('itemSearch', '');
        $this->assertCount(8, $page->viewData('selectedItems')->items());
        $this->assertSame(12, $page->viewData('selectedItems')->total());
        $this->assertSame(8, $page->viewData('catalogItems')->total());
        $page->set('selectedSearch', 'ITEM-012');
        $this->assertSame(1, $page->viewData('selectedItems')->total());
        $this->assertSame(8, $page->viewData('catalogItems')->total());
    }

    public function test_partner_writes_do_not_touch_operational_tables(): void
    {
        $tables = ['inventory_products', 'marketing_products', 'marketing_bom_items', 'marketing_sales_orders', 'purchase_requests', 'purchase_orders', 'inventory_receipts', 'production_handoffs', 'production_material_reservations', 'board_entries'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = ['columns' => Schema::getColumnListing($table), 'rows' => DB::table($table)->get()->toArray()];
        }
        $item = $this->item();
        app(PartnerService::class)->saveSupplier($this->data() + ['item_ids' => [$item->id]]);
        app(PartnerService::class)->saveCustomer($this->data(['code' => 'CUS-001']));
        foreach ($tables as $table) {
            $this->assertSame($before[$table]['columns'], Schema::getColumnListing($table));
            $this->assertEquals($before[$table]['rows'], DB::table($table)->get()->toArray());
        }
    }
}
