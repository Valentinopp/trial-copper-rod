<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Models\Division;
use App\Modules\MasterData\Models\JobTitle;
use App\Modules\MasterData\Models\ProductionReference;
use App\Modules\Produksi\Livewire\BoardPage;
use App\Modules\Produksi\Livewire\CopperBreakPage;
use App\Modules\Produksi\Livewire\OutputPage;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Services\LotEstimateService;
use App\Modules\Produksi\Services\StandaloneOutputService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CopperRodStandaloneTest extends TestCase
{
    use RefreshDatabase;

    protected UserRole $authenticatedRole = UserRole::OperatorProduksi;

    protected function setUp(): void
    {
        parent::setUp();
        config(['copper_rod.standalone' => true]);
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00', 'Asia/Jakarta'));
        ProductionReference::create(['section' => 'crystallizers', 'code' => 'CR-TRIAL', 'name' => 'Crystallizer Trial', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function board(array $values = [])
    {
        $page = Livewire::test(BoardPage::class)->call('openBoardForm');
        foreach (array_replace([
            'lot' => 'LOT-TRIAL', 'take_up' => '1XL', 'cooling' => '1XR', 'crystallizer' => 'CR-TRIAL',
            'rod_diameter_mm' => '8', 'take_up_speed' => '1200', 'upcasting_date' => '2026-11-01', 'upcasting_time' => '08:00',
        ], $values) as $key => $value) $page->set($key, $value);

        return $page;
    }

    public function test_operator_can_start_and_finish_lot_without_any_sales_or_plans(): void
    {
        $page = $this->board()->assertSee('Spesifikasi Cu Rod')->assertSee('Berat reject (kg)')
            ->assertDontSeeHtml('id="board-production_schedule_id"')
            ->assertViewHas('lotEstimate', fn ($v) => abs($v['current_mt'] - 0.9072) < 0.0000001)
            ->call('save')->assertHasNoErrors()->assertSet('showBoardForm', false);
        $entry = BoardEntry::sole();
        $this->assertNull($entry->production_schedule_id);
        $this->assertNull($entry->production_actual_kg);
        $this->assertSame('2026-11-04', $entry->estimated_pickup_date);
        $this->assertSame('06:00', substr($entry->estimated_pickup_time, 0, 5));
        $this->assertSame(0.00045, $entry->rod_weight_ton_per_meter);
        $page->call('edit', $entry->id)->set('actual_pickup_date', '2026-11-02')->set('actual_pickup_time', '10:00')
            ->set('event_status', 'panen')->set('weight_kg', '2000')->set('reject_weight_kg', '100')
            ->call('save')->assertHasNoErrors();
        $entry->refresh();
        $this->assertSame('1900.000000', $entry->production_actual_kg);
        $this->assertSame(0.0, (float) LotEstimateService::calculate($entry->getAttributes())['current_mt']);
        $page->call('exportExcel')->assertDispatched('export-excel', function ($event, $params) {
            $payload = $params[0];
            $column = array_search('Aktual hasil baik (kg)', $payload['headers'], true);
            return $column !== false && count($payload['headers']) === count($payload['rows'][0])
                && $payload['rows'][0][$column] === '1900.000000';
        });
        $page->call('exportPNG')->assertDispatched('export-png', fn ($event, $params) => count($params[0]['headers']) === count($params[0]['rows'][0]));
        foreach (['marketing_sales_orders', 'production_handoffs', 'production_schedules', 'inventory_receipts', 'production_material_reservations'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_master_specs_required_and_duplicate_cycles_rejected_but_edit_is_allowed(): void
    {
        $page = $this->board(['rod_diameter_mm' => ''])->call('save')->assertHasErrors('rod_diameter_mm');
        $page->set('rod_diameter_mm', '99')->call('save')->assertHasErrors('rod_diameter_mm');
        $page->set('rod_diameter_mm', '8')->call('save')->assertHasNoErrors();
        $entry = BoardEntry::sole();
        $this->board()->call('save')->assertHasErrors('lot');
        $page->call('edit', $entry->id)->set('remarks', 'Koreksi trial')->call('save')->assertHasNoErrors();
        $this->assertSame('Koreksi trial', $entry->fresh()->remarks);
        $this->assertDatabaseCount('board_entries', 1);
    }

    public function test_weight_requires_pickup_and_finished_lot_requires_weight_and_valid_reject(): void
    {
        $page = $this->board(['weight_kg' => '2000'])->call('save')->assertHasErrors('weight_kg')
            ->assertSet('weight_kg', '2000')->assertDispatched('notify');
        $page->set('actual_pickup_date', '2026-11-02')->set('actual_pickup_time', '10:00')
            ->call('save')->assertHasErrors('event_status');
        $page->set('event_status', 'panen')->set('weight_kg', '')->call('save')->assertHasErrors('weight_kg');
        $page->set('weight_kg', '100')->set('reject_weight_kg', '101')->call('save')->assertHasErrors('reject_weight_kg');
        $this->assertDatabaseCount('board_entries', 0);
        $page->set('reject_weight_kg', '100')->call('save')->assertHasNoErrors();
        $this->assertSame('0.000000', BoardEntry::sole()->production_actual_kg);
    }

    public function test_putus_details_and_monitoring_work_without_so(): void
    {
        $this->board(['actual_pickup_date' => '2026-11-02', 'actual_pickup_time' => '10:00',
            'event_status' => 'putus', 'weight_kg' => '300', 'reject_weight_kg' => '20'])->call('save')->assertHasNoErrors();
        $entry = BoardEntry::sole();
        $job = JobTitle::create(['code' => 'SPV-TRIAL', 'name' => 'SPV', 'is_active' => true]);
        $division = Division::create(['code' => 'PROD-TRIAL', 'name' => 'Produksi', 'is_active' => true]);
        $spv = User::factory()->create(['job_title_id' => $job->id, 'division_id' => $division->id]);
        $value = fn ($section) => ProductionReference::where('section', $section)->where('is_active', true)->firstOrFail()->selectionValue();
        Livewire::test(CopperBreakPage::class)->call('edit', $entry->id)->set('supervisor_user_id', (string) $spv->id)
            ->set('event_at_break', $value('break-events'))->set('event_after_break', $value('break-conditions'))
            ->set('category', $value('break-categories'))->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('copper_break_entries', ['board_entry_id' => $entry->id, 'supervisor_user_id' => $spv->id]);
        foreach (['/', '/crystallizer', '/copper-break', '/output'] as $path) $this->get($path)->assertOk();
    }

    public function test_actual_report_uses_pickup_production_day_across_month_boundary_and_exports_same_rows(): void
    {
        $this->board(['lot' => 'OCT', 'upcasting_date' => '2026-10-29', 'actual_pickup_date' => '2026-11-01',
            'actual_pickup_time' => '07:59', 'event_status' => 'panen', 'weight_kg' => '2000', 'reject_weight_kg' => '100'])
            ->call('save')->assertHasNoErrors();
        $this->board(['lot' => 'NOV', 'upcasting_date' => '2026-10-29', 'actual_pickup_date' => '2026-11-01',
            'actual_pickup_time' => '08:00', 'event_status' => 'panen', 'weight_kg' => '1000', 'reject_weight_kg' => '50'])
            ->call('save')->assertHasNoErrors();
        $this->board(['lot' => 'RUNNING'])->call('save')->assertHasNoErrors();
        $october = app(StandaloneOutputService::class)->dailyActual('2026-10-01', '2026-10-31');
        $this->assertSame('1.900000000', $october['2026-10-31']['actual_mt']);
        $page = Livewire::test(OutputPage::class)->set('month', '2026-11')
            ->assertDontSee('Target MTD · PPIC')->assertDontSee('TARGET OUTPUT (MT)')->assertDontSee('Achievement (%)')
            ->assertViewHas('days', fn ($days) => (float) $days[0]['actual_mt'] === 0.95 && (float) $days[1]['actual_mtd_mt'] === 0.95)
            ->assertViewHas('reportRows', fn ($rows) => count($rows) === 8);
        $page->call('exportExcel')->assertDispatched('export-excel', function ($event, $params) {
            $payload = $params[0];
            $actual = collect($payload['rows'])->first(fn ($row) => $row[1] === 'AKTUAL OUTPUT (MT)');
            return count($payload['rows']) === 8 && $actual[2] === 0.95;
        });
        $page->call('exportPNG')->assertDispatched('export-png', fn ($event, $params) => count($params[0]['rows']) === 8);
    }

    public function test_standalone_pages_do_not_query_marketing_or_planning_and_missing_schedule_is_still_rejected_when_integrated(): void
    {
        DB::enableQueryLog();
        Livewire::test(BoardPage::class)->call('openBoardForm');
        Livewire::test(OutputPage::class)->call('exportExcel');
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/production_schedules|production_handoffs|marketing_sales_orders/i', $query);
        }
        config(['copper_rod.standalone' => false]);
        $this->board()->call('save')->assertHasErrors('production_schedule_id');
        $this->assertDatabaseCount('board_entries', 0);
    }

    public function test_trial_does_not_grant_production_access_to_other_roles(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Marketing]));
        Livewire::test(BoardPage::class)->assertForbidden();
        Livewire::test(OutputPage::class)->assertForbidden();
    }
}
