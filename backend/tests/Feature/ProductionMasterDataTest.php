<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Livewire\ProductionReferencePage;
use App\Modules\MasterData\Models\Division;
use App\Modules\MasterData\Models\JobTitle;
use App\Modules\MasterData\Models\ProductionReference;
use App\Modules\Produksi\Livewire\BoardPage;
use App\Modules\Produksi\Livewire\CopperBreakPage;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Models\CopperBreakEntry;
use App\Modules\Produksi\Services\CopperBreakService;
use App\Modules\Produksi\Services\OutputSummaryService;
use App\Modules\Produksi\Services\ProductionSupervisorService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionMasterDataTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\UsesBoardSchedule;

    protected UserRole $authenticatedRole = UserRole::SuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reference(string $section, string $code, array $extra = []): ProductionReference
    {
        return ProductionReference::create(array_replace(['section' => $section, 'code' => $code, 'name' => $code, 'is_active' => true], $extra));
    }

    private function supervisor(string $name = 'SPV Baru', string $job = 'SPV', string $division = 'Produksi', bool $active = true): User
    {
        $title = JobTitle::firstOrCreate(['name' => $job], ['code' => strtoupper(str_replace(' ', '-', $job)), 'is_active' => true]);
        $department = Division::firstOrCreate(['name' => $division], ['code' => strtoupper(str_replace(' ', '-', $division)), 'is_active' => true]);

        return User::factory()->create(['name' => $name, 'job_title_id' => $title->id, 'division_id' => $department->id,
            'job_title' => $job, 'department' => $division, 'is_active' => $active]);
    }

    private function boardForm(array $fields = [])
    {
        $this->reference('crystallizers', 'CR-NEW');
        $component = Livewire::test(BoardPage::class)->call('openBoardForm')
            ->set('production_schedule_id', (string) $this->boardSchedule((string) ($fields['rod_diameter_mm'] ?? '8'))->id);
        foreach (array_replace([
            'lot' => 'MASTER-LOT', 'take_up' => '1XL', 'cooling' => '1XR', 'crystallizer' => 'CR-NEW',
            'rod_diameter_mm' => '8', 'take_up_speed' => '6000', 'weight_kg' => '0',
            'upcasting_date' => '2026-10-28', 'upcasting_time' => '08:00',
            'estimated_pickup_date' => '2026-10-28', 'estimated_pickup_time' => '10:00',
            'actual_pickup_date' => '2026-10-28', 'actual_pickup_time' => '10:00', 'event_status' => 'putus',
        ], $fields) as $key => $value) {
            $component->set($key, $value);
        }

        return $component;
    }

    private function source(): BoardEntry
    {
        return BoardEntry::create([
            'board_date' => '2026-10-28', 'shift' => 'S1', 'lot' => 'BREAK-LOT',
            'take_up' => '1XL', 'cooling' => '1XR', 'crystallizer' => 'OLD-CR',
            'upcasting_date' => '2026-10-28', 'upcasting_time' => '08:00',
            'actual_pickup_date' => '2026-10-28', 'actual_pickup_time' => '10:00', 'event_status' => 'putus',
        ]);
    }

    public function test_admin_can_manage_all_sections_and_they_are_in_master_navigation(): void
    {
        foreach (ProductionReference::SECTIONS as $section => $title) {
            $this->get('/master-data/'.$section)->assertOk()->assertSee('Master '.$title);
            $form = ['code' => ' new-01 ', 'name' => 'Data Baru', 'description' => 'Contoh', 'is_active' => true];
            if (in_array($section, ['take-ups', 'coilings'], true)) {
                $form['servo'] = 'Y';
            }
            if ($section === 'rod-specifications') {
                $form += ['diameter_mm' => '16', 'weight_ton_per_meter' => '0.0018'];
            }
            Livewire::test(ProductionReferencePage::class, ['section' => $section])->call('openCreate')
                ->set('form', $form)->call('save')->assertHasNoErrors()->assertSet('showForm', false);
            $row = ProductionReference::where('section', $section)->where('code', 'NEW-01')->firstOrFail();
            Livewire::test(ProductionReferencePage::class, ['section' => $section])->call('openCreate')
                ->set('form', $form)->call('save')->assertHasErrors(['form.code', 'form.name']);
            Livewire::test(ProductionReferencePage::class, ['section' => $section])->call('openEdit', $row->id)
                ->set('form.is_active', false)->call('save')->assertHasNoErrors();
            $this->assertFalse($row->fresh()->is_active);
        }
        $this->get('/master-data/items')->assertSee('Produksi Copper Rod')->assertSee('Kejadian Saat Putus');
    }

    public function test_invalid_specifications_and_missing_servo_are_rejected(): void
    {
        Livewire::test(ProductionReferencePage::class, ['section' => 'take-ups'])->call('openCreate')
            ->set('form.code', 'TU-INVALID')->set('form.name', 'Invalid')->call('save')->assertHasErrors('form.servo');
        Livewire::test(ProductionReferencePage::class, ['section' => 'rod-specifications'])->call('openCreate')
            ->set('form.code', 'ROD-INVALID')->set('form.name', 'Invalid')
            ->set('form.diameter_mm', '-1')->set('form.weight_ton_per_meter', '0')->call('save')
            ->assertHasErrors(['form.diameter_mm', 'form.weight_ton_per_meter']);
        Livewire::test(ProductionReferencePage::class, ['section' => 'rod-specifications'])->call('openCreate')
            ->set('form.code', 'ROD-PRECISION')->set('form.name', 'Invalid precision')
            ->set('form.diameter_mm', '16.25')->set('form.weight_ton_per_meter', '0.0018')->call('save')
            ->assertHasErrors('form.diameter_mm');
    }

    public function test_only_admin_can_manage_masters_and_section_is_enforced(): void
    {
        $other = ProductionReference::where('section', 'break-categories')->firstOrFail();
        $this->assertThrows(fn () => Livewire::test(ProductionReferencePage::class, ['section' => 'take-ups'])
            ->call('openEdit', $other->id), ModelNotFoundException::class);
        $this->actingAs(User::factory()->create(['role' => UserRole::OperatorProduksi]));
        foreach (array_keys(ProductionReference::SECTIONS) as $section) {
            $this->get('/master-data/'.$section)->assertForbidden();
        }
        Livewire::test(ProductionReferencePage::class)->assertForbidden();
        Livewire::test(BoardPage::class)->assertOk();
    }

    public function test_custom_equipment_servo_and_rod_weight_drive_board_and_output(): void
    {
        $takeUp = $this->reference('take-ups', 'TU-NEW', ['servo' => 'Y']);
        $coiling = $this->reference('coilings', 'CO-NEW', ['servo' => 'Y']);
        $rod = $this->reference('rod-specifications', 'ROD-16', ['diameter_mm' => 16, 'weight_ton_per_meter' => 0.0018]);
        $this->boardForm(['take_up' => 'TU-NEW', 'cooling' => 'CO-NEW', 'rod_diameter_mm' => '16'])
            ->assertViewHas('inlineOutputMt', fn ($value) => abs($value - 1.296) < 0.0000001)
            ->call('save')->assertHasNoErrors();
        $entry = BoardEntry::firstOrFail();
        $this->assertSame($takeUp->id, $entry->take_up_master_id);
        $this->assertSame($coiling->id, $entry->cooling_master_id);
        $this->assertSame($rod->id, $entry->rod_diameter_mm_master_id);
        $this->assertNotNull($entry->crystallizer_master_id);
        $this->assertSame('Y', $entry->effective_servo);

        $takeUp->update(['code' => 'TU-RENAMED', 'servo' => 'X', 'is_active' => false]);
        $coiling->update(['servo' => 'X', 'is_active' => false]);
        $rod->update(['diameter_mm' => 17, 'weight_ton_per_meter' => 0.002, 'is_active' => false]);
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->assertSee('TU-NEW (riwayat)')
            ->assertViewHas('inlineOutputMt', fn ($value) => abs($value - 1.296) < 0.0000001)
            ->set('remarks', 'Tetap dapat diedit')->call('save')->assertHasNoErrors();
        $entry->refresh();
        $this->assertSame('TU-NEW', $entry->take_up);
        $this->assertSame('Y', $entry->effective_servo);
        $this->assertSame(16.0, $entry->rod_diameter_mm);
        $this->assertSame(0.0018, $entry->rod_weight_ton_per_meter);
        Livewire::test(ProductionReferencePage::class, ['section' => 'take-ups'])->call('openCreate')
            ->set('form.code', 'TU-NEW')->set('form.name', 'Replacement')->set('form.servo', 'X')
            ->call('save')->assertHasErrors('form.code');
        $summary = app(OutputSummaryService::class)->getSummary('2026-10-01', '2026-10-30');
        $shift = collect($summary['days'])->firstWhere('date', '2026-10-28')['shifts']['S1'];
        $this->assertEqualsWithDelta(1.296, $shift['output_mt'], 0.0000001);
        $this->assertEqualsWithDelta(2, $shift['hours_y'], 0.0000001);
        Livewire::test(BoardPage::class)->assertViewHas('takeupOptions', fn ($options) => ! $options->contains('value', 'TU-NEW'));
    }

    public function test_new_board_rejects_inactive_arbitrary_and_conflicting_master_values(): void
    {
        ProductionReference::where('section', 'take-ups')->where('code', '1XL')->update(['is_active' => false]);
        $this->boardForm(['rod_diameter_mm' => '10', 'crystallizer' => 'FAKE'])
            ->call('save')->assertHasErrors(['take_up', 'rod_diameter_mm', 'crystallizer']);
        $this->assertDatabaseCount('board_entries', 0);
        Livewire::test(BoardPage::class)->set('rod_diameter_mm', 'abc')->assertOk();
        ProductionReference::where('section', 'take-ups')->where('code', '1XL')->update(['is_active' => true]);
        $component = Livewire::test(BoardPage::class)->set('production_schedule_id', (string) $this->boardSchedule()->id);
        foreach (['lot' => 'CONFLICT', 'take_up' => '1XL', 'cooling' => '2YR', 'rod_diameter_mm' => '8', 'take_up_speed' => '6000',
            'crystallizer' => 'CR-NEW', 'upcasting_date' => '2026-10-28', 'upcasting_time' => '08:00',
            'estimated_pickup_date' => '2026-10-28', 'estimated_pickup_time' => '10:00'] as $field => $value) {
            $component->set($field, $value);
        }
        $component->call('save')->assertHasErrors('cooling');
        $this->assertDatabaseCount('board_entries', 0);
    }

    public function test_copper_break_uses_dynamic_masters_and_preserves_archived_selections(): void
    {
        $source = $this->source();
        $spv = $this->supervisor();
        $event = $this->reference('break-events', 'EVT-NEW', ['name' => 'Gangguan baru']);
        $condition = $this->reference('break-conditions', 'AFTER-NEW', ['name' => 'Diperbaiki']);
        $category = $this->reference('break-categories', 'CAT-NEW', ['name' => 'Kategori baru']);
        Livewire::test(CopperBreakPage::class)->call('openCopperForm')->set('board_entry_id', (string) $source->id)
            ->assertSee('SPV Baru')->set('supervisor_user_id', (string) $spv->id)->set('event_at_break', 'Gangguan baru')
            ->set('event_after_break', 'Diperbaiki')->set('category', 'Kategori baru')->set('frequency', '2')
            ->call('save')->assertHasNoErrors()->assertSet('showCopperForm', false);
        $detail = CopperBreakEntry::firstOrFail();
        $this->assertSame($spv->id, $detail->supervisor_user_id);
        $spv->update(['name' => 'Nama karyawan diubah', 'is_active' => false]);
        foreach (['event_at_break' => $event, 'event_after_break' => $condition, 'category' => $category] as $field => $row) {
            $this->assertSame($row->id, $detail->{$field.'_master_id'});
            $row->update(['name' => $row->name.' diubah', 'is_active' => false]);
        }
        Livewire::test(CopperBreakPage::class)->call('edit', $source->id)->assertSee('SPV Baru (riwayat)')
            ->set('remarks', 'Riwayat tetap')->call('save')->assertHasNoErrors();
        $this->assertSame('SPV Baru', $detail->fresh()->supervisor);
        $this->assertSame('Gangguan baru', $detail->fresh()->event_at_break);
        Livewire::test(CopperBreakPage::class)->assertViewHas('supervisors', fn ($options) => ! $options->contains('value', (string) $spv->id));
    }

    public function test_copper_break_service_rejects_bypassed_master_values_and_invalid_frequency(): void
    {
        $source = $this->source();
        $spv = $this->supervisor('Danil');
        foreach ([['supervisor_user_id' => 'Bebas'], ['supervisor_user_id' => (string) $spv->id, 'category' => 'Bebas'], ['supervisor_user_id' => (string) $spv->id, 'frequency' => '-1']] as $data) {
            try {
                app(CopperBreakService::class)->upsertDetail($source->id, $data);
                $this->fail('Invalid master selection should be rejected.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertDatabaseCount('copper_break_entries', 0);
        Livewire::test(CopperBreakPage::class)->call('openCopperForm')->set('board_entry_id', (string) $source->id)
            ->set('supervisor_user_id', (string) $spv->id)->set('category', 'Bebas')->call('save')->assertHasErrors('category')->assertSet('showCopperForm', true);
    }

    public function test_migration_imports_historical_values_without_rewriting_transactions(): void
    {
        $migration = require database_path('migrations/2026_10_29_000019_create_production_masters.php');
        $employeesMigration = require database_path('migrations/2026_10_29_000020_use_employees_for_production_supervisors.php');
        $employeesMigration->down();
        $migration->down();
        $source = $this->source();
        $source->update(['rod_diameter_mm' => 8, 'take_up_speed' => 6000]);
        $detail = CopperBreakEntry::create(['board_entry_id' => $source->id, 'event_date' => $source->board_date,
            'supervisor' => 'SPV Lama', 'event_at_break' => 'Kejadian Lama', 'event_after_break' => 'Kondisi Lama', 'category' => 'Kategori Lama']);
        $migration->up();
        $source->refresh();
        $detail->refresh();
        $this->assertSame('OLD-CR', $source->crystallizer);
        $this->assertNotNull($source->crystallizer_master_id);
        $this->assertNotNull($source->take_up_master_id);
        $this->assertSame(0.00045, $source->rod_weight_ton_per_meter);
        $this->assertSame('SPV Lama', $detail->supervisor);
        foreach (['supervisor', 'event_at_break', 'event_after_break', 'category'] as $field) {
            $this->assertNotNull($detail->{$field.'_master_id'});
        }
        $employeesMigration->up();
        $detail->refresh();
        $this->assertNull($detail->supervisor_user_id);
        Livewire::test(CopperBreakPage::class)->call('edit', $source->id)->call('save')->assertHasNoErrors();
    }

    public function test_supervisor_choices_use_active_employee_placement_and_reject_other_departments(): void
    {
        $correct = $this->supervisor('SPV Cocok', 'Supervisor Produksi');
        $sameName = $this->supervisor('SPV Cocok', 'Supervisor Produksi');
        $wrongDivision = $this->supervisor('SPV PPIC', 'SPV', 'PPIC');
        $wrongJob = $this->supervisor('Operator Produksi', 'Operator');
        $inactive = $this->supervisor('SPV Nonaktif', 'SPV', 'Produksi', false);
        $service = app(ProductionSupervisorService::class);
        $ids = $service->options()->pluck('value')->all();
        $this->assertContains((string) $correct->id, $ids);
        $this->assertContains((string) $sameName->id, $ids);
        foreach ([$wrongDivision, $wrongJob, $inactive] as $employee) {
            $this->assertNotContains((string) $employee->id, $ids);
            $this->assertThrows(fn () => $service->resolve((string) $employee->id), ValidationException::class);
        }
        $source = $this->source();
        $detail = app(CopperBreakService::class)->upsertDetail($source->id, ['supervisor_user_id' => (string) $sameName->id, 'supervisor' => 'Nama palsu']);
        $this->assertSame($sameName->id, $detail->supervisor_user_id);
        $this->assertSame('SPV Cocok', $detail->supervisor);
        JobTitle::find($correct->job_title_id)->update(['is_active' => false]);
        $this->assertFalse($service->options()->contains('value', (string) $correct->id));
        JobTitle::find($correct->job_title_id)->update(['is_active' => true]);
        Division::find($correct->division_id)->update(['is_active' => false]);
        $this->assertFalse($service->options()->contains('value', (string) $correct->id));
    }

    public function test_supervisor_migration_links_only_unambiguous_employees_and_removes_redundant_master(): void
    {
        $migration = require database_path('migrations/2026_10_29_000020_use_employees_for_production_supervisors.php');
        $migration->down();
        $correct = $this->supervisor('SPV Unik');
        $this->supervisor('Nama Sama');
        $this->supervisor('Nama Sama');
        $this->supervisor('SPV PPIC', 'SPV', 'PPIC');
        $details = [];
        foreach (['SPV Unik', 'Nama Sama', 'SPV PPIC', 'Nama Lama'] as $name) {
            $source = $this->source();
            $details[$name] = CopperBreakEntry::create(['board_entry_id' => $source->id, 'event_date' => $source->board_date, 'supervisor' => $name]);
        }
        $migration->up();
        $this->assertSame($correct->id, $details['SPV Unik']->fresh()->supervisor_user_id);
        foreach (['Nama Sama', 'SPV PPIC', 'Nama Lama'] as $name) {
            $this->assertNull($details[$name]->fresh()->supervisor_user_id);
            $this->assertSame($name, $details[$name]->fresh()->supervisor);
        }
        $this->assertFalse(Schema::hasColumn('copper_break_entries', 'supervisor_master_id'));
        $this->assertDatabaseMissing('master_data_production_references', ['section' => 'production-supervisors']);
        $this->get('/master-data/production-supervisors')->assertNotFound();
    }

    public function test_master_section_survives_a_fresh_livewire_http_request(): void
    {
        $response = $this->get('/master-data/rod-specifications')->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES, 'UTF-8'))
            ->first(fn ($value) => json_decode($value, true)['memo']['name'] === 'master-data.production-reference-page');
        $this->assertNotNull($snapshot);
        $pdo = $this->app['db']->connection()->getPdo();
        $id = auth()->id();
        $this->refreshApplication();
        $this->app['db']->connection()->setPdo($pdo);
        $this->actingAs(User::findOrFail($id));
        $this->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'openCreate', 'params' => []]],
        ]]], ['X-Livewire' => 'true'])->assertOk()->assertSee('Bobot rod per meter');
    }

    public function test_dropdown_options_are_sorted_naturally_and_alphabetically(): void
    {
        $service = app(\App\Modules\Produksi\Services\ProductionMasterDataService::class);

        // Ensure crystallizers are naturally sorted (1, 2, 3, 4, 10...)
        ProductionReference::where('section', 'crystallizers')->delete();
        $this->reference('crystallizers', '3', ['name' => 'Crystalizer 3']);
        $this->reference('crystallizers', '1', ['name' => 'Crystallizer 1']);
        $this->reference('crystallizers', '10', ['name' => 'Crystallizer 10']);
        $this->reference('crystallizers', '2', ['name' => 'Crystallizer 2']);
        $this->reference('crystallizers', '4', ['name' => 'Crystalizer 4']);

        $crystallizerLabels = $service->options('crystallizers')->pluck('label')->all();
        $this->assertSame([
            '1 — Crystallizer 1',
            '2 — Crystallizer 2',
            '3 — Crystalizer 3',
            '4 — Crystalizer 4',
            '10 — Crystallizer 10',
        ], $crystallizerLabels);

        // Ensure take-ups are naturally sorted
        $takeupLabels = $service->options('take-ups')->pluck('label')->all();
        $expectedTakeups = ['1XL', '1XR', '2YL', '2YR', '3XL', '3XR', '4YL', '4YR', '5XL', '5XR', '6YL', '6YR'];
        $this->assertSame($expectedTakeups, $takeupLabels);

        // Ensure supervisor options are naturally sorted A-Z
        $this->supervisor('Zul', 'SPV');
        $this->supervisor('Agus', 'SPV');
        $this->supervisor('Budi', 'SPV');
        $supervisorLabels = app(ProductionSupervisorService::class)->options()->pluck('label')->all();
        $sortedSupervisorLabels = $supervisorLabels;
        natcasesort($sortedSupervisorLabels);
        $this->assertSame(array_values($sortedSupervisorLabels), $supervisorLabels);
    }
}
