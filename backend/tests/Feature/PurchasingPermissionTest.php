<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Livewire\AccessProfilePage;
use App\Modules\MasterData\Models\{AccessProfile, JobTitle, Division};
use App\Modules\Ppic\Models\PurchaseRequest;
use App\Modules\Purchasing\Livewire\PengajuanPage;
use App\Modules\Purchasing\Livewire\PembelianPage;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\ProcurementService;
use App\Services\{AccessPermissionService, SinoContextService};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Gate};
use Livewire\Livewire;
use Tests\TestCase;

class PurchasingPermissionTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\UsesMasterSales;

    protected UserRole $authenticatedRole = UserRole::SuperAdmin;

    private function profile(array $permissions = [], ?array $modules = ['purchasing']): AccessProfile
    {
        $n = AccessProfile::count() + 1;
        return AccessProfile::create([
            'job_title_id' => JobTitle::create(['code' => 'J'.$n, 'name' => 'Job '.$n, 'is_active' => true])->id,
            'division_id' => Division::create(['code' => 'D'.$n, 'name' => 'Division '.$n, 'is_active' => true])->id,
            'permissions' => AccessPermissionService::normalize($permissions),
            'configured_modules' => $modules, 'is_active' => true,
        ]);
    }

    private function employee(AccessProfile $profile, UserRole $role = UserRole::Purchasing): User
    {
        return User::factory()->create(['role' => $role, 'job_title_id' => $profile->job_title_id, 'division_id' => $profile->division_id]);
    }

    private function request(): PurchaseRequest
    {
        $item = $this->masterMaterial();
        return app(ProcurementService::class)->submit(['product_id' => $item->id, 'quantity' => '10', 'required_on' => now()->format('Y-m-d')]);
    }

    public function test_checklist_normalizes_dependencies_and_preserves_other_modules(): void
    {
        $profile = $this->profile(['marketing.sales.view', 'ppic.ic.stock.view'], ['marketing', 'ppic_ic']);
        Livewire::test(AccessProfilePage::class)->call('openEdit', $profile->id)
            ->assertSee('Gunakan checklist Purchasing')
            ->set('form.permissions', ['marketing.sales.view', 'ppic.ic.stock.view', 'purchasing.orders.create'])
            ->assertSet('form.configured_modules', ['marketing', 'ppic_ic', 'purchasing'])
            ->call('save')->assertHasNoErrors();
        $this->assertEqualsCanonicalizing(['marketing.sales.view', 'ppic.ic.stock.view', 'purchasing.orders.create', 'purchasing.requests.view', 'purchasing.orders.view'], $profile->fresh()->permissions);
    }

    public function test_legacy_fallback_and_explicit_empty_checklist(): void
    {
        $profile = $this->profile([], null);
        $this->actingAs($this->employee($profile));
        $this->get('/purchasing')->assertOk();
        $profile->update(['configured_modules' => ['purchasing']]);
        $this->get('/purchasing')->assertForbidden();
        Livewire::test(PengajuanPage::class)->assertForbidden();
        $this->assertFalse(Gate::allows('use-sino'));
        $this->assertSame('access-unavailable', auth()->user()->homeRoute());
    }

    public function test_request_only_viewer_does_not_load_orders_or_actions(): void
    {
        $this->request();
        $this->actingAs($this->employee($this->profile(['purchasing.requests.view']), UserRole::OperatorProduksi));
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $this->get('/purchasing/requests')->assertOk()->assertSee('Pengajuan pembelian dari IC')
            ->assertDontSee('wire:click="selectRequest', false)->assertDontSee('Sisa diterima');
        // Pending request queries may use a NOT EXISTS subquery on orders, but must not fetch order rows.
        $this->assertCount(0, array_filter($queries, fn($sql) => preg_match('/^select (?:\*|count\(\*\) as aggregate) from "purchase_orders"/i', $sql)));
        Livewire::test(PengajuanPage::class)->call('selectRequest', 999)->assertForbidden();
        Livewire::test(PengajuanPage::class)->call('approveOrder')->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(ProcurementService::class)->approve(999, '', '');
    }

    public function test_orders_only_viewer_has_no_pending_requests_or_form_and_filtered_sino(): void
    {
        $request = $this->request();
        app(ProcurementService::class)->approve($request->id, now()->format('Y-m-d'), now()->format('Y-m-d'));
        $this->actingAs($this->employee($this->profile(['purchasing.orders.view'])));
        $page = Livewire::test(PembelianPage::class)->assertSee('Sisa diterima')
            ->assertDontSee('Pengajuan pembelian dari IC')->assertDontSee('wire:submit="approveOrder"', false);
        $context = app(SinoContextService::class)->build()['purchasing'];
        $this->assertArrayHasKey('30_pesanan_terbaru', $context);
        $this->assertArrayNotHasKey('30_pengajuan_belum_dipesan', $context);
    }

    public function test_creator_can_create_po_and_revocation_blocks_open_form(): void
    {
        $request = $this->request();
        $profile = $this->profile(['purchasing.orders.create']);
        $this->actingAs($this->employee($profile));
        $page = Livewire::test(PengajuanPage::class)->call('selectRequest', $request->id);
        $profile->update(['permissions' => ['purchasing.requests.view', 'purchasing.orders.view']]);
        $page->call('approveOrder')->assertForbidden();
        $this->assertDatabaseCount('purchase_orders', 0);
        $page = Livewire::test(PengajuanPage::class);
        $profile->update(['permissions' => AccessPermissionService::normalize(['purchasing.orders.create'])]);
        $page->call('selectRequest', $request->id)->call('approveOrder')->assertHasNoErrors();
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    public function test_assignment_and_disabled_master_changes_take_effect_without_login(): void
    {
        $requests = $this->profile(['purchasing.requests.view']);
        $orders = $this->profile(['purchasing.orders.view']);
        $user = $this->employee($requests);
        $this->actingAs($user);
        $page = Livewire::test(PengajuanPage::class)->assertSee('Pengajuan pembelian dari IC');
        $user->update(['job_title_id' => $orders->job_title_id, 'division_id' => $orders->division_id]);
        $page->call('$refresh')->assertForbidden();
        Livewire::test(PembelianPage::class)->assertSee('Sisa diterima');
        $page = Livewire::test(PembelianPage::class);
        $orders->division->update(['is_active' => false]);
        $page->call('$refresh')->assertForbidden();
    }

    public function test_request_only_sino_and_manager_context_do_not_leak_orders(): void
    {
        $profile = $this->profile(['purchasing.requests.view']);
        foreach ([UserRole::Purchasing, UserRole::Manager] as $role) {
            $this->actingAs($this->employee($profile, $role));
            $context = app(SinoContextService::class)->build()['purchasing'];
            $this->assertArrayHasKey('30_pengajuan_belum_dipesan', $context);
            $this->assertArrayNotHasKey('30_pesanan_terbaru', $context);
        }
        $profile->update(['permissions' => []]);
        $this->assertArrayNotHasKey('purchasing', app(SinoContextService::class)->build());
    }

    public function test_super_admin_bypasses_empty_checklist_but_inactive_user_cannot(): void
    {
        $user = $this->employee($this->profile(), UserRole::SuperAdmin);
        $this->actingAs($user);
        $this->get('/purchasing')->assertOk();
        $this->assertTrue(Gate::allows('purchasing.orders.create'));
        $user->update(['is_active' => false]);
        Livewire::test(PengajuanPage::class)->assertForbidden();
    }

    public function test_fresh_livewire_http_request_rechecks_revoked_permissions(): void
    {
        $request = $this->request();
        $profile = $this->profile(['purchasing.orders.create']);
        $user = $this->employee($profile);
        $this->actingAs($user);
        $response = $this->get('/purchasing/requests')->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = collect($matches[1])->map(fn($value) => html_entity_decode($value, ENT_QUOTES, 'UTF-8'))
            ->first(fn($value) => json_decode($value, true)['memo']['name'] === 'purchasing.pengajuan-page');
        $this->assertNotNull($snapshot);
        $profile->update(['permissions' => ['purchasing.requests.view']]);
        $connection = $this->app['db']->connection();
        $this->refreshApplication();
        $this->app['db']->extend('sqlite', fn() => $connection);
        $this->actingAs(User::findOrFail($user->id));
        $this->postJson('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => [],
            'calls' => [['path' => '', 'method' => 'selectRequest', 'params' => [$request->id]]]]]], ['X-Livewire' => 'true'])->assertForbidden();
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_refresh_clears_open_form_after_action_is_revoked(): void
    {
        $request = $this->request();
        $profile = $this->profile(['purchasing.orders.create']);
        $this->actingAs($this->employee($profile));
        $page = Livewire::test(PengajuanPage::class)->call('selectRequest', $request->id);
        $profile->update(['permissions' => ['purchasing.requests.view', 'purchasing.orders.view']]);
        $page->call('$refresh')->assertSet('selectedRequestId', null)->assertSet('ordered_on', '')
            ->assertDontSee('wire:submit="approveOrder"', false);
        $this->assertNull($page->viewData('selectedRequest'));
    }

    public function test_disabled_profile_and_job_deny_access_and_login_uses_available_module(): void
    {
        $profile = $this->profile(['purchasing.requests.view', 'ppic.ic.stock.view'], ['purchasing', 'ppic_ic']);
        $user = $this->employee($profile);
        $this->actingAs($user);
        $profile->update(['is_active' => false]);
        $this->get('/purchasing')->assertForbidden();
        $profile->update(['is_active' => true]);
        $profile->jobTitle->update(['is_active' => false]);
        $this->get('/purchasing')->assertForbidden();
        $profile->jobTitle->update(['is_active' => true]);
        $profile->update(['permissions' => ['ppic.ic.stock.view']]);
        auth()->logout();
        $this->post('/login', ['username' => $user->username, 'password' => 'TestPassword!2026'])
            ->assertRedirect(route('ppic.inventory-control'));
        $this->get('/purchasing')->assertForbidden();
        $profile->update(['permissions' => []]);
        auth()->logout();
        $this->post('/login', ['username' => $user->username, 'password' => 'TestPassword!2026'])
            ->assertRedirect(route('access-unavailable'));
    }

    public function test_separate_routes_show_one_table_and_only_load_the_active_section(): void
    {
        $this->get('/purchasing/requests')->assertOk()->assertSee('aria-current="page"', false)
            ->assertSee('Pengajuan pembelian dari IC')->assertDontSee('Sisa diterima');
        $this->get('/purchasing/orders')->assertOk()->assertSee('Sisa diterima')
            ->assertDontSee('Pengajuan pembelian dari IC')->assertDontSee('wire:submit="approveOrder"', false);
    }

    public function test_section_urls_and_navigation_respect_independent_permissions(): void
    {
        $this->actingAs($this->employee($this->profile(['purchasing.requests.view'])));
        $this->get('/purchasing/requests')->assertOk()->assertDontSee('href="'.route('purchasing.orders').'"', false);
        $this->get('/purchasing/orders')->assertForbidden();
        $this->actingAs($this->employee($this->profile(['purchasing.orders.view'])));
        $this->get('/purchasing/orders')->assertOk()->assertDontSee('href="'.route('purchasing.requests').'"', false);
        $this->get('/purchasing/requests')->assertForbidden();
        $this->get('/purchasing')->assertOk()->assertSee('Dalam tahap pengembangan.');
    }

    public function test_sidebar_expands_active_purchasing_and_marks_the_current_child(): void
    {
        foreach (['requests' => 'Pengajuan Pembelian', 'orders' => 'Pesanan Pembelian'] as $section => $label) {
            $response = $this->get('/purchasing/'.$section)->assertOk()
                ->assertSee('aria-controls="purchasing-submenu"', false)
                ->assertSee('aria-expanded="true"', false)
                ->assertDontSee('class="purchasing-navigation"', false);
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $active = $xpath->query('//*[@id="purchasing-submenu"]//a[@aria-current="page"]');
            $this->assertCount(1, $active);
            $this->assertSame($label, trim($active->item(0)->textContent));
        }
        $this->get('/')->assertOk()->assertSee('aria-expanded="false"', false);
    }

    public function test_no_purchasing_sidebar_group_when_all_purchasing_access_is_denied(): void
    {
        $this->actingAs($this->employee($this->profile([], ['purchasing']), UserRole::Manager));
        $this->get('/')->assertOk()->assertDontSee('aria-controls="purchasing-submenu"', false);
    }

    public function test_purchasing_parent_only_toggles_and_dashboard_has_no_procurement_queries(): void
    {
        $this->actingAs($this->employee($this->profile(['purchasing.orders.view'])));
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $response = $this->get('/purchasing')->assertOk()->assertSee('Dalam tahap pengembangan.')
            ->assertSee('Stay tune!')->assertDontSee('Sisa diterima')->assertDontSee('Pengajuan pembelian dari IC');
        $this->assertCount(0, array_filter($queries, fn($sql) => str_contains($sql, 'purchase_orders') || str_contains($sql, 'purchase_requests')));
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $parent = $xpath->query('//button[@aria-controls="purchasing-submenu"]')->item(0);
        $this->assertSame('button', $parent->getAttribute('type'));
        $this->assertFalse($parent->hasAttribute('href'));
        $this->assertSame('', $parent->getAttribute('aria-current'));
        $active = $xpath->query('//*[@id="purchasing-submenu"]//a[@aria-current="page"]');
        $this->assertCount(1, $active);
        $this->assertSame('Dashboard', trim($active->item(0)->textContent));
        $this->assertSame(route('purchasing.dashboard'), $active->item(0)->getAttribute('href'));
        $this->assertSame('purchasing.dashboard', auth()->user()->homeRoute());
    }

    public function test_dashboard_livewire_rechecks_access_after_revocation(): void
    {
        $profile = $this->profile(['purchasing.requests.view']);
        $this->actingAs($this->employee($profile));
        $page = Livewire::test(\App\Modules\Purchasing\Livewire\DashboardPage::class)->assertSee('Stay tune!');
        $profile->update(['permissions' => []]);
        $page->call('$refresh')->assertForbidden();
    }

    public function test_attention_tracks_unprocessed_requests_and_respects_revocation(): void
    {
        $indicator = \App\Modules\Purchasing\Livewire\AttentionIndicator::class;
        Livewire::test($indicator)->assertDontSee('data-purchasing-attention', false);
        $request = $this->request();
        $page = Livewire::test($indicator)->assertSee('1 pengajuan pembelian belum diproses')
            ->assertSee('data-purchasing-attention="requests"', false);
        $profile = $this->profile(['purchasing.requests.view']);
        $user = $this->employee($profile);
        $this->actingAs($user);
        $page->call('refreshAttention')->assertSee('1 pengajuan pembelian belum diproses');
        $profile->update(['permissions' => ['purchasing.orders.view']]);
        $page->call('refreshAttention')->assertDontSee('data-purchasing-attention', false);
        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]));
        app(ProcurementService::class)->approve($request->id, now()->format('Y-m-d'), now()->format('Y-m-d'));
        $page->call('refreshAttention')->assertDontSee('data-purchasing-attention', false);
    }
}