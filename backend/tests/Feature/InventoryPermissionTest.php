<?php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Livewire\AccessProfilePage;
use App\Modules\MasterData\Models\AccessProfile;
use App\Modules\MasterData\Models\JobTitle;
use App\Modules\MasterData\Models\Division;
use App\Modules\MasterData\Services\AccessProfileService;
use App\Modules\Ppic\Livewire\InventoryControlPage;
use App\Modules\Ppic\Models\InventoryReceipt;
use App\Modules\Ppic\Models\PurchaseRequest;
use App\Modules\Ppic\Models\ProductionHandoff;
use App\Modules\Ppic\Services\SalesReadinessService;
use App\Modules\Ppic\Services\PpicAttentionService;
use App\Modules\Purchasing\Services\ProcurementService;
use App\Services\AccessPermissionService;
use App\Services\SinoContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryPermissionTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\UsesMasterSales;
    protected UserRole $authenticatedRole = UserRole::SuperAdmin;

    private function profile(array $permissions = [], ?array $modules = ['marketing','ppic_ic']): AccessProfile
    {
        $n=AccessProfile::count()+1;
        return AccessProfile::create([
            'job_title_id'=>JobTitle::create(['code'=>'J-'.$n,'name'=>'Supervisor '.$n,'is_active'=>true])->id,
            'division_id'=>Division::create(['code'=>'D-'.$n,'name'=>'Divisi '.$n,'is_active'=>true])->id,
            'permissions'=>AccessPermissionService::normalize($permissions), 'configured_modules'=>$modules, 'is_active'=>true,
        ]);
    }

    private function employee(AccessProfile $profile, UserRole $role = UserRole::OperatorProduksi): User
    {
        return User::factory()->create(['role'=>$role,'job_title_id'=>$profile->job_title_id,'division_id'=>$profile->division_id]);
    }

    private function preparedSale(): array
    {
        $material=$this->masterMaterial();
        $sale=$this->masterSale($this->masterProduct($material), '10');
        app(SalesReadinessService::class)->prepare($sale->id);
        return [$material,$sale];
    }

    private function order($material, string $qty = '10')
    {
        $service=app(ProcurementService::class);
        $request=$service->submit(['product_id'=>$material->id,'quantity'=>$qty,'required_on'=>now()->format('Y-m-d')]);
        return $service->approve($request->id,now()->format('Y-m-d'),now()->format('Y-m-d'));
    }

    public function test_checklist_has_thirteen_ic_permissions_and_preserves_marketing(): void
    {
        $keys=array_values(array_filter(AccessPermissionService::keys(),fn($p)=>str_starts_with($p,'ppic.ic.')));
        $this->assertCount(13,$keys);
        $profile=$this->profile(['marketing.sales.view']);
        Livewire::test(AccessProfilePage::class)->call('openEdit',$profile->id)->assertSee('Gunakan checklist Inventory Control')
            ->set('form.permissions',['marketing.sales.view','ppic.ic.requests.create','ppic.ic.sales.handover'])
            ->call('save')->assertHasNoErrors();
        $stored=$profile->fresh();
        $this->assertContains('marketing.sales.view',$stored->permissions);
        $this->assertContains('ppic.ic.requests.view',$stored->permissions);
        $this->assertContains('ppic.ic.sales.view',$stored->permissions);
        $this->assertNotContains('ppic.ic.sales.check',$stored->permissions);
        $this->assertContains('ppic_ic',$stored->configured_modules);
    }

    public function test_legacy_profiles_keep_ic_until_enabled_and_empty_checklist_denies_only_ic(): void
    {
        $profile=$this->profile(['marketing.sales.view'],null);
        $user=$this->employee($profile,UserRole::Ppic);
        $this->actingAs($user);
        $this->get('/ppic/inventory-control')->assertOk();
        $profile->update(['configured_modules'=>['ppic_ic']]);
        $this->get('/ppic/inventory-control')->assertForbidden();
        $this->get('/ppic/production-plan')->assertOk();
        $this->assertTrue(Gate::allows('marketing.sales.view'));
        $this->assertFalse(Gate::allows('ppic.ic.stock.view'));
    }

    public function test_stock_only_employee_sees_only_stock_without_pp_or_unrelated_queries(): void
    {
        $this->actingAs($this->employee($this->profile(['ppic.ic.stock.view'])));
        $queries=[];
        DB::listen(function($q)use(&$queries){$queries[]=$q->sql;});
        $this->get('/ppic/inventory-control')->assertOk()->assertSee('Stok barang')
            ->assertDontSee('wire:click="setTab(\'sales\')"',false)->assertDontSee('wire:click="setTab(\'requests\')"',false)
            ->assertDontSee('wire:click="setTab(\'in\')"',false)->assertDontSee('href="'.route('ppic.production-plan').'"',false);
        foreach(['purchase_requests','purchase_orders','marketing_sales_orders'] as $table) $this->assertStringNotContainsString($table,implode(' ',$queries));
        $this->get('/ppic/production-plan')->assertForbidden();
        Livewire::test(InventoryControlPage::class)->assertSet('tab','stock')->call('setTab','sales')->assertForbidden();
    }

    public function test_request_viewer_cannot_submit_and_receipt_viewer_cannot_receive(): void
    {
        $profile=$this->profile(['ppic.ic.requests.view','ppic.ic.receipts.view']);
        $this->actingAs($this->employee($profile));
        Livewire::test(InventoryControlPage::class)->assertSet('tab','requests')->assertDontSee('wire:submit="submitRequest"',false)->call('submitRequest')->assertForbidden();
        Livewire::test(InventoryControlPage::class)->call('setTab','in')->assertSee('Riwayat barang masuk')->call('selectReceipt',999)->assertForbidden();
        Livewire::test(InventoryControlPage::class)->call('receiveGoods')->assertForbidden();
        try { app(ProcurementService::class)->submit([]); $this->fail('Denied submit'); } catch(AuthorizationException) {}
        $this->expectException(AuthorizationException::class);
        app(ProcurementService::class)->receive(999,'10',now()->format('Y-m-d'),(string)Str::uuid());
    }

    public function test_read_only_sale_view_does_not_prepare_or_change_requirements(): void
    {
        [$material,$sale]=$this->preparedSale();
        $before=$sale->materialRequirements()->get()->toArray();
        $this->actingAs($this->employee($this->profile(['ppic.ic.sales.view'])));
        Livewire::test(InventoryControlPage::class)->assertSet('tab','sales')->call('viewSale',$sale->id)
            ->assertSee('Kebutuhan bahan')->assertDontSee('wire:submit="requestShortages"',false)
            ->assertDontSee('wire:click="handOverSale"',false)->assertDontSee('wire:click="checkSale',false);
        $this->assertSame($before,$sale->materialRequirements()->get()->toArray());
        Livewire::test(InventoryControlPage::class)->call('checkSale',$sale->id)->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(SalesReadinessService::class)->prepare($sale->id);
    }

    public function test_sales_action_permissions_are_independent_after_preparation(): void
    {
        [$material,$sale]=$this->preparedSale();
        $this->actingAs($this->employee($this->profile(['ppic.ic.sales.request'])));
        Livewire::test(InventoryControlPage::class)->call('viewSale',$sale->id)->call('requestShortages')->assertHasNoErrors();
        $this->assertSame(1,PurchaseRequest::where('sales_order_id',$sale->id)->count());
        $this->assertFalse(Gate::allows('ppic.ic.sales.check'));
        Livewire::test(InventoryControlPage::class)->call('handOverSale')->assertForbidden();
        Livewire::test(InventoryControlPage::class)->call('submitRequest')->assertForbidden();
    }

    public function test_handover_does_not_require_check_or_pp_page_access(): void
    {
        [$material,$sale]=$this->preparedSale();
        $order=$this->order($material);
        app(ProcurementService::class)->receive($order->id,'10',now()->format('Y-m-d'),(string)Str::uuid());
        $this->actingAs($this->employee($this->profile(['ppic.ic.sales.handover'])));
        Livewire::test(InventoryControlPage::class)->call('viewSale',$sale->id)->call('handOverSale')->assertHasNoErrors();
        $this->assertSame(1,ProductionHandoff::where('sales_order_id',$sale->id)->count());
        $this->assertFalse(Gate::allows('ppic.ic.sales.check'));
        $this->get('/ppic/production-plan')->assertForbidden();
    }

    public function test_request_creation_and_receipt_are_allowed_only_when_checked(): void
    {
        $material=$this->masterMaterial();
        $order=$this->order($material);
        $this->actingAs($this->employee($this->profile(['ppic.ic.requests.create','ppic.ic.receipts.create'])));
        Livewire::test(InventoryControlPage::class)->set('product_id',(string)$material->id)->set('quantity','5')
            ->set('required_on',now()->format('Y-m-d'))->call('submitRequest')->assertHasNoErrors();
        Livewire::test(InventoryControlPage::class)->call('setTab','in')->call('selectReceipt',$order->id)
            ->call('receiveGoods')->assertHasNoErrors();
        $this->assertEquals(10,InventoryReceipt::sum('quantity'));
        $this->assertSame(2,PurchaseRequest::count());
    }

    public function test_revocation_blocks_an_already_open_receipt_and_updates_indicators(): void
    {
        $order=$this->order($this->masterMaterial());
        $profile=$this->profile(['ppic.ic.receipts.create']);
        $this->actingAs($this->employee($profile));
        $this->assertSame(1,app(PpicAttentionService::class)->for('incoming')['count']);
        $page=Livewire::test(InventoryControlPage::class)->call('setTab','in')->call('selectReceipt',$order->id);
        $profile->update(['permissions'=>['ppic.ic.receipts.view']]);
        $page->call('receiveGoods')->assertForbidden();
        $this->assertSame(0,InventoryReceipt::count());
        $profile->update(['permissions'=>[]]);
        $this->assertSame(0,app(PpicAttentionService::class)->for('inventory')['count']);
        Livewire::test(InventoryControlPage::class)->assertForbidden();
    }

    public function test_disabled_profiles_and_masters_deny_ic(): void
    {
        $profile=$this->profile(['ppic.ic.stock.view']);
        $this->actingAs($this->employee($profile));
        $profile->update(['is_active'=>false]);
        $this->get('/ppic/inventory-control')->assertForbidden();
        $profile->update(['is_active'=>true]);
        $profile->division->update(['is_active'=>false]);
        $this->get('/ppic/inventory-control')->assertForbidden();
    }

    public function test_sino_context_excludes_ic_sections_not_granted_but_keeps_pp(): void
    {
        $this->actingAs($this->employee($this->profile(['ppic.ic.requests.view']),UserRole::Ppic));
        $context=app(SinoContextService::class)->build()['ppic'];
        $this->assertArrayHasKey('30_pengajuan_barang_terbaru',$context);
        $this->assertArrayHasKey('30_production_plan_terbaru',$context);
        $this->assertArrayNotHasKey('30_stok_material',$context);
        $this->assertArrayNotHasKey('30_kesiapan_penjualan_untuk_produksi',$context);
    }

    public function test_super_admin_still_has_all_ic_actions_even_with_empty_profile(): void
    {
        $this->actingAs($this->employee($this->profile(),UserRole::SuperAdmin));
        foreach(AccessPermissionService::keys() as $permission) $this->assertTrue(Gate::allows($permission));
        $this->get('/ppic/inventory-control')->assertOk();
    }

    public function test_turning_off_checklist_does_not_clear_marketing_permissions(): void
    {
        $profile=$this->profile(['marketing.sales.view'],['marketing']);
        Livewire::test(AccessProfilePage::class)->call('openEdit',$profile->id)->call('save')->assertHasNoErrors();
        $this->assertSame(['marketing.sales.view'],$profile->fresh()->permissions);
        $this->assertNotContains('ppic_ic',$profile->fresh()->configured_modules);
        Livewire::test(AccessProfilePage::class)->call('openEdit',$profile->id)
            ->set('form.configured_modules',['marketing','ppic_ic'])->call('save')->assertHasNoErrors();
        $this->assertContains('ppic_ic',$profile->fresh()->configured_modules);
        $this->assertSame(['marketing.sales.view'],$profile->fresh()->permissions);
    }
    public function test_check_permission_prepares_materials_without_granting_other_mutations(): void
    {
        $sale=$this->masterSale($this->masterProduct($this->masterMaterial()),'10');
        $this->actingAs($this->employee($this->profile(['ppic.ic.sales.check'])));
        Livewire::test(InventoryControlPage::class)->call('checkSale',$sale->id)->assertHasNoErrors()->assertSee('Kebutuhan bahan');
        $this->assertSame(1,$sale->materialRequirements()->count());
        Livewire::test(InventoryControlPage::class)->call('requestShortages')->assertForbidden();
        $this->assertFalse(Gate::allows('ppic.ic.sales.handover'));
    }

    public function test_direct_service_calls_reject_missing_permissions_before_reading_or_writing(): void
    {
        $this->actingAs($this->employee($this->profile(['ppic.ic.stock.view'])));
        $service=app(SalesReadinessService::class);
        foreach([
            fn()=>$service->prepare(999), fn()=>$service->assess(999),
            fn()=>$service->requestShortages(999,now()->format('Y-m-d')), fn()=>$service->handOver(999),
        ] as $call) {
            try { $call(); $this->fail('Missing permission must be rejected'); }
            catch(AuthorizationException) { $this->addToAssertionCount(1); }
        }
        $this->assertSame(0,PurchaseRequest::count());
        $this->assertSame(0,ProductionHandoff::count());
    }

    public function test_fresh_livewire_request_preserves_ic_permissions_and_does_not_grant_pp(): void
    {
        $user=$this->employee($this->profile(['ppic.ic.stock.view']));
        $this->actingAs($user);
        $response=$this->get('/ppic/inventory-control')->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/',$response->getContent(),$matches);
        $snapshot=collect($matches[1])->map(fn($value)=>html_entity_decode($value,ENT_QUOTES,'UTF-8'))
            ->first(fn($value)=>json_decode($value,true)['memo']['name']==='ppic.inventory-control-page');
        $this->assertNotNull($snapshot);
        $connection=$this->app['db']->connection();
        $this->refreshApplication();
        $this->app['db']->extend('sqlite',fn()=>$connection);
        $this->actingAs(User::findOrFail($user->id));
        $this->postJson('/livewire/update',['components'=>[['snapshot'=>$snapshot,'updates'=>[],
            'calls'=>[['path'=>'','method'=>'setTab','params'=>['sales']]]]]],['X-Livewire'=>'true'])->assertForbidden();
        Livewire::test(\App\Modules\Ppic\Livewire\ProductionPlanPage::class)->assertForbidden();
    }

    public function test_assignment_changes_replace_allowed_tabs_without_a_new_login(): void
    {
        $stock=$this->profile(['ppic.ic.stock.view']);
        $requests=$this->profile(['ppic.ic.requests.view']);
        $user=$this->employee($stock);
        $this->actingAs($user);
        $page=Livewire::test(InventoryControlPage::class)->assertSet('tab','stock');
        $user->update(['job_title_id'=>$requests->job_title_id,'division_id'=>$requests->division_id]);
        $page->call('$refresh')->assertSet('tab','requests')->assertSee('Riwayat pengajuan');
        $page->call('setTab','stock')->assertForbidden();
    }

    public function test_marketing_role_with_ic_only_permissions_logs_in_to_inventory(): void
    {
        $user=$this->employee($this->profile(['ppic.ic.stock.view']),UserRole::Marketing);
        auth()->logout();
        $this->post('/login',['username'=>$user->username,'password'=>'TestPassword!2026'])
            ->assertRedirect(route('ppic.inventory-control'));
        $this->get('/ppic/inventory-control')->assertOk()->assertSee('Stok barang');
        $this->get('/marketing/sales')->assertForbidden();
        $this->get('/ppic/production-plan')->assertForbidden();
    }

    public function test_ic_sidebar_urls_open_each_section_without_page_tabs(): void
    {
        foreach (['sales' => 'Kesiapan Penjualan', 'requests' => 'Pengajuan Pembelian', 'receipts' => 'Barang Masuk', 'stock' => 'Stok'] as $key => $label) {
            $response = $this->get('/ppic/inventory-control/'.$key)->assertOk()
                ->assertSee('<h1>'.$label.'</h1>', false)->assertDontSee('aria-label="Inventory Control"', false);
            $dom = new \DOMDocument();
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $active = $xpath->query('//*[@id="ppic-submenu"]//a[@aria-current="page"]');
            $this->assertCount(1, $active);
            $this->assertSame($label, trim($active->item(0)->textContent));
            $this->assertSame('true', $xpath->query('//button[@aria-controls="ppic-submenu"]')->item(0)->getAttribute('aria-expanded'));
        }
    }

    public function test_stock_only_sidebar_hides_other_ic_pages_and_ppic_dashboard(): void
    {
        $this->actingAs($this->employee($this->profile(['ppic.ic.stock.view'])));
        $this->get('/ppic/inventory-control/stock')->assertOk()
            ->assertDontSee('href="'.route('ppic.dashboard').'"', false)
            ->assertDontSee('href="'.route('ppic.ic.requests').'"', false)
            ->assertDontSee('href="'.route('ppic.ic.sales').'"', false)
            ->assertDontSee('href="'.route('ppic.ic.receipts').'"', false);
        foreach (['/ppic', '/ppic/production-plan', '/ppic/inventory-control/sales', '/ppic/inventory-control/requests', '/ppic/inventory-control/receipts'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_explicit_ic_page_rechecks_permission_on_fresh_livewire_request(): void
    {
        $profile = $this->profile(['ppic.ic.stock.view', 'ppic.ic.requests.view']);
        $user = $this->employee($profile);
        $this->actingAs($user);
        $response = $this->get('/ppic/inventory-control/stock')->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = collect($matches[1])->map(fn($v) => html_entity_decode($v, ENT_QUOTES, 'UTF-8'))
            ->first(fn($v) => json_decode($v, true)['memo']['name'] === 'ppic.inventory-control-page');
        $this->assertNotNull($snapshot);
        $profile->update(['permissions' => ['ppic.ic.requests.view']]);
        $connection = $this->app['db']->connection();
        $this->refreshApplication();
        $this->app['db']->extend('sqlite', fn() => $connection);
        $this->actingAs(User::findOrFail($user->id));
        $this->postJson('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => [],
            'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertForbidden();
    }

    public function test_production_calendar_placeholder_is_available_only_with_ppic_access(): void
    {
        $this->get('/ppic/production-calendar')->assertOk()->assertSee('Kalender Produksi')->assertSee('Dalam tahap pengembangan.')->assertSee('Stay tune!');
        $this->actingAs(User::factory()->create(['role' => UserRole::Ppic]));
        $this->get('/ppic/production-calendar')->assertOk();
        Livewire::test(\App\Modules\Ppic\Livewire\ProductionCalendarPage::class)->assertSee('Stay tune!');
        $this->actingAs($this->employee($this->profile(['ppic.ic.stock.view'])));
        $this->get('/ppic/production-calendar')->assertForbidden();
        Livewire::test(\App\Modules\Ppic\Livewire\ProductionCalendarPage::class)->assertForbidden();
        $this->get('/ppic/inventory-control/stock')->assertOk()->assertDontSee('href="'.route('ppic.production-calendar').'"', false);
    }
}