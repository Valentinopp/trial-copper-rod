<?php

namespace Tests\Feature;

use App\Modules\Produksi\Enums\EventStatus;
use App\Modules\Produksi\Livewire\BoardPage;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Services\CopperBreakService;
use App\Modules\Produksi\Services\CrystallizerAnalyticsService;
use App\Modules\Produksi\Services\LotSequenceService;
use App\Modules\Produksi\Services\OutputSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LotSequenceTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\UsesBoardSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Modules\MasterData\Models\ProductionReference::create(['section' => 'crystallizers', 'code' => '12', 'name' => '12', 'is_active' => true]);
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function entry(array $values = []): BoardEntry
    {
        return BoardEntry::create(array_merge([
            'production_schedule_id' => $this->boardSchedule()->id, 'weight_kg' => 0,
            'board_date' => '2026-10-28', 'shift' => 'S1', 'lot' => '10',
            'take_up' => '1XL', 'cooling' => '1XR', 'crystallizer' => '12',
            'take_up_speed' => 6000, 'rod_diameter_mm' => 8,
            'upcasting_date' => '2026-10-28', 'upcasting_time' => '08:00',
            'estimated_pickup_date' => '2026-10-28', 'estimated_pickup_time' => '10:00',
            'actual_pickup_date' => '2026-10-28', 'actual_pickup_time' => '10:00', 'event_status' => 'panen',
        ], $values));
    }

    private function sequence(BoardEntry $entry): BoardEntry
    {
        return app(LotSequenceService::class)->decorate(collect([$entry->fresh()]))->first();
    }

    public function test_submitting_next_lot_shows_harvest_chain_while_preserving_individual_records(): void
    {
        $first = $this->entry();
        Livewire::test(BoardPage::class)->call('openBoardForm')
            ->set('production_schedule_id', (string) $first->production_schedule_id)
            ->set('lot', '11')->set('take_up', '1XL')->set('cooling', '1XR')->set('crystallizer', '12')
            ->set('take_up_speed', '6000')->set('rod_diameter_mm', '8')
            ->set('upcasting_date', '2026-10-28')->set('upcasting_time', '10:00')
            ->set('estimated_pickup_date', '2026-10-29')->set('estimated_pickup_time', '10:00')
            ->call('save')->assertHasNoErrors()->assertSee('10 ; 11')->assertSee('LOT ke-2');
        $next = BoardEntry::orderByDesc('id')->first();
        $this->assertSame('11', $next->lot);
        $this->assertSame('10', $first->fresh()->lot);
        $this->assertSame(2, $this->sequence($next)->lot_position);
        $this->assertDatabaseCount('board_entries', 2);
        // Filters must not hide the predecessor from the chain lookup.
        Livewire::test(BoardPage::class)->set('search', '11')->assertSee('10 ; 11');
    }

    public function test_only_exact_harvest_to_upcasting_match_on_same_crystallizer_can_link(): void
    {
        $first = $this->entry();
        foreach ([
            ['crystallizer' => '13', 'upcasting_time' => '10:00'],
            ['upcasting_time' => '10:01'],
            ['upcasting_date' => '2026-10-29', 'upcasting_time' => '10:00'],
            ['upcasting_time' => '09:00'], // Same pickup time does not establish a sequence.
        ] as $values) {
            $next = $this->entry(array_merge(['lot' => '11'], $values));
            $this->assertSame('11', $this->sequence($next)->lot_sequence);
            $next->delete();
        }
        $next = $this->entry(['lot' => '11', 'upcasting_time' => '10:00', 'actual_pickup_time' => '12:00']);
        foreach (['putus', '', 'tarik_lot_baru'] as $status) {
            $first->update(['event_status' => $status]);
            $this->assertSame('11', $this->sequence($next)->lot_sequence);
        }
        $first->update(['event_status' => 'panen_continue']);
        $this->assertSame('10 ; 11', $this->sequence($next)->lot_sequence);
    }

    public function test_multilot_chain_is_chronological_even_if_earlier_lot_is_entered_later_and_stops_at_putus(): void
    {
        $second = $this->entry(['lot' => '11', 'upcasting_time' => '10:00', 'actual_pickup_time' => '12:00']);
        $third = $this->entry(['lot' => '12', 'upcasting_time' => '12:00', 'actual_pickup_time' => '14:00', 'event_status' => 'putus']);
        $this->entry();
        $this->assertSame('10 ; 11 ; 12', $this->sequence($third)->lot_sequence);
        $this->assertSame(3, $this->sequence($third)->lot_position);
        $fourth = $this->entry(['lot' => '13', 'upcasting_time' => '14:00', 'actual_pickup_time' => '16:00']);
        $this->assertSame('13', $this->sequence($fourth)->lot_sequence);
        $this->assertSame(1, $this->sequence($fourth)->lot_position);
    }

    public function test_edits_deletions_and_ambiguous_predecessors_do_not_leave_incorrect_sequences(): void
    {
        $first = $this->entry();
        $next = $this->entry(['lot' => '11', 'upcasting_time' => '10:00', 'actual_pickup_time' => '12:00']);
        $this->assertSame('10 ; 11', $this->sequence($next)->lot_sequence);
        $duplicate = $this->entry(['lot' => 'OTHER']);
        $this->assertNull($this->sequence($next)->lot_position);
        $this->assertSame('11', $this->sequence($next)->lot_sequence);
        $duplicate->delete();
        $first->update(['lot' => '10A']);
        $this->assertSame('10A ; 11', $this->sequence($next)->lot_sequence);
        $first->update(['actual_pickup_time' => '10:01']);
        $this->assertSame('11', $this->sequence($next)->lot_sequence);
        $first->update(['actual_pickup_time' => '10:00']);
        $first->delete();
        $this->assertSame('11', $this->sequence($next)->lot_sequence);
    }

    public function test_sequence_is_consistent_in_exports_and_monitoring_without_double_counting_output(): void
    {
        $this->entry();
        $next = $this->entry(['lot' => '11', 'upcasting_time' => '10:00', 'actual_pickup_time' => '12:00', 'event_status' => 'putus']);
        foreach (['exportExcel' => 'export-excel', 'exportPNG' => 'export-png'] as $method => $event) {
            Livewire::test(BoardPage::class)->call($method)->assertDispatched($event, function ($name, $params) {
                foreach ($params[0]['rows'] as $row) {
                    if (in_array('10 ; 11', $row, true)) {
                        return count($row) === count($params[0]['headers']);
                    }
                }
                return false;
            });
        }
        $details = app(CrystallizerAnalyticsService::class)->getSummary()['items'];
        $this->assertSame('10 ; 11', collect($details)->firstWhere('id', $next->id)['lot']);
        $this->assertSame('10 ; 11', app(CopperBreakService::class)->listEntries()['items'][0]['lot']);
        $days = app(OutputSummaryService::class)->getSummary('2026-10-01', '2026-10-31')['days'];
        $day = collect($days)->firstWhere('date', '2026-10-28');
        $this->assertEqualsWithDelta(4, $day['shifts']['S1']['total_hours'], 0.0001);
        $this->assertEqualsWithDelta(0.648, $day['output_day_mt'], 0.0000001);
        $this->assertDatabaseCount('board_entries', 2);
    }

    public function test_pickup_requires_status_but_empty_pickup_does_not_and_only_two_statuses_are_available(): void
    {
        $entry = $this->entry(['event_status' => '']);
        $form = Livewire::test(BoardPage::class)->call('edit', $entry->id);
        $form->call('save')->assertHasErrors('event_status')->assertDispatched('notify');
        $form->set('event_status', 'panen')->call('save')->assertHasNoErrors();
        $this->assertSame('panen', $entry->fresh()->event_status);
        Livewire::test(BoardPage::class)->call('edit', $entry->id)
            ->set('actual_pickup_date', '')->set('actual_pickup_time', '')->set('event_status', '')
            ->set('weight_kg', '')->set('reject_weight_kg', '0')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('', $entry->fresh()->event_status);
        $this->assertSame(['panen', 'putus'], EventStatus::allowedValues());
        $this->assertSame('Panen', EventStatus::label('panen_stop'));
        $this->assertSame('Putus', EventStatus::label('putus_tarik_lot_baru'));
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->set('event_status', 'tarik_lot_baru')
            ->call('save')->assertHasErrors('event_status');
    }

    public function test_completing_pickup_only_warns_about_missing_status_after_submit(): void
    {
        $form = Livewire::test(BoardPage::class)->call('openBoardForm')
            ->set('actual_pickup_date', '2026-10-28')->assertNotDispatched('notify');
        $form->set('actual_pickup_time', '10:00')->assertHasNoErrors('event_status')->assertNotDispatched('notify');
        $form->set('actual_pickup_time', '10:01')->assertNotDispatched('notify');
        $form->set('event_status', 'panen')->assertHasNoErrors('event_status');
        $form->set('event_status', '')->assertHasNoErrors('event_status')->assertNotDispatched('notify');
        $form->call('save')->assertHasErrors('event_status')
            ->assertDispatched('notify', fn ($event, $params) => $params[0]['title'] === 'Lengkapi status kejadian');
        $this->assertDatabaseCount('board_entries', 0);
    }

    public function test_editing_existing_pickup_without_status_only_warns_on_submit(): void
    {
        $entry = $this->entry(['event_status' => '']);
        $form = Livewire::test(BoardPage::class)->call('edit', $entry->id)
            ->assertSet('showBoardForm', true)->assertHasNoErrors('event_status')->assertNotDispatched('notify');
        $form->call('save')->assertHasErrors('event_status')
            ->assertDispatched('notify', fn ($event, $params) => $params[0]['title'] === 'Lengkapi status kejadian');
        $this->assertSame('', $entry->fresh()->event_status);
        $form->set('event_status', 'panen')->call('save')->assertHasNoErrors();
        $this->assertSame('panen', $entry->fresh()->event_status);
        $this->assertDatabaseCount('board_entries', 1);
    }
}
