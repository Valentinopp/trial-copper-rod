<?php

namespace Tests\Feature;

use App\Modules\Produksi\Livewire\BoardPage;
use App\Modules\Produksi\Livewire\OutputPage;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Models\ShiftEntry;
use App\Modules\Produksi\Services\OutputFormulaService;
use App\Modules\Produksi\Services\OutputSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OutputFormulaTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\UsesBoardSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-03 12:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function entry(array $values = []): BoardEntry
    {
        return BoardEntry::create(array_merge([
            'production_schedule_id' => $this->boardSchedule((string) ($values['rod_diameter_mm'] ?? '8'))->id, 'weight_kg' => 0,
            'board_date' => '2026-10-28', 'shift' => 'S1', 'lot' => 'LOT-TEST',
            'take_up' => '1XL', 'cooling' => '1XR', 'crystallizer' => 'C01',
            'upcasting_date' => '2026-10-28', 'upcasting_time' => '08:00',
            'estimated_pickup_date' => '2026-10-28', 'estimated_pickup_time' => '10:00',
            'actual_pickup_date' => '2026-10-28', 'actual_pickup_time' => '10:00',
            'event_status' => 'panen',
            'take_up_speed' => 6000, 'rod_diameter_mm' => 8,
        ], $values));
    }

    private function day(string $date): array
    {
        $summary = app(OutputSummaryService::class)->getSummary(substr($date, 0, 7).'-01', Carbon::parse($date)->endOfMonth()->format('Y-m-d'));
        return collect($summary['days'])->firstWhere('date', $date);
    }

    public function test_rod_weights_and_millimeters_per_minute_are_converted_to_tons_per_hour(): void
    {
        foreach (['8' => 0.00045, '12.5' => 0.0011, '20' => 0.0028] as $diameter => $weight) {
            $this->assertEqualsWithDelta($weight, OutputFormulaService::rodWeight($diameter), 0.00000001);
        }
        // 60,000 mm/min = 60 m/min; 0.45 kg/m produces 1620 kg in one hour.
        $this->assertEqualsWithDelta(1.62, OutputFormulaService::coefficientForRod('8', 60000), 0.00000001);
        $this->assertEqualsWithDelta(0.396, OutputFormulaService::coefficientForRod('12.5', 6000), 0.00000001);
        $this->assertEqualsWithDelta(1.008, OutputFormulaService::coefficientForRod('20', 6000), 0.00000001);
        foreach ([null, '', 'abc', '0', '-1', INF, NAN] as $speed) {
            $this->assertNull(OutputFormulaService::coefficientForRod('8', $speed));
        }
        foreach ([null, '', '10', '12', 'abc'] as $diameter) {
            $this->assertNull(OutputFormulaService::coefficientForRod($diameter, 100));
        }
    }

    public function test_lot_estimate_uses_length_in_meters_and_returns_metric_tons(): void
    {
        $this->assertEqualsWithDelta(1.5, OutputFormulaService::speedMetersPerMinute('1500'), 0.000000001);
        $estimate = \App\Modules\Produksi\Services\LotEstimateService::calculate([
            'upcasting_date' => '2026-10-28', 'upcasting_time' => '08:00', 'take_up_speed' => '1500',
            'rod_diameter_mm' => '8', 'rod_weight_ton_per_meter' => '0.00045',
        ], Carbon::parse('2026-10-28 09:00', 'Asia/Jakarta'));
        $this->assertEqualsWithDelta(90, $estimate['length_m'], 0.000001);
        $this->assertEqualsWithDelta(0.0405, $estimate['current_mt'], 0.000000001);
        $this->assertEqualsWithDelta(40.5, $estimate['current_kg'], 0.000001);
        $this->assertEqualsWithDelta(0.0405, OutputFormulaService::coefficientForRod('8', '1500'), 0.000000001);
    }

    public function test_original_excel_formula_matches_lot_estimates_and_shift_recap(): void
    {
        // Excel: (1 * .00045 * 1.59 * 60 * 30.25) + (1 * .00045 * 1.59 * 60 * 35.5).
        // Input 1590 mm/min is 1.59 m/min. Each row represents one producing line/LOT.
        $first = $this->entry(['take_up_speed' => 1590, 'weight_kg' => null,
            'actual_pickup_date' => '2026-10-29', 'actual_pickup_time' => '14:15']);
        $second = $this->entry(['lot' => 'EXCEL-SECOND', 'take_up' => '2YL', 'cooling' => '2YR',
            'take_up_speed' => 1590, 'weight_kg' => null, 'actual_pickup_date' => '2026-10-29', 'actual_pickup_time' => '19:30']);
        $estimateA = \App\Modules\Produksi\Services\LotEstimateService::calculate($first->getAttributes());
        $estimateB = \App\Modules\Produksi\Services\LotEstimateService::calculate($second->getAttributes());
        $this->assertEqualsWithDelta(1.2986325, $estimateA['current_mt'], 0.000000001);
        $this->assertEqualsWithDelta(1.524015, $estimateB['current_mt'], 0.000000001);
        $this->assertEqualsWithDelta(2.8226475, $estimateA['current_mt'] + $estimateB['current_mt'], 0.000000001);
        $this->assertEqualsWithDelta(0.04293, OutputFormulaService::coefficientForRod('8', 1590), 0.000000001);
        $this->assertEqualsWithDelta(OutputFormulaService::coefficient(), OutputFormulaService::coefficientForRod('8', 1590), 0.000000001);
        $days = app(OutputSummaryService::class)->getSummary('2026-10-01', '2026-10-31')['days'];
        $this->assertEqualsWithDelta(2.8226475, array_sum(array_column($days, 'output_day_mt')), 0.000001);
    }

    public function test_1200_mm_per_minute_produces_2_point_268_metric_tons_in_seventy_hours(): void
    {
        $estimate = \App\Modules\Produksi\Services\LotEstimateService::calculate([
            'upcasting_date' => '2026-10-28', 'upcasting_time' => '08:00', 'take_up_speed' => 1200,
            'rod_diameter_mm' => 8, 'rod_weight_ton_per_meter' => 0.00045,
        ], Carbon::parse('2026-10-31 06:00', 'Asia/Jakarta'));
        $this->assertEqualsWithDelta(1.2, $estimate['speed_m_per_minute'], 0.000000001);
        $this->assertEqualsWithDelta(5040, $estimate['length_m'], 0.000001);
        $this->assertEqualsWithDelta(2.268, $estimate['current_mt'], 0.000000001);
        $this->assertEqualsWithDelta(2268, $estimate['current_kg'], 0.000001);
    }

    public function test_mixed_diameters_and_speeds_are_weighted_per_lot_in_the_same_shift(): void
    {
        $this->entry(); // 2 h x 8 mm x 6000 mm/min = 0.324 MT.
        $this->entry(['lot' => 'LOT-12.5', 'take_up' => '2YL', 'cooling' => '2YR', 'rod_diameter_mm' => 12.5, 'take_up_speed' => 3000]); // 0.396 MT.
        $this->entry(['lot' => 'LOT-20', 'rod_diameter_mm' => 20, 'take_up_speed' => 1500]); // 0.504 MT.
        $shift = $this->day('2026-10-28')['shifts']['S1'];
        $this->assertEqualsWithDelta(1.224, $shift['output_mt'], 0.0000001);
        $this->assertEqualsWithDelta(4, $shift['hours_x'], 0.0001);
        $this->assertEqualsWithDelta(2, $shift['hours_y'], 0.0001);
        Livewire::test(OutputPage::class)->set('month', '2026-10')->call('exportExcel')
            ->assertDispatched('export-excel', function ($event, $params) {
                $s1 = collect($params[0]['rows'])->first(fn ($row) => $row[0] === '1');
                return abs($s1[29] - 1.224) < 0.0000001;
            });
    }

    public function test_monthly_matrix_has_the_correct_days_and_can_navigate_across_years(): void
    {
        $page = Livewire::test(OutputPage::class)->set('month', '2026-08')
            ->assertSee('Agustus 2026')->assertSee('TARGET OUTPUT (MT)')->assertSee('AKTUAL MTD (MT)')
            ->assertViewHas('days', fn ($days) => count($days) === 31 && $days[30]['date'] === '2026-08-31')
            ->assertViewHas('reportRows', fn ($rows) => count($rows) === 12 && count($rows[0]['values']) === 31)
            ->call('moveMonth', 1)->assertSet('month', '2026-09')
            ->assertViewHas('days', fn ($days) => count($days) === 30)
            ->set('month', '2024-02')->assertViewHas('days', fn ($days) => count($days) === 29)
            ->set('month', '2025-02')->assertViewHas('days', fn ($days) => count($days) === 28)
            ->set('month', '2026-12')->call('moveMonth', 1)->assertSet('month', '2027-01')
            ->call('moveMonth', -1)->assertSet('month', '2026-12');
        foreach (['2026-13', '2026-00', 'invalid', ''] as $invalid) {
            $page->set('month', $invalid)->assertViewHas('days', [])->assertSee('Pilih bulan laporan yang valid');
        }
    }

    public function test_editing_one_lot_updates_only_its_own_output_and_inline_preview(): void
    {
        $first = $this->entry();
        $second = $this->entry(['lot' => 'LOT-12.5', 'rod_diameter_mm' => 12.5, 'take_up_speed' => 3000]);
        Livewire::test(BoardPage::class)->call('edit', $first->id)
            ->assertViewHas('inlineOutputMt', fn ($value) => abs($value - 0.324) < 0.0000001)
            ->set('take_up_speed', '12000')
            ->assertViewHas('inlineOutputMt', fn ($value) => abs($value - 0.648) < 0.0000001)
            ->call('save')->assertHasNoErrors();
        $this->assertEqualsWithDelta(1.044, $this->day('2026-10-28')['output_day_mt'], 0.0000001);
        $this->assertSame(12000.0, $first->fresh()->take_up_speed);
        $this->assertSame(3000.0, $second->fresh()->take_up_speed);
        $this->assertSame(12.5, $second->fresh()->rod_diameter_mm);
    }

    public function test_old_global_settings_do_not_change_lots_with_their_own_specifications(): void
    {
        DB::table('output_formula_settings')->insert(['id' => 1, 'factor_a' => 0.0009, 'factor_b' => 2]);
        $this->entry();
        $this->entry(['lot' => 'LEGACY', 'rod_diameter_mm' => null, 'take_up_speed' => null]);
        $this->assertEqualsWithDelta(0.54, $this->day('2026-10-28')['output_day_mt'], 0.0000001);
        DB::table('output_formula_settings')->where('id', 1)->update(['factor_a' => 0.0001, 'factor_b' => 1]);
        $this->assertEqualsWithDelta(0.336, $this->day('2026-10-28')['output_day_mt'], 0.0000001);
        // Only the legacy LOT changes; the specified LOT stays at 0.324 MT.
    }

    public function test_specified_lots_split_output_at_shift_and_production_month_boundaries(): void
    {
        $this->entry([
            'board_date' => '2026-10-31', 'shift' => 'S2',
            'upcasting_date' => '2026-10-31', 'upcasting_time' => '22:00',
            'actual_pickup_date' => '2026-11-01', 'actual_pickup_time' => '10:00',
            'rod_diameter_mm' => 20, 'take_up_speed' => 3000,
        ]);
        $october = $this->day('2026-10-31')['shifts'];
        $november = $this->day('2026-11-01')['shifts'];
        $this->assertEqualsWithDelta(1.008, $october['S2']['output_mt'], 0.0000001);
        $this->assertEqualsWithDelta(4.032, $october['S3']['output_mt'], 0.0000001);
        $this->assertEqualsWithDelta(1.008, $november['S1']['output_mt'], 0.0000001);
        $this->assertSame(0.0, $november['S3']['output_mt']);
    }

    public function test_legacy_shift_rows_use_the_source_board_specifications_and_standalone_rows_keep_fallback(): void
    {
        $source = $this->entry(['upcasting_date' => '', 'upcasting_time' => '', 'actual_pickup_date' => '', 'actual_pickup_time' => '']);
        ShiftEntry::create(['board_entry_id' => $source->id, 'work_date' => '2026-10-28', 'shift' => 'S1', 'servo' => 'X', 'hours' => 3]);
        ShiftEntry::create(['work_date' => '2026-10-28', 'shift' => 'S1', 'servo' => 'Y', 'hours' => 1]);
        $shift = $this->day('2026-10-28')['shifts']['S1'];
        $this->assertEqualsWithDelta(0.52893, $shift['output_mt'], 0.0000001);
        $this->assertEqualsWithDelta(4, $shift['total_hours'], 0.0001);
    }

    public function test_partial_specifications_do_not_silently_use_legacy_coefficient(): void
    {
        $this->entry(['take_up_speed' => null]);
        $this->entry(['rod_diameter_mm' => null]);
        $this->assertSame(0.0, $this->day('2026-10-28')['output_day_mt']);
    }
}
