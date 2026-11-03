<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Livewire\CustomerPage;
use App\Modules\MasterData\Livewire\MasterDataPage;
use App\Modules\MasterData\Livewire\SupplierPage;
use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Customer;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Supplier;
use App\Modules\MasterData\Models\Unit;
use App\Modules\MasterData\Services\MasterDataDeletionService;
use App\Modules\MasterData\Services\MasterDataService;
use App\Modules\MasterData\Services\PartnerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected UserRole $authenticatedRole = UserRole::SuperAdmin;

    private function item(): Item
    {
        return Item::create(['code' => 'TEST-ITEM', 'name' => 'Barang uji',
            'category_id' => Category::where('code', 'MAT')->value('id'),
            'uom_id' => Unit::where('code', 'KG')->value('id'), 'is_active' => true]);
    }

    public function test_item_category_and_unit_deletion_requires_confirmation_and_can_be_cancelled(): void
    {
        $records = [
            ['item', $this->item(), 'items'],
            ['category', Category::create(['code' => 'TEST', 'name' => 'Kategori uji', 'is_active' => true]), 'categories'],
            ['unit', Unit::create(['code' => 'TEST', 'name' => 'Satuan uji', 'factor' => 1, 'is_active' => true]), 'units'],
        ];
        foreach ($records as [$kind, $record, $tab]) {
            $page = Livewire::test(MasterDataPage::class, ['section' => $tab])
                ->call('deleteConfirmed')->assertHasNoErrors()
                ->call('confirmRemoval', $kind, $record->id)->assertSet('pendingDeleteId', $record->id)
                ->assertSee('Hapus master data?')->assertSee($record->name);
            $this->assertModelExists($record);
            $page->call('cancelRemoval')->assertSet('pendingDeleteId', null)->call('deleteConfirmed');
            $this->assertModelExists($record);
            $page->call('confirmRemoval', $kind, $record->id)->call('deleteConfirmed')
                ->assertHasNoErrors()->assertSet('pendingDeleteId', null)->assertDispatched('notify');
            $this->assertModelMissing($record);
        }
    }

    public function test_referenced_master_data_cannot_be_deleted_and_error_stays_in_confirmation(): void
    {
        $item = $this->item();
        $supplier = Supplier::create(['code' => 'SUP-TEST', 'name' => 'Supplier uji', 'is_active' => true]);
        $supplier->items()->attach($item);
        $category = Category::create(['code' => 'PARENT', 'name' => 'Induk', 'is_active' => true]);
        Category::create(['code' => 'CHILD', 'name' => 'Anak', 'parent_id' => $category->id, 'is_active' => true]);
        $unit = Unit::create(['code' => 'BASE', 'name' => 'Acuan', 'factor' => 1, 'is_active' => true]);
        Unit::create(['code' => 'RELATED', 'name' => 'Turunan', 'factor' => 2, 'related_uom_id' => $unit->id, 'is_active' => true]);
        foreach ([['item', $item], ['category', $item->category], ['unit', $item->unit], ['category', $category], ['unit', $unit]] as [$kind, $record]) {
            Livewire::test(MasterDataPage::class)->call('confirmRemoval', $kind, $record->id)
                ->call('deleteConfirmed')->assertHasErrors('delete')->assertSet('pendingDeleteId', $record->id)
                ->assertSee('Data masih digunakan');
            $this->assertModelExists($record);
        }
    }

    public function test_supplier_deletion_removes_only_its_links_and_customer_deletion_is_independent(): void
    {
        $item = $this->item();
        $a = Supplier::create(['code' => 'SUP-A', 'name' => 'Supplier A', 'is_active' => true]);
        $b = Supplier::create(['code' => 'SUP-B', 'name' => 'Supplier B', 'is_active' => true]);
        $a->items()->attach($item);
        $b->items()->attach($item);
        Livewire::test(SupplierPage::class)->call('confirmRemoval', 'supplier', $a->id)
            ->call('deleteConfirmed')->assertHasNoErrors()->assertSet('pendingDeleteId', null);
        $this->assertModelMissing($a);
        $this->assertModelExists($item);
        $this->assertSame([$item->id], $b->items()->pluck('master_data_items.id')->all());
        $this->assertDatabaseMissing('master_data_supplier_items', ['supplier_id' => $a->id]);
        $customer = Customer::create(['code' => 'CUS-A', 'name' => 'Customer A', 'is_active' => true]);
        $page = Livewire::test(CustomerPage::class)->call('confirmRemoval', 'customer', $customer->id)->call('cancelRemoval');
        $this->assertModelExists($customer);
        $page->call('confirmRemoval', 'customer', $customer->id)->call('deleteConfirmed')->assertHasNoErrors();
        $this->assertModelMissing($customer);
        $this->assertModelExists($b);
    }

    public function test_non_super_admin_cannot_use_master_write_services_directly(): void
    {
        $item = $this->item();
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        foreach ([
            fn () => app(MasterDataService::class)->saveItem([]),
            fn () => app(MasterDataService::class)->saveCategory([]),
            fn () => app(MasterDataService::class)->saveUnit([]),
            fn () => app(PartnerService::class)->saveSupplier([]),
            fn () => app(PartnerService::class)->saveCustomer([]),
            fn () => app(MasterDataDeletionService::class)->delete('item', $item->id),
        ] as $write) {
            try {
                $write();
                $this->fail('Only Super Admin may write master data.');
            } catch (AuthorizationException) {
                $this->assertModelExists($item);
            }
        }
    }
}
