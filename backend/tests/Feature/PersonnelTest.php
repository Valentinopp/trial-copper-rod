<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Models\JobTitle;
use App\Modules\MasterData\Models\Division;
use App\Modules\MasterData\Livewire\PersonnelPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
class PersonnelTest extends TestCase {
    use RefreshDatabase;
    protected UserRole $authenticatedRole=UserRole::SuperAdmin;
    private function form(array $overrides=[]): array {
        $job=JobTitle::create(['code'=>'STAFF','name'=>'Staff','is_active'=>true]);
        $division=Division::create(['code'=>'PPIC','name'=>'PPIC','is_active'=>true]);
        return array_replace(['username'=>'staff.new','employee_number'=>'K-001','name'=>'Karyawan Baru','email'=>'','password'=>'test-password','job_title_id'=>$job->id,'division_id'=>$division->id,'role'=>'ppic','is_active'=>true],$overrides);
    }
    public function test_pages_and_manual_master_codes(): void {
        foreach(['job-titles'=>'Jabatan','divisions'=>'Divisi','employees'=>'Karyawan'] as $path=>$title)
            $this->get('/master-data/'.$path)->assertOk()->assertSee('Master '.$title)->assertSee('<title>Master '.$title.' - irris.next</title>', false);
        foreach(['job-titles'=>JobTitle::class,'divisions'=>Division::class] as $section=>$model) {
            Livewire::test(PersonnelPage::class,['section'=>$section])->call('openCreate')->set('form',['code'=>' manual-01 ','name'=>'Contoh','description'=>'','is_active'=>true])->call('save')->assertHasNoErrors();
            $this->assertSame('MANUAL-01',$model::first()->code);
            Livewire::test(PersonnelPage::class,['section'=>$section])->call('openCreate')->set('form',['code'=>'manual-01','name'=>'Duplikat','description'=>'','is_active'=>true])->call('save')->assertHasErrors('form.code');
        }
    }
    public function test_employee_creation_edit_password_and_login(): void {
        $form=$this->form();
        Livewire::test(PersonnelPage::class)->call('openCreate')->set('form',$form)->call('save')->assertHasNoErrors()->assertSet('form',[]);
        $user=User::where('username','staff.new')->firstOrFail();
        $this->assertNull($user->email); $this->assertTrue(Hash::check('test-password',$user->password));
        $this->assertSame('Staff',$user->job_title); $this->assertSame('PPIC',$user->department);
        $old=$user->password;
        Livewire::test(PersonnelPage::class)->call('openEdit',$user->id)->set('form.name','Nama Baru')->call('save')->assertHasNoErrors();
        $this->assertSame($old,$user->fresh()->password);
        auth()->logout();
        $this->post('/login',['username'=>'STAFF.NEW','password'=>'test-password'])->assertRedirect(route('ppic.production-plan'));
        $this->assertAuthenticatedAs($user);
        $user->update(['is_active'=>false]);
        $this->get('/master-data/employees')->assertForbidden();
        auth()->logout();
        $this->post('/login',['username'=>'staff.new','password'=>'test-password'])->assertSessionHasErrors('username');
    }
    public function test_inactive_master_and_duplicate_username_rejected(): void {
        $form=$this->form(); JobTitle::find($form['job_title_id'])->update(['is_active'=>false]);
        Livewire::test(PersonnelPage::class)->call('openCreate')->set('form',$form)->call('save')->assertHasErrors('form.job_title_id');
        $this->assertFalse(User::where('username','staff.new')->exists());
        JobTitle::find($form['job_title_id'])->update(['is_active'=>true]); $form['username']=auth()->user()->username;
        Livewire::test(PersonnelPage::class)->call('openCreate')->set('form',$form)->call('save')->assertHasErrors('form.username');
    }
    public function test_last_admin_and_self_deactivation_protected(): void {
        $form=$this->form(); $user=auth()->user(); $user->update(['job_title_id'=>$form['job_title_id'],'division_id'=>$form['division_id']]);
        Livewire::test(PersonnelPage::class)->call('openEdit',$user->id)->set('form.is_active',false)->call('save')->assertHasErrors('form.is_active');
        Livewire::test(PersonnelPage::class)->call('openEdit',$user->id)->set('form.role','ppic')->call('save')->assertHasErrors('form.role');
        $this->assertSame(UserRole::SuperAdmin,$user->fresh()->role);
    }
    public function test_non_admin_cannot_access_pages_or_livewire(): void {
        $this->actingAs(User::factory()->create(['role'=>UserRole::Manager]));
        foreach(['employees','job-titles','divisions'] as $path) $this->get('/master-data/'.$path)->assertForbidden();
        Livewire::test(PersonnelPage::class)->assertForbidden();
    }
    public function test_fresh_livewire_request_keeps_section_and_authorization(): void {
        $response=$this->get('/master-data/divisions')->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot=collect($matches[1])->map(fn($value)=>html_entity_decode($value,ENT_QUOTES,'UTF-8'))->first(fn($value)=>json_decode($value,true)['memo']['name']==='master-data.personnel-page');
        $this->assertNotNull($snapshot);
        $pdo=$this->app['db']->connection()->getPdo(); $id=auth()->id(); $this->refreshApplication();
        $this->app['db']->connection()->setPdo($pdo); $this->actingAs(User::findOrFail($id));
        $this->postJson('/livewire/update',['components'=>[['snapshot'=>$snapshot,'updates'=>[],'calls'=>[['path'=>'','method'=>'openCreate','params'=>[]]]]]],['X-Livewire'=>'true'])
            ->assertOk()->assertSee('Kode *')->assertDontSee('Nomor karyawan');
    }
    public function test_legacy_accounts_are_backfilled_without_changing_passwords(): void {
        $migration=require database_path('migrations/2026_10_28_000015_create_personnel_masters.php');
        $migration->down();
        $user=User::factory()->create(['job_title'=>' Staff ', 'department'=>'Produksi']);
        $other=User::factory()->create(['job_title'=>'staff', 'department'=>'produksi']);
        $hash=$user->password; $migration->up();
        $user->refresh(); $other->refresh();
        $this->assertNotNull($user->job_title_id); $this->assertNotNull($user->division_id);
        $this->assertSame($user->job_title_id,$other->job_title_id);
        $this->assertSame($user->division_id,$other->division_id);
        $this->assertSame($hash,$user->password);
    }
    public function test_inactive_existing_placement_is_preserved_and_renames_update_legacy_fields(): void {
        $form=$this->form();
        Livewire::test(PersonnelPage::class)->call('openCreate')->set('form',$form)->call('save')->assertHasNoErrors();
        $user=User::where('username','staff.new')->firstOrFail();
        Livewire::test(PersonnelPage::class,['section'=>'job-titles'])->call('openEdit',$form['job_title_id'])->set('form.name','Staff Baru')->set('form.is_active',false)->call('save')->assertHasNoErrors();
        $this->assertSame('Staff Baru',$user->fresh()->job_title);
        Livewire::test(PersonnelPage::class)->call('openEdit',$user->id)->set('form.name','Nama Diubah')->call('save')->assertHasNoErrors();
        $this->assertSame($form['job_title_id'],$user->fresh()->job_title_id);
    }
}