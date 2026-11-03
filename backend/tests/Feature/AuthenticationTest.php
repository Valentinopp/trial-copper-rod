<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Livewire\CustomerPage;
use App\Modules\MasterData\Livewire\MasterDataPage;
use App\Modules\MasterData\Livewire\SupplierPage;
use App\Modules\Produksi\Livewire\SinoChat;
use App\Modules\Produksi\Services\SinoAiService;
use Database\Seeders\DummyUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticateByDefault = false;

    private function user(UserRole $role = UserRole::OperatorProduksi, array $values = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'password' => 'TestPassword!2026'], $values));
    }

    public function test_guests_are_redirected_and_login_contains_only_username_and_password_fields(): void
    {
        foreach (['/', '/crystallizer', '/copper-break', '/output', '/ppic', '/marketing/sales', '/purchasing', '/master-data/items'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
        $this->get('/login')->assertOk()->assertSee('name="username"', false)->assertSee('name="password"', false)
            ->assertDontSee('name="email"', false);
    }

    public function test_username_login_is_case_insensitive_records_last_login_and_logs_out(): void
    {
        $user = $this->user(values: ['username' => 'operator.produksi']);
        $this->post('/login', ['username' => '  OPERATOR.PRODUKSI  ', 'password' => 'TestPassword!2026'])
            ->assertRedirect(route('board'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertNotSame('TestPassword!2026', $user->password);
        $this->assertTrue(Hash::check('TestPassword!2026', $user->password));
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_wrong_password_and_inactive_account_cannot_sign_in(): void
    {
        $user = $this->user();
        $this->from('/login')->post('/login', ['username' => $user->username, 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors('username');
        $this->assertGuest();
        $user->update(['is_active' => false]);
        $this->post('/login', ['username' => $user->username, 'password' => 'TestPassword!2026'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_repeated_failed_login_is_throttled_even_when_next_password_is_correct(): void
    {
        $user = $this->user();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'wrong'])->assertSessionHasErrors('username');
        }
        $this->post('/login', ['username' => $user->username, 'password' => 'TestPassword!2026'])
            ->assertSessionHasErrors('username');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('username'));
        $this->assertGuest();
    }

    public function test_operator_only_sees_production_and_cannot_access_other_modules_directly(): void
    {
        $operator = $this->user();
        $this->actingAs($operator);
        foreach (['/', '/crystallizer', '/copper-break', '/output'] as $path) {
            $this->get($path)->assertOk()->assertSee('Copper Rod')->assertSee($operator->name)
                ->assertDontSee('Tanya <strong>Sino</strong>', false)
                ->assertDontSee('produksi.sino-chat')->assertDontSee('href="'.route('ppic.production-plan').'"', false)->assertDontSee('Inventory Control')
                ->assertDontSee('Katalog Produk')->assertDontSee('aria-controls="purchasing-submenu"', false);
        }
        foreach (['/ppic', '/ppic/production-plan', '/ppic/inventory-control', '/marketing', '/marketing/sales', '/purchasing', '/master-data/items', '/master-data/suppliers', '/master-data/customers'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->mock(SinoAiService::class)->shouldNotReceive('ask');
        Livewire::actingAs($operator)->test(SinoChat::class)->assertForbidden();
    }

    public function test_management_roles_have_operational_access_and_sino_without_master_data(): void
    {
        foreach ([UserRole::AsistenManager, UserRole::Manager] as $role) {
            $user = $this->user($role);
            $this->actingAs($user);
            foreach (['/', '/crystallizer', '/copper-break', '/output', '/ppic', '/ppic/production-plan', '/ppic/inventory-control', '/marketing/sales', '/purchasing'] as $path) {
                $this->get($path)->assertOk();
            }
            foreach (['items', 'categories', 'uoms', 'suppliers', 'customers'] as $section) {
                $this->get('/master-data/'.$section)->assertForbidden();
            }
            $this->get('/')->assertSee('Tanya <strong>Sino</strong>', false)->assertSee('Production Plan');
            Livewire::actingAs($user)->test(SinoChat::class)->call('openChat')->assertSet('chatOpen', true);
        }
    }

    private function snapshot(string $path, string $component): string
    {
        $page = $this->get($path)->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
        return collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES, 'UTF-8'))
            ->firstOrFail(fn ($value) => json_decode($value, true)['memo']['name'] === $component);
    }

    private function sendUpdate(string $snapshot, string $method, array $params = [])
    {
        return $this->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
        ]]], ['X-Livewire' => 'true']);
    }

    public function test_operator_cannot_replay_management_or_sino_component_requests(): void
    {
        $manager = $this->user(UserRole::Manager);
        $this->actingAs($manager);
        $marketing = $this->snapshot('/marketing/sales', 'marketing.sales-page');
        $sino = $this->snapshot('/', 'produksi.sino-chat');
        $operator = $this->user();
        $this->actingAs($operator);
        $this->mock(SinoAiService::class)->shouldNotReceive('ask');
        $this->sendUpdate($marketing, 'openSale')->assertForbidden();
        $this->sendUpdate($sino, 'sendChatMessage')->assertForbidden();
    }

    public function test_disabled_account_and_signed_out_sessions_cannot_continue_livewire_requests(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $snapshot = $this->snapshot('/', 'produksi.board-page');
        $user->update(['is_active' => false]);
        $this->sendUpdate($snapshot, 'openBoardForm')->assertForbidden();
        $this->post('/logout')->assertRedirect(route('login'));
        $this->sendUpdate($snapshot, 'openBoardForm')->assertUnauthorized();
    }

    public function test_operator_login_does_not_redirect_to_previously_requested_management_page(): void
    {
        $user = $this->user();
        $this->withSession(['url.intended' => route('ppic.dashboard')])
            ->post('/login', ['username' => $user->username, 'password' => 'TestPassword!2026'])
            ->assertRedirect(route('board'));
    }

    public function test_dummy_seeding_is_idempotent_and_does_not_reset_existing_passwords(): void
    {
        $this->seed(DummyUserSeeder::class);
        $operator = User::where('username', 'produksi')->sole();
        $passwordHash = $operator->password;
        $this->assertSame(UserRole::OperatorProduksi, $operator->role);
        $this->assertSame('Operator', $operator->job_title);
        $this->assertSame('Produksi', $operator->department);
        $this->seed(DummyUserSeeder::class);
        $this->assertDatabaseCount('users', 7);
        $this->assertSame($passwordHash, $operator->fresh()->password);
    }

    public function test_department_accounts_can_only_access_their_own_modules(): void
    {
        $modules = [
            'produksi' => ['/', '/crystallizer', '/copper-break', '/output'],
            'ppic' => ['/ppic', '/ppic/production-plan', '/ppic/inventory-control'],
            'marketing' => ['/marketing/sales'],
            'purchasing' => ['/purchasing'],
            'master-data' => ['/master-data/items', '/master-data/suppliers', '/master-data/customers'],
        ];
        foreach ([UserRole::Ppic, UserRole::Marketing, UserRole::Purchasing] as $role) {
            $user = $this->user($role);
            $this->actingAs($user);
            foreach ($modules as $module => $paths) {
                foreach ($paths as $path) {
                    $response = $this->get($path);
                    $module === $role->value ? $response->assertOk() : $response->assertForbidden();
                }
            }
            $this->get(route($role->homeRoute()))->assertOk()->assertSee('Tanya <strong>Sino</strong>', false)
                ->assertSee('class="topbar-logout"', false);
            Livewire::actingAs($user)->test(SinoChat::class)->call('openChat')->assertSet('chatOpen', true)
                ->assertSet('chatScope', $role->value);
        }
    }

    public function test_each_role_logs_in_to_a_real_module_instead_of_a_dashboard_or_old_intended_url(): void
    {
        foreach ([
            [UserRole::Ppic, 'ppic.production-plan'],
            [UserRole::Marketing, 'marketing.sales'],
            [UserRole::Purchasing, 'purchasing.dashboard'],
            [UserRole::OperatorProduksi, 'board'],
            [UserRole::AsistenManager, 'board'],
            [UserRole::Manager, 'board'],
            [UserRole::SuperAdmin, 'board'],
        ] as [$role, $route]) {
            $user = $this->user($role);
            $this->withSession(['url.intended' => route('ppic.dashboard')])
                ->post('/login', ['username' => $user->username, 'password' => 'TestPassword!2026'])
                ->assertRedirect(route($route))->assertSessionMissing('url.intended');
            $this->get('/login')->assertRedirect(route($route));
            $this->post('/logout')->assertRedirect(route('login'));
        }
    }

    public function test_super_admin_can_access_every_module_and_switch_to_a_master_only_workspace(): void
    {
        $this->actingAs($this->user(UserRole::SuperAdmin));
        foreach (['/', '/crystallizer', '/copper-break', '/output', '/ppic/production-plan', '/ppic/inventory-control', '/marketing/sales', '/purchasing'] as $path) {
            $this->get($path)->assertOk()->assertSee('Master Data')->assertDontSeeHtml('<strong>Master Barang</strong>');
        }
        foreach (['items', 'categories', 'uoms', 'suppliers', 'customers'] as $section) {
            $this->get('/master-data/'.$section)->assertOk()->assertSee('Menu Utama')
                ->assertSee('Master Barang')->assertSee('Master Supplier')->assertSee('Master Customer')
                ->assertDontSee('Production Plan')->assertDontSee('Inventory Control')
                ->assertDontSee('Katalog Produk')->assertDontSee('aria-controls="purchasing-submenu"', false);
        }
    }

    public function test_master_data_components_reject_every_other_role_even_when_called_directly(): void
    {
        foreach (UserRole::cases() as $role) {
            if ($role === UserRole::SuperAdmin) {
                continue;
            }
            $user = $this->user($role);
            $this->actingAs($user);
            $this->get(route($role->homeRoute()))->assertOk()->assertDontSee('workspace-switch');
            foreach ([MasterDataPage::class, SupplierPage::class, CustomerPage::class] as $component) {
                Livewire::actingAs($user)->test($component)->assertForbidden();
            }
        }
    }

    public function test_revoked_super_admin_access_blocks_livewire_requests_from_previously_open_pages(): void
    {
        $admin = $this->user(UserRole::SuperAdmin);
        $this->actingAs($admin);
        $snapshots = [
            [$this->snapshot('/master-data/items', 'master-data.master-data-page'), 'openForm', ['item']],
            [$this->snapshot('/master-data/suppliers', 'master-data.supplier-page'), 'openCreate', []],
            [$this->snapshot('/master-data/customers', 'master-data.customer-page'), 'openCreate', []],
        ];
        $admin->update(['role' => UserRole::Manager]);
        foreach ($snapshots as [$snapshot, $method, $params]) {
            $this->sendUpdate($snapshot, $method, $params)->assertForbidden();
        }
    }

    public function test_explicit_dummy_reset_renames_accounts_without_changing_ids_and_sets_matching_passwords(): void
    {
        $old = $this->user(values: ['username' => 'operator.produksi', 'employee_number' => 'DUMMY-001']);
        $other = $this->user(values: ['username' => 'karyawan']);
        $otherHash = $other->password;
        $this->artisan('demo:users', ['--reset-passwords' => true])->assertSuccessful();
        $this->assertSame('produksi', $old->fresh()->username);
        $this->assertDatabaseMissing('users', ['username' => 'operator.produksi']);
        foreach (['produksi', 'asman', 'manager', 'ppic', 'marketing', 'purchasing', 'it'] as $username) {
            $user = User::where('username', $username)->sole();
            $this->assertTrue(Hash::check($username, $user->password));
            $this->assertNotSame($username, $user->password);
            $this->assertTrue($user->is_active);
        }
        $this->assertSame(UserRole::SuperAdmin, User::where('username', 'it')->sole()->role);
        $this->assertSame($otherHash, $other->fresh()->password);
        $this->assertDatabaseCount('users', 8);
    }

    public function test_dummy_username_collision_rolls_back_instead_of_overwriting_another_account(): void
    {
        $old = $this->user(values: ['username' => 'operator.produksi', 'employee_number' => 'DUMMY-001']);
        $existing = $this->user(values: ['username' => 'asman']);
        $oldHash = $old->password;
        $existingHash = $existing->password;
        try {
            app(DummyUserSeeder::class)->sync(true);
            $this->fail('Username collision must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('sudah digunakan akun lain', $exception->getMessage());
        }
        $this->assertSame('operator.produksi', $old->fresh()->username);
        $this->assertSame($oldHash, $old->fresh()->password);
        $this->assertSame($existingHash, $existing->fresh()->password);
        $this->assertDatabaseCount('users', 2);
    }
}
