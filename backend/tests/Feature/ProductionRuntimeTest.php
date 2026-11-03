<?php

namespace Tests\Feature;

use App\Modules\Produksi\Livewire\BoardPage;
use App\Modules\Produksi\Livewire\OutputPage;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Models\ShiftEntry;
use App\Modules\Produksi\Services\OutputSummaryService;
use App\Modules\Produksi\Services\ProductionRuntimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionRuntimeTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\UsesBoardSchedule;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function freeze(string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time, ProductionRuntimeService::TIMEZONE));
    }

    private function entry(array $values = []): BoardEntry
    {
        return BoardEntry::create(array_merge([
            'production_schedule_id' => $this->boardSchedule()->id, 'weight_kg' => 0,
            'board_date' => '2026-10-28', 'shift' => 'S1', 'take_up' => '1XL',
            'cooling' => '1XR', 'lot' => 'LOT-TEST', 'crystallizer' => 'C01',
            'take_up_speed' => 1590.0, 'rod_diameter_mm' => 8,
            'estimated_pickup_date' => '2026-10-31', 'estimated_pickup_time' => '06:00',
            'upcasting_date' => '2026-10-28', 'upcasting_time' => '08:00',
        ], $values));
    }

    private function day(string $date): array
    {
        $summary = app(OutputSummaryService::class)->getSummary(substr($date, 0, 7).'-01', Carbon::parse($date)->endOfMonth()->format('Y-m-d'));
        return collect($summary['days'])->firstWhere('date', $date);
    }

    private function boardForm(string $takeUp = '1XL', string $cooling = '1XR')
    {
        \App\Modules\MasterData\Models\ProductionReference::firstOrCreate(['section' => 'crystallizers', 'code' => 'C01'], ['name' => 'C01', 'is_active' => true]);
        return Livewire::test(BoardPage::class)
            ->set('production_schedule_id', (string) $this->boardSchedule()->id)
            ->set('lot', 'LOT-TEST')->set('take_up', $takeUp)->set('cooling', $cooling)
            ->set('take_up_speed', '1590.0')->set('rod_diameter_mm', '8')
            ->set('crystallizer', 'C01')->set('estimated_pickup_date', '2026-10-31')->set('estimated_pickup_time', '06:00');
    }

    public function test_seventy_hour_lot_is_distributed_across_production_days_without_a_daily_cap(): void
    {
        $this->freeze('2026-11-02 12:00');
        $entry = $this->entry(['actual_pickup_date' => '2026-10-31', 'actual_pickup_time' => '06:00', 'event_status' => 'putus']);
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->call('save')->assertHasNoErrors();
        $this->assertEqualsWithDelta(70, $entry->operating_hours, 0.0001);
        foreach (['2026-10-28', '2026-10-29'] as $date) {
            foreach (['S1', 'S2', 'S3'] as $shift) {
                $this->assertEqualsWithDelta(8, $this->day($date)['shifts'][$shift]['total_hours'], 0.0001);
            }
        }
        $last = $this->day('2026-10-30');
        $this->assertEqualsWithDelta(6, $last['shifts']['S3']['total_hours'], 0.0001);
        $this->assertEqualsWithDelta(22 * 0.04293, $last['output_day_mt'], 0.000001);
        $this->assertSame(0.0, $this->day('2026-10-31')['output_day_mt']);
    }

    public function test_running_lot_counts_only_elapsed_time_and_pickup_stops_further_accrual(): void
    {
        $this->freeze('2026-10-29 02:00');
        $entry = $this->entry(['upcasting_time' => '22:00', 'shift' => 'S2']);
        $this->assertEqualsWithDelta(4, $entry->operating_hours, 0.0001);
        $day = $this->day('2026-10-28');
        $this->assertEqualsWithDelta(2, $day['shifts']['S2']['total_hours'], 0.0001);
        $this->assertEqualsWithDelta(2, $day['shifts']['S3']['total_hours'], 0.0001);
        $this->assertSame(0.0, $this->day('2026-10-29')['output_day_mt']);
        $entry->update(['actual_pickup_date' => '2026-10-29', 'actual_pickup_time' => '01:00', 'event_status' => 'putus']);
        $this->freeze('2026-10-30 18:00');
        $this->assertEqualsWithDelta(3, $entry->fresh()->operating_hours, 0.0001);
        $this->assertEqualsWithDelta(1, $this->day('2026-10-28')['shifts']['S3']['total_hours'], 0.0001);
    }

    public function test_month_boundary_includes_previous_month_lots_and_assigns_shift_three_to_previous_day(): void
    {
        $this->freeze('2026-11-02 12:00');
        $this->entry([
            'board_date' => '2026-10-31', 'upcasting_date' => '2026-10-31', 'upcasting_time' => '22:00',
            'actual_pickup_date' => '2026-11-01', 'actual_pickup_time' => '10:00',
        ]);
        $october = $this->day('2026-10-31')['shifts'];
        $november = $this->day('2026-11-01')['shifts'];
        $this->assertEqualsWithDelta(2, $october['S2']['total_hours'], 0.0001);
        $this->assertEqualsWithDelta(8, $october['S3']['total_hours'], 0.0001);
        $this->assertEqualsWithDelta(2, $november['S1']['total_hours'], 0.0001);
        $this->assertSame(0.0, $november['S3']['total_hours']);
    }

    public function test_shift_boundaries_and_invalid_or_incomplete_timestamps(): void
    {
        $this->freeze('2026-10-29 12:00');
        foreach (['00:00' => ['2026-10-27', 'S3'], '07:59' => ['2026-10-27', 'S3'], '08:00' => ['2026-10-28', 'S1'], '16:00' => ['2026-10-28', 'S2']] as $time => [$date, $shift]) {
            $this->assertSame(['date' => $date, 'shift' => $shift], ProductionRuntimeService::productionSlot(Carbon::parse('2026-10-28 '.$time, ProductionRuntimeService::TIMEZONE)));
        }
        $this->assertNull(ProductionRuntimeService::timestamp('2026-02-30', '08:00'));
        foreach ([['actual_pickup_date' => '2026-10-29'], ['actual_pickup_date' => '2026-10-27', 'actual_pickup_time' => '10:00'], ['event_status' => 'putus']] as $values) {
            $this->assertNull($this->entry($values)->operating_hours);
        }
    }

    public function test_board_save_derives_hours_and_shift_and_ignores_manual_hours(): void
    {
        $this->freeze('2026-10-29 12:00');
        $this->boardForm()
            ->set('take_up', '1XL')->set('upcasting_date', '2026-10-28')->set('upcasting_time', '23:00')
            ->set('actual_pickup_date', '2026-10-29')->set('actual_pickup_time', '02:00')
            ->set('event_status', 'putus')->set('weight_kg', '0')->set('operating_hours', '999')->call('save')->assertHasNoErrors();
        $entry = BoardEntry::sole();
        $this->assertEqualsWithDelta(3, $entry->operating_hours, 0.0001);
        $this->assertSame('2026-10-28', $entry->board_date);
        $this->assertSame('S2', $entry->shift);

        $this->boardForm('2YL', '2YR')
            ->set('upcasting_date', '2026-10-29')->set('upcasting_time', '01:00')->call('save')->assertHasNoErrors();
        $running = BoardEntry::orderByDesc('id')->first();
        $this->assertSame('2026-10-28', $running->board_date);
        $this->assertSame('S3', $running->shift);
        $this->assertNull($running->getRawOriginal('operating_hours'));
        $this->assertEqualsWithDelta(11, $running->operating_hours, 0.0001);
    }

    public function test_board_rejects_partial_end_and_reversed_times_when_pickup_is_provided(): void
    {
        $this->freeze('2026-10-29 12:00');
        $form = $this->boardForm()
            ->set('upcasting_date', '2026-10-28')->set('upcasting_time', '22:00');
        $form->set('event_status', 'putus');
        $form->set('actual_pickup_date', '2026-10-28')->call('save')->assertHasErrors('actual_pickup_time');
        $form->set('actual_pickup_time', '21:00')->call('save')->assertHasErrors('actual_pickup_time');
        $form->set('actual_pickup_date', '2026-10-30')->call('save')->assertHasErrors('actual_pickup_time');
        $this->assertDatabaseCount('board_entries', 0);
    }

    public function test_actual_pickup_can_be_empty_for_every_stop_status(): void
    {
        $this->freeze('2026-10-29 12:00');
        foreach (ProductionRuntimeService::END_STATUSES as $status) {
            $this->boardForm()->set('upcasting_date', '2026-10-28')->set('upcasting_time', '08:00')
                ->set('event_status', $status)->call('save')->assertHasNoErrors();
            $entry = BoardEntry::orderByDesc('id')->first();
            $this->assertSame($status, $entry->event_status);
            $this->assertSame('', $entry->actual_pickup_date);
            $this->assertSame('', $entry->actual_pickup_time);
        }
        $this->assertDatabaseCount('board_entries', count(ProductionRuntimeService::END_STATUSES));
    }

    public function test_legacy_manual_records_remain_supported_without_double_counting_linked_shifts(): void
    {
        $this->freeze('2026-10-29 12:00');
        $timed = $this->entry(['actual_pickup_date' => '2026-10-28', 'actual_pickup_time' => '10:00']);
        $manual = $this->entry(['upcasting_time' => '', 'operating_hours' => 3]);
        $legacy = $this->entry(['upcasting_date' => '', 'upcasting_time' => '']);
        foreach ([$timed->id, $manual->id, $legacy->id, null] as $id) {
            ShiftEntry::create(['board_entry_id' => $id, 'work_date' => '2026-10-28', 'shift' => 'S1', 'servo' => 'X', 'hours' => 1]);
        }
        $this->assertEqualsWithDelta(7, $this->day('2026-10-28')['shifts']['S1']['total_hours'], 0.0001);
    }

    public function test_current_report_uses_production_date_and_running_lot_remains_visible_across_months(): void
    {
        $this->freeze('2026-11-01 07:00');
        $this->entry(['board_date' => '2026-10-31', 'upcasting_date' => '2026-10-31']);
        Livewire::test(OutputPage::class)->assertSet('month', '2026-10')->assertViewHas('todayIso', '2026-10-31');
        $this->freeze('2026-11-01 08:00');
        Livewire::test(OutputPage::class)->assertSet('month', '2026-11')->assertViewHas('todayIso', '2026-11-01');
    }

    public function test_board_requires_operator_fields_but_allows_optional_fields_and_running_pickup_to_be_empty(): void
    {
        $this->freeze('2026-10-29 12:00');
        Livewire::test(BoardPage::class)->call('save')->assertHasErrors([
            'lot', 'take_up', 'cooling', 'crystallizer', 'upcasting_date', 'upcasting_time',
        ]);
        $this->assertDatabaseCount('board_entries', 0);
        $this->boardForm()->set('upcasting_date', '2026-10-28')->set('upcasting_time', '08:00')
            ->call('save')->assertHasNoErrors();
        $entry = BoardEntry::sole();
        $this->assertSame('X', $entry->servo);
        $this->assertSame('', $entry->event_status);
        $this->assertNull($entry->weight_kg);
        $this->assertSame('', $entry->remarks);
        $this->assertSame('', $entry->actual_pickup_date);
        $this->assertSame('', $entry->actual_pickup_time);
    }

    public function test_estimated_pickup_is_automatically_seventy_hours_after_upcasting_and_ignores_client_values(): void
    {
        $this->freeze('2027-01-05 12:00');
        foreach ([
            ['2026-10-28', '08:00', '2026-10-31', '06:00'],
            ['2026-10-30', '23:30', '2026-11-02', '21:30'],
            ['2026-12-30', '23:30', '2027-01-02', '21:30'],
        ] as [$date, $time, $estimatedDate, $estimatedTime]) {
            $this->boardForm()->set('upcasting_date', $date)->set('upcasting_time', $time)
                ->assertDontSeeHtml('id="board-estimated_pickup_date"')->assertDontSeeHtml('id="board-estimated_pickup_time"')
                ->assertSet('estimated_pickup_date', $estimatedDate)->assertSet('estimated_pickup_time', $estimatedTime)
                ->set('estimated_pickup_date', '1999-01-01')->set('estimated_pickup_time', '00:00')
                ->call('save')->assertHasNoErrors();
            $entry = BoardEntry::latest('id')->firstOrFail();
            $this->assertSame($estimatedDate, $entry->estimated_pickup_date);
            $this->assertSame($estimatedTime, $entry->estimated_pickup_time);
        }
        Livewire::test(BoardPage::class)->set('upcasting_date', '2026-10-28')->set('upcasting_time', '08:00')
            ->set('upcasting_time', '')->assertSet('estimated_pickup_date', '')->assertSet('estimated_pickup_time', '');
    }

    public function test_unreadable_or_conflicting_codes_cannot_save_and_edit_recalculates_servo(): void
    {
        $this->freeze('2026-10-29 12:00');
        $form = $this->boardForm()->set('upcasting_date', '2026-10-28')->set('upcasting_time', '08:00');
        $form->set('take_up', 'ABC')->call('save')->assertHasErrors('take_up');
        $form->set('take_up', '7XL')->call('save')->assertHasErrors('take_up');
        $form->set('take_up', '1XL')->set('cooling', 'ABC')->call('save')->assertHasErrors('cooling');
        $form->set('cooling', '7XR')->call('save')->assertHasErrors('cooling');
        $form->set('cooling', '2YR')->call('save')->assertHasErrors('cooling');
        $this->assertDatabaseCount('board_entries', 0);
        $form->set('cooling', '1XR')->call('save')->assertHasNoErrors();
        $entry = BoardEntry::sole();
        $this->assertSame('X', $entry->servo);
        Livewire::test(BoardPage::class)->call('edit', $entry->id)
            ->set('take_up', '2YL')->set('cooling', '2YR')->call('save')->assertHasNoErrors();
        $this->assertSame('Y', $entry->fresh()->servo);
        $this->assertDatabaseCount('board_entries', 1);
    }
}
