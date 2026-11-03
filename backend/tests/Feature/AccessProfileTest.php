<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Livewire\AccessProfilePage;
use App\Modules\MasterData\Models\AccessProfile;
use App\Modules\MasterData\Models\Division;
use App\Modules\MasterData\Models\JobTitle;
use App\Modules\MasterData\Services\AccessProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccessProfileTest extends TestCase
{
    use RefreshDatabase;

    protected UserRole $authenticatedRole = UserRole::SuperAdmin;

    private function combination(string $code = '01'): array
    {
        return [
            'job_title_id' => JobTitle::create(['code' => 'JBT-'.$code, 'name' => 'Supervisor '.$code, 'is_active' => true])->id,
            'division_id' => Division::create(['code' => 'DIV-'.$code, 'name' => 'Produksi '.$code, 'is_active' => true])->id,
            'is_active' => true,
        ];
    }

    public function test_page_creates_and_edits_a_combination_without_changing_user_access(): void
    {
        $this->get('/master-data/access-profiles')->assertOk()
            ->assertSee('<title>Hak Akses - irris.next</title>', false)
            ->assertSee('Belum ada pengaturan hak akses')->assertSee('Organisasi &amp; Karyawan', false);
        $form = $this->combination();
        $users = User::orderBy('id')->get()->toArray();
        $page = Livewire::test(AccessProfilePage::class)->call('openCreate')->set('form', $form)
            ->call('save')->assertHasNoErrors()->assertSet('showForm', false)->assertSet('form', [])
            ->assertSee('Supervisor 01')->assertSee('Produksi 01')->assertSee('0 izin fitur');
        $profile = AccessProfile::firstOrFail();
        $this->assertSame('Supervisor 01', $profile->jobTitle->name);
        $this->assertSame('Produksi 01', $profile->division->name);
        $page->call('openEdit', $profile->id)->call('save')->assertHasNoErrors();
        $this->assertSame(1, AccessProfile::count());
        $page->call('openEdit', $profile->id)->set('form.is_active', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($profile->fresh()->is_active);
        $this->assertSame($users, User::orderBy('id')->get()->toArray());
        $this->get('/master-data/employees')->assertOk();
    }

    public function test_duplicate_pair_is_rejected_on_create_and_edit_even_when_inactive(): void
    {
        $form = $this->combination();
        $first = app(AccessProfileService::class)->save($form);
        $first->update(['is_active' => false]);
        Livewire::test(AccessProfilePage::class)->call('openCreate')->set('form', $form)
            ->call('save')->assertHasErrors('form.division_id');
        $other = app(AccessProfileService::class)->save($this->combination('02'));
        Livewire::test(AccessProfilePage::class)->call('openEdit', $other->id)->set('form', $form)
            ->call('save')->assertHasErrors('form.division_id');
        $this->assertSame(2, AccessProfile::count());
    }

    public function test_same_job_can_be_registered_for_different_divisions(): void
    {
        $form = $this->combination();
        app(AccessProfileService::class)->save($form);
        $division = Division::create(['code' => 'PPIC', 'name' => 'PPIC', 'is_active' => true]);
        app(AccessProfileService::class)->save(array_replace($form, ['division_id' => $division->id]));
        $this->assertSame(2, AccessProfile::count());
    }

    public function test_required_and_nonexistent_ids_are_rejected(): void
    {
        Livewire::test(AccessProfilePage::class)->call('openCreate')->call('save')
            ->assertHasErrors(['form.job_title_id', 'form.division_id']);
        Livewire::test(AccessProfilePage::class)->call('openCreate')
            ->set('form', ['job_title_id' => 999999, 'division_id' => 999999, 'is_active' => true])
            ->call('save')->assertHasErrors(['form.job_title_id', 'form.division_id']);
        $this->assertSame(0, AccessProfile::count());
    }

    public function test_inactive_masters_cannot_be_assigned_but_existing_pair_can_be_kept(): void
    {
        $form = $this->combination();
        JobTitle::findOrFail($form['job_title_id'])->update(['is_active' => false]);
        Livewire::test(AccessProfilePage::class)->call('openCreate')->set('form', $form)
            ->call('save')->assertHasErrors('form.job_title_id');
        JobTitle::findOrFail($form['job_title_id'])->update(['is_active' => true]);
        Division::findOrFail($form['division_id'])->update(['is_active' => false]);
        Livewire::test(AccessProfilePage::class)->call('openCreate')->set('form', $form)
            ->call('save')->assertHasErrors('form.division_id');
        Division::findOrFail($form['division_id'])->update(['is_active' => true]);
        $profile = app(AccessProfileService::class)->save($form);
        JobTitle::findOrFail($form['job_title_id'])->update(['is_active' => false]);
        Division::findOrFail($form['division_id'])->update(['is_active' => false]);
        Livewire::test(AccessProfilePage::class)->call('openEdit', $profile->id)
            ->assertSee('JBT-01')->assertSee('DIV-01')->set('form.is_active', false)
            ->call('save')->assertHasNoErrors();
    }

    public function test_search_status_and_pagination(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $form = $this->combination(str_pad((string) $i, 2, '0', STR_PAD_LEFT));
            app(AccessProfileService::class)->save(array_replace($form, ['is_active' => $i !== 16]));
        }
        $page = Livewire::test(AccessProfilePage::class)->assertViewHas('rows', fn ($rows) => $rows->total() === 16 && $rows->count() === 15);
        $page->call('gotoPage', 2)->assertViewHas('rows', fn ($rows) => $rows->currentPage() === 2);
        $page->set('search', 'jbt-01')->assertViewHas('rows', fn ($rows) => $rows->currentPage() === 1 && $rows->total() === 1);
        $page->set('search', 'PRODUKSI 02')->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $page->set('search', '')->set('status', 'inactive')->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $page->set('status', 'active')->assertViewHas('rows', fn ($rows) => $rows->total() === 15);
    }

    public function test_non_admin_and_inactive_admin_cannot_access_page_or_component(): void
    {
        foreach ([UserRole::Manager, UserRole::Ppic, UserRole::OperatorProduksi] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/master-data/access-profiles')->assertForbidden();
            Livewire::test(AccessProfilePage::class)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => false]));
        $this->get('/master-data/access-profiles')->assertForbidden();
        Livewire::test(AccessProfilePage::class)->assertForbidden();
    }

    public function test_service_authorization_and_cancel_do_not_create_records(): void
    {
        $form = $this->combination();
        Livewire::test(AccessProfilePage::class)->call('openCreate')->set('form', $form)
            ->call('closeForm')->call('save')->assertSet('form', []);
        $this->assertSame(0, AccessProfile::count());
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->expectException(AuthorizationException::class);
        app(AccessProfileService::class)->save($form);
    }

    public function test_database_also_prevents_duplicate_pairs(): void
    {
        $form = $this->combination();
        AccessProfile::create($form);
        $this->expectException(QueryException::class);
        AccessProfile::create($form);
    }

    public function test_fresh_livewire_request_can_open_and_save_form(): void
    {
        $form = $this->combination();
        $response = $this->get('/master-data/access-profiles')->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES, 'UTF-8'))
            ->first(fn ($value) => json_decode($value, true)['memo']['name'] === 'master-data.access-profile-page');
        $this->assertNotNull($snapshot);
        $connection = $this->app['db']->connection();
        $userId = auth()->id();
        $this->refreshApplication();
        $this->app['db']->extend('sqlite', fn () => $connection);
        $this->actingAs(User::findOrFail($userId));
        $response = $this->postJson('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => [],
            'calls' => [['path' => '', 'method' => 'openCreate', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertOk();
        $snapshot = $response->json('components.0.snapshot');
        $this->postJson('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => ['form' => $form],
            'calls' => [['path' => '', 'method' => 'save', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertOk();
        $this->assertDatabaseHas('access_profiles', $form);
    }
}