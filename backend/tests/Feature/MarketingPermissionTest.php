<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Marketing\Livewire\SalesPage;
use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\Marketing\Services\SalesService;
use App\Modules\MasterData\Livewire\AccessProfilePage;
use App\Modules\MasterData\Models\AccessProfile;
use App\Modules\MasterData\Models\Division;
use App\Modules\MasterData\Models\JobTitle;
use App\Modules\MasterData\Services\AccessProfileService;
use App\Services\AccessPermissionService;
use App\Services\SinoContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingPermissionTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\UsesMasterSales;

    protected UserRole $authenticatedRole = UserRole::SuperAdmin;

    private function profile(array $permissions = []): AccessProfile
    {
        $suffix = (string) (AccessProfile::count() + 1);
        $job = JobTitle::create(['code' => 'SUP-'.$suffix, 'name' => 'Supervisor '.$suffix, 'is_active' => true]);
        $division = Division::create(['code' => 'DIV-'.$suffix, 'name' => 'Divisi '.$suffix, 'is_active' => true]);
        return AccessProfile::create(['job_title_id' => $job->id, 'division_id' => $division->id,
            'permissions' => $permissions, 'is_active' => true]);
    }

    private function employee(AccessProfile $profile, UserRole $role = UserRole::Marketing): User
    {
        return User::factory()->create(['role' => $role, 'job_title_id' => $profile->job_title_id,
            'division_id' => $profile->division_id]);
    }

    public function test_checklist_persists_and_create_automatically_grants_view(): void
    {
        $profile = $this->profile();
        Livewire::test(AccessProfilePage::class)->call('openEdit', $profile->id)
            ->assertSee('Lihat penjualan')->assertSee('Catat penjualan')
            ->set('form.permissions', ['marketing.sales.create'])
            ->assertSet('form.permissions', ['marketing.sales.create', 'marketing.sales.view'])
            ->call('save')->assertHasNoErrors();
        $this->assertEqualsCanonicalizing(['marketing.sales.view', 'marketing.sales.create'], $profile->fresh()->permissions);
        Livewire::test(AccessProfilePage::class)->call('openEdit', $profile->id)
            ->set('form.permissions', [])->call('save')->assertHasNoErrors();
        $this->assertSame([], $profile->fresh()->permissions);
    }

    public function test_service_validates_known_permissions_normalizes_dependency_and_preserves_omitted_permissions(): void
    {
        $profile = $this->profile();
        $service = app(AccessProfileService::class);
        $data = $profile->only(['job_title_id', 'division_id', 'is_active']);
        $service->save($data + ['permissions' => ['marketing.sales.create']], $profile->id);
        $service->save($data, $profile->id);
        $this->assertEqualsCanonicalizing(['marketing.sales.view', 'marketing.sales.create'], $profile->fresh()->permissions);
        Livewire::test(AccessProfilePage::class)->call('openEdit', $profile->id)
            ->set('form.permissions', ['marketing.sales.delete'])->call('save')->assertHasErrors('form.permissions.0');
        $this->assertCount(2, $profile->fresh()->permissions);
    }

    public function test_view_only_user_can_read_but_cannot_open_form_or_save(): void
    {
        $this->actingAs($this->employee($this->profile(['marketing.sales.view'])));
        $this->get('/marketing/sales')->assertOk()->assertDontSee('wire:click="openSale"', false);
        $this->get('/marketing')->assertRedirectToRoute('marketing.sales');
        Livewire::test(SalesPage::class)->call('openSale')->assertForbidden();
        Livewire::test(SalesPage::class)->call('saveSale')->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(SalesService::class)->record([], (string) Str::uuid());
    }

    public function test_employee_with_both_permissions_can_create_sales_even_with_a_different_legacy_role(): void
    {
        $product = $this->masterProduct($this->masterMaterial());
        $customer = \App\Modules\MasterData\Models\Customer::create(['code' => 'CUS-TEST', 'name' => 'Customer', 'is_active' => true]);
        $profile = $this->profile(['marketing.sales.view', 'marketing.sales.create']);
        $this->actingAs($this->employee($profile, UserRole::OperatorProduksi));
        $this->get('/marketing/sales')->assertOk()->assertSee('wire:click="openSale"', false);
        Livewire::test(SalesPage::class)->call('openSale')
            ->set('customer_id', (string) $customer->id)->set('product_id', (string) $product->id)
            ->set('quantity', '10')->set('sold_on', now()->format('Y-m-d'))->set('ship_by', now()->addDay()->format('Y-m-d'))
            ->call('saveSale')->assertHasNoErrors()->assertSet('showForm', false);
        $this->assertSame(1, SalesOrder::count());
    }

    public function test_empty_or_null_checklist_denies_marketing_instead_of_falling_back_to_role(): void
    {
        $profile = $this->profile();
        foreach ([[], null] as $permissions) {
            $profile->update(['permissions' => $permissions]);
            $this->actingAs($this->employee($profile));
            $this->get('/marketing/sales')->assertForbidden();
            $this->get('/marketing/products')->assertForbidden();
            $this->get('/access-unavailable')->assertOk()->assertDontSee('href="'.route('marketing.sales').'"', false);
            Livewire::test(SalesPage::class)->assertForbidden();
            $this->assertFalse(Gate::allows('use-sino'));
        }
    }

    public function test_disabled_profile_masters_or_employee_deny_access(): void
    {
        $profile = $this->profile(['marketing.sales.view']);
        $user = $this->employee($profile);
        $this->actingAs($user);
        $profile->update(['is_active' => false]);
        $this->get('/marketing/sales')->assertForbidden();
        $profile->update(['is_active' => true]);
        $profile->jobTitle->update(['is_active' => false]);
        $this->get('/marketing/sales')->assertForbidden();
        $profile->jobTitle->update(['is_active' => true]);
        $profile->division->update(['is_active' => false]);
        $this->get('/marketing/sales')->assertForbidden();
        $profile->division->update(['is_active' => true]);
        $user->update(['is_active' => false]);
        $this->get('/marketing/sales')->assertForbidden();
    }

    public function test_revoke_create_blocks_an_already_open_form_and_direct_service_call(): void
    {
        $profile = $this->profile(['marketing.sales.view', 'marketing.sales.create']);
        $this->actingAs($this->employee($profile));
        $page = Livewire::test(SalesPage::class)->call('openSale')->assertSet('showForm', true);
        $profile->update(['permissions' => ['marketing.sales.view']]);
        $page->call('saveSale')->assertForbidden();
        $this->assertSame(0, SalesOrder::count());
        $this->expectException(AuthorizationException::class);
        app(SalesService::class)->record([], (string) Str::uuid());
    }

    public function test_revoke_view_blocks_existing_component_and_department_changes_take_effect(): void
    {
        $allowed = $this->profile(['marketing.sales.view']);
        $denied = $this->profile();
        $user = $this->employee($allowed);
        $this->actingAs($user);
        $page = Livewire::test(SalesPage::class);
        $user->update(['job_title_id' => $denied->job_title_id, 'division_id' => $denied->division_id]);
        $page->call('$refresh')->assertForbidden();
        $this->assertFalse(app(AccessPermissionService::class)->allows($user, 'marketing.sales.view'));
    }

    public function test_super_admin_keeps_access_and_non_integrated_modules_keep_their_roles(): void
    {
        $profile = $this->profile();
        $admin = $this->employee($profile, UserRole::SuperAdmin);
        $this->actingAs($admin);
        $this->get('/marketing/sales')->assertOk()->assertSee('wire:click="openSale"', false);
        $this->get('/master-data/access-profiles')->assertOk();
        $this->actingAs($this->employee($profile, UserRole::Ppic));
        $this->get('/ppic/production-plan')->assertOk();
        $this->get('/marketing/sales')->assertForbidden();
    }

    public function test_unconfigured_accounts_keep_legacy_access_during_transition(): void
    {
        $service = app(AccessPermissionService::class);
        foreach ([UserRole::Marketing, UserRole::Manager, UserRole::AsistenManager] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue($service->allows($user, 'marketing.sales.view'));
            $this->assertTrue($service->allows($user, 'marketing.sales.create'));
        }
        $operator = User::factory()->create(['role' => UserRole::OperatorProduksi]);
        $this->assertFalse($service->allows($operator, 'marketing.sales.view'));
        $this->assertFalse($service->allows($operator, 'marketing.sales.unknown'));
    }

    public function test_marketing_login_without_permissions_has_a_real_landing_page(): void
    {
        $user = $this->employee($this->profile());
        auth()->logout();
        $this->post('/login', ['username' => $user->username, 'password' => 'TestPassword!2026'])
            ->assertRedirect(route('access-unavailable'));
        $this->get('/access-unavailable')->assertOk()->assertSee('Akses belum tersedia');
        $this->get('/login')->assertRedirect(route('access-unavailable'));
    }

    public function test_erp_sino_does_not_include_revoked_marketing_context(): void
    {
        $this->actingAs($this->employee($this->profile(), UserRole::Manager));
        $context = app(SinoContextService::class)->build();
        $this->assertArrayNotHasKey('marketing', $context);
        $this->assertArrayHasKey('produksi', $context);
        $this->assertArrayHasKey('ppic', $context);
        $this->assertArrayHasKey('purchasing', $context);
    }
}