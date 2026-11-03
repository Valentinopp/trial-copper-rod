<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Models\ProductionReference;
use App\Modules\MasterData\Models\Unit;
use App\Modules\Ppic\Livewire\ProductionPlanPage;
use App\Modules\Ppic\Models\InventoryReceipt;
use App\Modules\Ppic\Models\MaterialReservation;
use App\Modules\Ppic\Models\ProductionSchedule;
use App\Modules\Ppic\Services\ProductionPlanningService;
use App\Modules\Ppic\Services\SalesReadinessService;
use App\Modules\Produksi\Livewire\BoardPage;
use App\Modules\Produksi\Livewire\OutputPage;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Purchasing\Services\ProcurementService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\UsesMasterSales;
use Tests\TestCase;

class ProductionPlanCopperRodIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use UsesMasterSales;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-29 14:00', 'Asia/Bangkok'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29 14:00', 'Asia/Bangkok'));
        ProductionReference::create(['section' => 'crystallizers', 'code' => 'CR-TEST', 'name' => 'Crystallizer Test', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function schedule(string $uom = 'KG', bool $withRod = true): ProductionSchedule
    {
        $material = $this->masterMaterial();
        $product = $this->masterProduct($material, $uom === 'TON' ? '1' : '1000', '1000', $uom);
        if ($withRod) {
            $product->update(['rod_specification_id' => ProductionReference::where('section', 'rod-specifications')->where('diameter_mm', 8)->firstOrFail()->id]);
        }
        $sale = $this->masterSale($product, $uom === 'TON' ? '1' : '1000');
        $ic = app(SalesReadinessService::class);
        $ic->prepare($sale->id);
        foreach ($sale->materialRequirements()->get() as $requirement) {
            $procurement = app(ProcurementService::class);
            $request = $procurement->submit(['product_id' => $requirement->material_id, 'quantity' => $requirement->quantity, 'required_on' => '2026-10-29']);
            $order = $procurement->approve($request->id, '2026-10-29', '2026-10-29');
            $procurement->receive($order->id, $requirement->quantity, '2026-10-29', (string) Str::uuid());
        }
        $handoff = $ic->handOver($sale->id);

        return app(ProductionPlanningService::class)->save([
            'handoff_id' => $handoff->id, 'submission_token' => (string) Str::uuid(), 'planned_on' => '2026-10-29',
            'quantity' => $uom === 'TON' ? '0.6' : '600',
        ])->load('handoff.sale');
    }

    private function board(ProductionSchedule $schedule, array $values = [])
    {
        $component = Livewire::test(BoardPage::class)->call('openBoardForm')
            ->set('production_schedule_id', (string) $schedule->id);
        foreach (array_replace([
            'lot' => 'LOT-'.Str::random(8), 'take_up' => '1XL', 'cooling' => '1XR', 'crystallizer' => 'CR-TEST',
            'take_up_speed' => '6000', 'upcasting_date' => '2026-10-29', 'upcasting_time' => '08:00',
            'estimated_pickup_date' => '2026-10-29', 'estimated_pickup_time' => '12:00',
            'event_status' => 'panen', 'actual_pickup_date' => '2026-10-29', 'actual_pickup_time' => '10:00',
            'weight_kg' => '250', 'reject_weight_kg' => '5',
        ], $values) as $key => $value) {
            $component->set($key, $value);
        }

        return $component;
    }

    private function progress(ProductionSchedule $schedule): array
    {
        return ProductionSchedule::withActuals()->findOrFail($schedule->id)->progress();
    }

    public function test_planning_derives_diameter_from_so_snapshot_and_ignores_client_override(): void
    {
        $schedule = $this->schedule();
        $sale = $schedule->handoff->sale;
        $this->assertEquals(8, $sale->product_snapshot['copper_rod']['diameter_mm']);
        $other = ProductionReference::where('section', 'rod-specifications')->where('diameter_mm', 20)->firstOrFail();
        $sale->product->update(['rod_specification_id' => $other->id]);
        ProductionReference::whereKey($schedule->rod_specification_id)->update(['weight_ton_per_meter' => 0.0009, 'is_active' => false]);
        $second = app(ProductionPlanningService::class)->save([
            'handoff_id' => $schedule->handoff_id, 'submission_token' => (string) Str::uuid(),
            'planned_on' => '2026-10-30', 'quantity' => '400', 'rod_specification_id' => $other->id,
        ]);
        $this->assertEquals(8, $second->rod_diameter_mm);
        $this->assertEqualsWithDelta(0.00045, $second->rod_weight_ton_per_meter, 0.000000001);
        $this->assertSame($schedule->rod_specification_id, $second->rod_specification_id);
        Livewire::test(ProductionPlanPage::class)->call('openEdit', $second->id)
            ->assertDontSeeHtml('wire:model="form.rod_specification_id"')->assertDontSee('Otomatis mengikuti spesifikasi produk pada SO.')
            ->set('form.rod_specification_id', (string) $other->id)->set('form.notes', 'Tetap sesuai SO')->call('save')->assertHasNoErrors();
        $this->assertEquals(8, $second->fresh()->rod_diameter_mm);
        $newSale = $this->masterSale($sale->product->fresh(), '100');
        $this->assertEquals(20, $newSale->product_snapshot['copper_rod']['diameter_mm']);
    }

    public function test_legacy_so_uses_explicit_diameter_before_product_mapping_without_parsing_names(): void
    {
        $schedule = $this->schedule();
        $sale = $schedule->handoff->sale;
        $snapshot = $sale->product_snapshot;
        unset($snapshot['copper_rod']);
        $snapshot['diameter_mm'] = '12.5';
        $sale->update(['product_snapshot' => $snapshot]);
        $service = app(ProductionPlanningService::class);
        $this->assertEquals(12.5, $service->rodForSale($sale)['rod_diameter_mm']);
        unset($snapshot['diameter_mm']);
        $sale->update(['product_snapshot' => $snapshot]);
        $this->assertEquals(8, $service->rodForSale($sale)['rod_diameter_mm']);
        $sale->product->update(['rod_specification_id' => null]);
        $sale->unsetRelation('product');
        $this->assertNull($service->rodForSale($sale)['rod_specification_id']);
    }

    private function sameProgressOnBothPages(ProductionSchedule $schedule, string $expected): void
    {
        Livewire::test(ProductionPlanPage::class)->assertViewHas('schedules', fn ($rows) => $rows->firstWhere('id', $schedule->id)->progress()['actual'] === $expected)
            ->assertViewHas('dailyTotals', fn ($totals) => $totals->first()['actual'] === $expected);
        Livewire::test(BoardPage::class)->assertViewHas('productionSchedules', fn ($rows) => $rows->firstWhere('id', $schedule->id)->progress()['actual'] === $expected)
            ->assertViewHas('productionProgressTotals', fn ($totals) => $totals->first()['actual'] === $expected);
    }

    public function test_multiple_lots_update_the_same_plan_and_actual_comparison_on_both_pages(): void
    {
        $schedule = $this->schedule();
        $stock = [InventoryReceipt::count(), InventoryReceipt::sum('quantity'), MaterialReservation::sum('quantity')];
        $this->board($schedule, ['weight_kg' => '400', 'reject_weight_kg' => '10'])->call('save')->assertHasNoErrors();
        $this->board($schedule, ['weight_kg' => '100', 'reject_weight_kg' => '5'])->call('save')->assertHasNoErrors();
        $progress = $this->progress($schedule);
        $this->assertSame('485.000000', $progress['actual']);
        $this->assertSame('-115.000000', $progress['difference']);
        $this->assertSame('80.8', $progress['percentage']);
        $this->assertSame(2, $progress['lots']);
        $this->sameProgressOnBothPages($schedule, '485.000000');
        $this->assertSame($stock, [InventoryReceipt::count(), InventoryReceipt::sum('quantity'), MaterialReservation::sum('quantity')]);
    }

    public function test_backlog_shows_order_actual_across_all_schedules_and_keeps_remaining_to_schedule_separate(): void
    {
        $schedule = $this->schedule();
        $this->board($schedule, ['weight_kg' => '400', 'reject_weight_kg' => '10'])->call('save')->assertHasNoErrors();
        $entry = BoardEntry::firstOrFail();
        Livewire::test(ProductionPlanPage::class)->assertSee('Sisa dijadwalkan')->assertSee('Sisa Aktual')
            ->assertViewHas('handoffs', function ($rows) use ($schedule) {
                $row = $rows->firstWhere('id', $schedule->handoff_id);
                return $row->actual_quantity === '390.000000' && $row->remaining_quantity === '400.000000'
                    && $row->remaining_actual_quantity === '-610.000000';
            });
        $second = app(ProductionPlanningService::class)->save([
            'handoff_id' => $schedule->handoff_id, 'submission_token' => (string) Str::uuid(),
            'planned_on' => '2026-10-30', 'quantity' => '400',
        ]);
        $this->board($second, ['weight_kg' => '300', 'reject_weight_kg' => '20'])->call('save')->assertHasNoErrors();
        $this->board($schedule, ['actual_pickup_date' => '', 'actual_pickup_time' => '', 'event_status' => '',
            'weight_kg' => '', 'reject_weight_kg' => '0'])->call('save')->assertHasNoErrors();
        BoardEntry::create(['lot' => 'UNLINKED', 'board_date' => '2026-10-29', 'weight_kg' => 9999]);
        Livewire::test(ProductionPlanPage::class)->set('backlogFilter', 'all')->set('selectedDate', '2026-11-05')
            ->assertViewHas('handoffs', function ($rows) use ($schedule) {
                $row = $rows->firstWhere('id', $schedule->handoff_id);
                return $row->relationLoaded('activeSchedules') && $row->actual_quantity === '670.000000'
                    && $row->remaining_quantity === '0.000000' && $row->remaining_actual_quantity === '-330.000000';
            });
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->set('weight_kg', '500')->call('save')->assertHasNoErrors();
        Livewire::test(ProductionPlanPage::class)->set('backlogFilter', 'all')
            ->assertViewHas('handoffs', fn ($rows) => $rows->firstWhere('id', $schedule->handoff_id)->actual_quantity === '770.000000'
                && $rows->firstWhere('id', $schedule->handoff_id)->remaining_actual_quantity === '-230.000000');
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->set('weight_kg', '730')->call('save')->assertHasNoErrors();
        Livewire::test(ProductionPlanPage::class)->set('backlogFilter', 'all')
            ->assertViewHas('handoffs', fn ($rows) => $rows->firstWhere('id', $schedule->handoff_id)->remaining_actual_quantity === '0.000000');
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->set('weight_kg', '830')->call('save')->assertHasNoErrors();
        Livewire::test(ProductionPlanPage::class)->set('backlogFilter', 'all')->assertSee('+100')
            ->assertViewHas('handoffs', fn ($rows) => $rows->firstWhere('id', $schedule->handoff_id)->remaining_actual_quantity === '100.000000');
    }

    public function test_backlog_actual_uses_schedule_unit_snapshot_and_handles_missing_conversion_and_cancelled_plans(): void
    {
        $kg = Unit::where('code', 'KG')->firstOrFail();
        $ton = Unit::create(['code' => 'TON', 'name' => 'Ton', 'related_uom_id' => $kg->id, 'factor' => 1000, 'is_active' => true]);
        $schedule = $this->schedule('TON');
        $this->board($schedule, ['weight_kg' => '200', 'reject_weight_kg' => '20'])->call('save')->assertHasNoErrors();
        $ton->update(['factor' => 500]);
        Livewire::test(ProductionPlanPage::class)
            ->assertViewHas('handoffs', fn ($rows) => $rows->firstWhere('id', $schedule->handoff_id)->actual_quantity === '0.180000');
        $unmapped = $this->schedule('PCS');
        Livewire::test(ProductionPlanPage::class)->assertSee('Konversi KG belum tersedia')
            ->assertViewHas('handoffs', fn ($rows) => $rows->firstWhere('id', $unmapped->handoff_id)->actual_quantity === null
                && $rows->firstWhere('id', $unmapped->handoff_id)->remaining_actual_quantity === null);
        app(ProductionPlanningService::class)->cancel($unmapped->id, $unmapped->revision);
        Livewire::test(ProductionPlanPage::class)
            ->assertViewHas('handoffs', fn ($rows) => $rows->firstWhere('id', $unmapped->handoff_id)->actual_quantity === '0.000000');
    }

    public function test_running_lots_and_estimated_output_do_not_count_as_weighed_actual(): void
    {
        $schedule = $this->schedule();
        $this->board($schedule, ['actual_pickup_date' => '', 'actual_pickup_time' => '', 'event_status' => '', 'weight_kg' => '', 'reject_weight_kg' => '0'])
            ->call('save')->assertHasNoErrors();
        $entry = BoardEntry::where('production_schedule_id', $schedule->id)->firstOrFail();
        $this->assertNull($entry->production_actual_kg);
        $this->assertSame('0.000000', $this->progress($schedule)['actual']);
        $this->assertSame('Sedang produksi', $this->progress($schedule)['status']);
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->set('event_status', 'panen')
            ->set('actual_pickup_date', '2026-10-29')->set('actual_pickup_time', '10:00')
            ->set('weight_kg', '100')->set('reject_weight_kg', '10')->call('save')->assertHasNoErrors();
        $this->sameProgressOnBothPages($schedule, '90.000000');
        $this->assertSame(0, $this->progress($schedule)['running']);
    }

    public function test_running_board_recovers_automatic_diameter_and_displays_metric_ton_estimate_before_submit(): void
    {
        $schedule = $this->schedule();
        $form = $this->board($schedule, ['take_up_speed' => '1500', 'rod_diameter_mm' => '',
            'actual_pickup_date' => '', 'actual_pickup_time' => '', 'event_status' => '',
            'weight_kg' => '', 'reject_weight_kg' => '0']);
        $form->assertSet('rod_diameter_mm', '8')->assertHasNoErrors()
            ->assertSeeHtml('novalidate')->assertDontSeeHtml('id="board-rod_diameter_mm"')
            ->assertSee('0,24 MT')->assertViewHas('lotEstimate', fn ($estimate) => abs($estimate['current_mt'] - 0.243) < 0.00000001
                && abs($estimate['speed_m_per_minute'] - 1.5) < 0.00000001 && abs($estimate['length_m'] - 540) < 0.000001)
            ->set('event_status', 'panen')->assertSee('0,24 MT')
            ->call('save')->assertHasNoErrors();
        $entry = BoardEntry::firstOrFail();
        $this->assertEquals(8, $entry->rod_diameter_mm);
        $this->assertNull($entry->production_actual_kg);
        $this->assertSame('2026-11-01', $entry->estimated_pickup_date);
    }

    public function test_missing_input_shows_estimate_placeholder_and_named_validation_errors_on_submit(): void
    {
        Livewire::test(BoardPage::class)->call('openBoardForm')->assertHasNoErrors()
            ->assertSeeHtml('id="board-lot-estimate"')->assertSee('— MT')
            ->assertNotDispatched('notify')->call('save')->assertHasErrors(['lot', 'take_up', 'upcasting_time'])
            ->assertDispatched('notify', function ($event, $params) {
                return in_array('LOT wajib diisi.', $params[0]['messages'], true)
                    && in_array('Jam upcasting wajib diisi.', $params[0]['messages'], true)
                    && !in_array('Ada kolom wajib yang belum diisi. Silakan lengkapi sebelum menyimpan.', $params[0]['messages'], true);
            });
    }

    public function test_form_estimate_updates_after_pickup_speed_and_status_changes_even_with_actual_weight(): void
    {
        $schedule = $this->schedule();
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00', 'Asia/Jakarta'));
        $form = $this->board($schedule, ['take_up_speed' => '1200', 'upcasting_date' => '2026-10-28',
            'upcasting_time' => '08:00', 'actual_pickup_date' => '2026-10-30', 'actual_pickup_time' => '08:00',
            'weight_kg' => '2000', 'reject_weight_kg' => '0', 'event_status' => 'panen']);
        $form->assertSee('1,56 MT')->assertViewHas('lotEstimate', fn ($estimate) => abs($estimate['current_mt'] - 1.5552) < 0.00000001)
            ->set('weight_kg', '2100')->set('reject_weight_kg', '100')
            ->assertViewHas('lotEstimate', fn ($estimate) => abs($estimate['current_mt'] - 1.5552) < 0.00000001)
            ->call('save')->assertHasNoErrors();
        $entry = BoardEntry::firstOrFail();
        $this->assertSame('2000.000000', $entry->production_actual_kg);
        $this->assertSame('0.000000', $this->progress($schedule)['estimated']);
        $form->call('edit', $entry->id)->assertSee('1,56 MT')
            ->set('actual_pickup_date', '2026-10-29')
            ->assertViewHas('lotEstimate', fn ($estimate) => abs($estimate['current_mt'] - 0.7776) < 0.00000001)
            ->set('actual_pickup_time', '10:00')
            ->assertViewHas('lotEstimate', fn ($estimate) => abs($estimate['current_mt'] - 0.8424) < 0.00000001)
            ->set('take_up_speed', '1590')
            ->assertViewHas('lotEstimate', fn ($estimate) => abs($estimate['current_mt'] - 1.11618) < 0.00000001)
            ->set('upcasting_time', '09:00')
            ->assertViewHas('lotEstimate', fn ($estimate) => abs($estimate['current_mt'] - 1.07325) < 0.00000001)
            ->set('event_status', 'putus')->assertViewHas('lotEstimate', fn ($estimate) => $estimate['current_mt'] === 0.0)
            ->set('event_status', 'panen')
            ->assertViewHas('lotEstimate', fn ($estimate) => abs($estimate['current_mt'] - 1.07325) < 0.00000001)
            ->set('actual_pickup_time', '')->assertViewHas('lotEstimate', fn ($estimate) => $estimate['current_mt'] === null)
            ->set('actual_pickup_time', '10:00')
            ->assertViewHas('lotEstimate', fn ($estimate) => abs($estimate['current_mt'] - 1.07325) < 0.00000001)
            ->set('actual_pickup_date', '')->set('actual_pickup_time', '')
            ->assertViewHas('lotEstimate', fn ($estimate) => abs($estimate['current_mt'] - 2.18943) < 0.00000001);
        // Editing the preview must not overwrite saved actual until the operator submits.
        $this->assertSame('2000.000000', $entry->fresh()->production_actual_kg);
    }

    public function test_unweighed_lot_estimate_is_visible_on_both_pages_until_harvest_or_break(): void
    {
        $schedule = $this->schedule();
        $this->board($schedule, ['take_up_speed' => '1500', 'actual_pickup_date' => '', 'actual_pickup_time' => '',
            'event_status' => '', 'weight_kg' => '', 'reject_weight_kg' => '0'])->call('save')->assertHasNoErrors();
        $entry = BoardEntry::firstOrFail();
        $this->assertSame('243.000000', $this->progress($schedule)['estimated']);
        $this->assertSame('2835.000000', $this->progress($schedule)['estimated_completion']);
        $this->assertSame('0.000000', $this->progress($schedule)['actual']);
        Livewire::test(BoardPage::class)->assertSee('Estimasi berjalan')
            ->assertViewHas('productionProgressTotals', fn ($rows) => $rows->first()['estimated'] === '243.000000');
        Livewire::test(ProductionPlanPage::class)->assertSee('Estimasi berjalan')
            ->assertViewHas('dailyTotals', fn ($rows) => $rows->first()['estimated'] === '243.000000');

        // Master edits must not alter the LOT's captured production rate.
        ProductionReference::whereKey($schedule->rod_specification_id)->update(['weight_ton_per_meter' => 0.0009]);
        Carbon::setTestNow(Carbon::parse('2026-11-02 14:00', 'Asia/Bangkok'));
        $this->assertSame('2835.000000', $this->progress($schedule)['estimated']);
        Livewire::test(BoardPage::class)->call('edit', $entry->id)
            ->set('event_status', 'panen')->set('actual_pickup_date', '2026-10-31')->set('actual_pickup_time', '06:00')
            ->set('weight_kg', '2800')->set('reject_weight_kg', '10')->call('save')->assertHasNoErrors();
        $this->assertSame('0.000000', $this->progress($schedule)['estimated']);
        $this->assertSame('0.000000', $this->progress($schedule)['estimated_completion']);
        $this->assertSame('2790.000000', $this->progress($schedule)['actual']);

        $this->board($schedule, ['lot' => 'BROKEN', 'take_up_speed' => '1500', 'actual_pickup_date' => '', 'actual_pickup_time' => '',
            'event_status' => '', 'weight_kg' => '', 'reject_weight_kg' => '0'])->call('save')->assertHasNoErrors();
        $broken = BoardEntry::where('lot', 'BROKEN')->firstOrFail();
        $this->assertSame('2835.000000', $this->progress($schedule)['estimated']);
        Livewire::test(BoardPage::class)->call('edit', $broken->id)->set('event_status', 'putus')
            ->assertViewHas('lotEstimate', fn ($estimate) => $estimate['current_kg'] === 0.0 && $estimate['completion_kg'] === 0.0)
            ->set('actual_pickup_date', '2026-10-29')->set('actual_pickup_time', '10:00')
            ->set('weight_kg', '50')->set('reject_weight_kg', '50')->call('save')->assertHasNoErrors();
        $this->assertSame('0.000000', $this->progress($schedule)['estimated']);
        $this->assertSame('2790.000000', $this->progress($schedule)['actual']);
    }

    public function test_production_calendar_shows_future_targets_and_navigates_without_editing_plans(): void
    {
        $schedule = $this->schedule();
        $schedule->update(['planned_on' => '2026-11-02']);
        $cancelled = $this->schedule();
        $cancelled->update(['planned_on' => '2026-11-02', 'status' => 'cancelled']);
        $this->actingAs(User::factory()->create(['role' => UserRole::OperatorProduksi]));
        Livewire::test(BoardPage::class)->assertSee('TARGET PRODUKSI')
            ->call('movePlanWeek', 1)->assertSet('planDate', '2026-11-05')
            ->assertViewHas('planWeek', fn ($days) => $days->firstWhere('date', '2026-11-02')['count'] === 1
                && $days->firstWhere('date', '2026-11-02')['targets']->first()['quantity'] === '600.000000')
            ->call('selectPlanDate', '2026-11-02')->assertSee($schedule->reference)
            ->assertViewHas('productionSchedules', fn ($rows) => $rows->count() === 1 && $rows->first()->id === $schedule->id)
            ->call('movePlanWeek', -1)->assertSet('planDate', '2026-10-26')
            ->call('planToday')->assertSet('planDate', '2026-10-29')
            ->call('selectPlanDate', '2026-02-30')->assertHasErrors('planDate');
        $this->assertSame('600.000000', $schedule->fresh()->quantity);
        $this->assertSame('2026-11-02', $schedule->fresh()->planned_on->format('Y-m-d'));
        $this->get('/ppic/production-plan')->assertForbidden();
    }

    public function test_estimates_convert_using_the_plan_unit_without_guessing_a_lot_weight(): void
    {
        $kg = Unit::where('code', 'KG')->firstOrFail();
        Unit::create(['code' => 'TON', 'name' => 'Ton', 'related_uom_id' => $kg->id, 'factor' => 1000, 'is_active' => true]);
        $schedule = $this->schedule('TON');
        $this->board($schedule, ['take_up_speed' => '1500', 'actual_pickup_date' => '', 'actual_pickup_time' => '',
            'event_status' => '', 'weight_kg' => '', 'reject_weight_kg' => '0'])->call('save')->assertHasNoErrors();
        $this->assertSame('0.243000', $this->progress($schedule)['estimated']);
        $this->assertSame('2.835000', $this->progress($schedule)['estimated_completion']);
        $entry = BoardEntry::firstOrFail();
        $entry->update(['take_up_speed' => null]);
        $this->assertNull($this->progress($schedule)['estimated']);
        $entry->update(['event_status' => 'putus']);
        $this->assertSame('0.000000', $this->progress($schedule)['estimated']);
    }

    public function test_output_rows_compare_plans_with_weighed_actual_on_the_original_schedule_date(): void
    {
        $schedule = $this->schedule();
        $second = $this->schedule();
        $cancelled = $this->schedule();
        app(ProductionPlanningService::class)->cancel($cancelled->id, 1);
        $this->board($schedule, ['weight_kg' => '400', 'reject_weight_kg' => '10'])->call('save')->assertHasNoErrors();
        $this->board($second, ['weight_kg' => '', 'reject_weight_kg' => '0',
            'actual_pickup_date' => '', 'actual_pickup_time' => '', 'event_status' => ''])->call('save')->assertHasNoErrors();
        BoardEntry::create(['lot' => 'UNLINKED', 'board_date' => '2026-10-29', 'weight_kg' => 9999]);

        $page = Livewire::test(OutputPage::class)->set('month', '2026-10')->set('target', '999');
        $page->assertSee('Plan PPIC')->assertSee('Aktual Timbang')->assertSee('Selisih Aktual − Plan')
            ->assertViewHas('days', function ($days) {
                $row = collect($days)->firstWhere('date', '2026-10-29');
                $empty = collect($days)->firstWhere('date', '2026-10-28');
                return $row['plan_count'] === 2 && $row['plan_mt'] === '1.200000000'
                    && $row['actual_mt'] === '0.390000000' && $row['difference_mt'] === '-0.810000000'
                    && $row['plan_achievement_pct'] === '32.50' && $empty['plan_mt'] === null;
            });
        $page->call('exportExcel')->assertDispatched('export-excel', function ($event, $params) {
            $payload = $params[0];
            $rows = collect($payload['rows'])->keyBy(fn ($row) => $row[1]);
            return $payload['format'] === 'monthly-output' && $payload['monthLabel'] === 'Oktober 2026'
                && count($payload['rowKinds']) === count($payload['rows']) && $payload['todayColumn'] === 28
                && count($rows['TARGET OUTPUT (MT)']) === count($payload['headers'])
                && $rows['TARGET OUTPUT (MT)'][30] === 1.2 && $rows['AKTUAL OUTPUT (MT)'][30] === 0.39
                && $rows['Selisih Aktual − Plan (MT)'][30] === -0.81 && $rows['Achievement (%)'][30] === 32.5;
        });
        $page->call('exportPNG')->assertDispatched('export-png', function ($event, $params) {
            $payload = $params[0];
            $row = collect($payload['rows'])->first(fn ($row) => $row[1] === 'Achievement (%)');
            return count($row) === count($payload['headers']) && $row[30] === '32,50%';
        });

        Carbon::setTestNow(Carbon::parse('2026-11-02 14:00', 'Asia/Bangkok'));
        $running = BoardEntry::where('production_schedule_id', $second->id)->firstOrFail();
        Livewire::test(BoardPage::class)->call('edit', $running->id)->set('event_status', 'panen')
            ->set('actual_pickup_date', '2026-11-01')->set('actual_pickup_time', '06:00')
            ->set('weight_kg', '1000')->set('reject_weight_kg', '0')->call('save')->assertHasNoErrors();
        Livewire::test(OutputPage::class)->set('month', '2026-10')->assertViewHas('days', function ($days) {
            $row = collect($days)->firstWhere('date', '2026-10-29');
            return $row['actual_mt'] === '1.390000000' && $row['difference_mt'] === '0.190000000'
                && $row['plan_achievement_pct'] === '115.83' && $row['actual_mtd_mt'] === '1.390000000'
                && $row['plan_mtd_mt'] === '1.200000000' && $row['plan_mtd_achievement_pct'] === '115.83';
        });
        Livewire::test(OutputPage::class)->set('month', '2026-11')
            ->assertViewHas('days', fn ($days) => collect($days)->firstWhere('date', '2026-11-01')['actual_mt'] === null
                && collect($days)->firstWhere('date', '2026-11-01')['actual_mtd_mt'] === '0');
    }

    public function test_output_comparison_converts_plan_units_and_flags_missing_conversion_instead_of_partial_totals(): void
    {
        $kg = Unit::where('code', 'KG')->firstOrFail();
        $ton = Unit::create(['code' => 'TON', 'name' => 'Ton', 'related_uom_id' => $kg->id, 'factor' => 1000, 'is_active' => true]);
        $schedule = $this->schedule('TON');
        $this->board($schedule, ['weight_kg' => '200', 'reject_weight_kg' => '20'])->call('save')->assertHasNoErrors();
        $ton->update(['factor' => 500]);
        Livewire::test(OutputPage::class)->set('month', '2026-10')->assertViewHas('days', function ($days) {
            $row = collect($days)->firstWhere('date', '2026-10-29');
            return $row['plan_mt'] === '0.600000000' && $row['actual_mt'] === '0.180000000'
                && $row['difference_mt'] === '-0.420000000' && $row['plan_achievement_pct'] === '30.00';
        });
        $this->schedule('PCS');
        Livewire::test(OutputPage::class)->set('month', '2026-10')->assertSee('Konversi KG belum lengkap')
            ->assertViewHas('days', function ($days) {
                $row = collect($days)->firstWhere('date', '2026-10-29');
                return $row['unconverted_plans'] === 1 && $row['plan_mt'] === null
                    && $row['difference_mt'] === null && $row['plan_achievement_pct'] === null
                    && $row['actual_mt'] === '0.180000000';
            });
    }

    public function test_weight_corrections_and_deletion_recalculate_actual_without_double_counting(): void
    {
        $schedule = $this->schedule();
        $this->board($schedule)->call('save')->assertHasNoErrors();
        $entry = BoardEntry::firstOrFail();
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->set('weight_kg', '300')->set('reject_weight_kg', '20')->call('save')->assertHasNoErrors();
        $this->sameProgressOnBothPages($schedule, '280.000000');
        $this->assertDatabaseCount('board_entries', 1);
        Livewire::test(BoardPage::class)->call('remove', $entry->id);
        $this->sameProgressOnBothPages($schedule, '0.000000');
        $this->assertSame('Belum mulai', $this->progress($schedule)['status']);
    }

    public function test_putus_records_count_only_usable_weight_and_all_reject_can_be_zero_actual(): void
    {
        $schedule = $this->schedule();
        $this->board($schedule, ['event_status' => 'putus', 'weight_kg' => '100', 'reject_weight_kg' => '30'])->call('save')->assertHasNoErrors();
        $this->assertSame('70.000000', $this->progress($schedule)['actual']);
        $this->board($schedule, ['event_status' => 'putus', 'weight_kg' => '100', 'reject_weight_kg' => '100'])->call('save')->assertHasNoErrors();
        $this->assertSame('70.000000', $this->progress($schedule)['actual']);
        $this->assertSame(0, $this->progress($schedule)['running']);
    }

    public function test_overproduction_is_recorded_truthfully_and_target_cannot_be_reduced_below_actual(): void
    {
        $schedule = $this->schedule();
        $this->board($schedule, ['weight_kg' => '700', 'reject_weight_kg' => '0'])->call('save')->assertHasNoErrors();
        $this->assertSame('100.000000', $this->progress($schedule)['difference']);
        $this->assertSame('Target tercapai', $this->progress($schedule)['status']);
        $this->sameProgressOnBothPages($schedule, '700.000000');
        Livewire::test(ProductionPlanPage::class)->call('openEdit', $schedule->id)->set('form.quantity', '650')->call('save')->assertHasErrors('form.quantity');
        Livewire::test(ProductionPlanPage::class)->call('openEdit', $schedule->id)->set('form.notes', 'Catatan baru')->call('save')->assertHasNoErrors();
    }

    public function test_finished_linked_lot_requires_weight_and_reject_cannot_exceed_weight(): void
    {
        $schedule = $this->schedule();
        $this->board($schedule, ['weight_kg' => ''])->call('save')->assertHasErrors('weight_kg');
        $this->board($schedule, ['weight_kg' => '100', 'reject_weight_kg' => '101'])->call('save')->assertHasErrors('reject_weight_kg');
        $this->assertDatabaseCount('board_entries', 0);
    }

    public function test_weight_fields_are_editable_and_missing_pickup_warns_only_on_submit(): void
    {
        $schedule = $this->schedule();
        $form = $this->board($schedule, ['actual_pickup_date' => '', 'actual_pickup_time' => '',
            'event_status' => '', 'weight_kg' => '', 'reject_weight_kg' => '0']);
        $document = new \DOMDocument;
        @$document->loadHTML($form->html());
        $this->assertFalse($document->getElementById('board-weight_kg')->hasAttribute('readonly'));
        $this->assertFalse($document->getElementById('board-reject_weight_kg')->hasAttribute('readonly'));
        $form->assertDontSee('Kosongkan berat dan reject')->assertDontSeeHtml('guardWeight(')
            ->set('weight_kg', '100')->assertNotDispatched('notify')
            ->set('reject_weight_kg', '10')->assertNotDispatched('notify')->call('save')
            ->assertHasErrors('weight_kg')->assertSet('weight_kg', '100')->assertSet('reject_weight_kg', '10')
            ->assertDispatched('notify', fn ($event, $params) => $params[0]['type'] === 'warning'
                && str_contains($params[0]['message'], 'Anda belum mengisi tanggal dan jam Aktual Pick Up'));
        $this->assertDatabaseCount('board_entries', 0);
        $form->set('actual_pickup_date', '2026-10-29')->assertNotDispatched('notify')->call('save')
            ->assertDispatched('notify', fn ($event, $params) => $params[0]['type'] === 'warning');
        $this->assertDatabaseCount('board_entries', 0);
        $form->set('actual_pickup_date', '')->set('actual_pickup_time', '10:00')->call('save')
            ->assertDispatched('notify', fn ($event, $params) => $params[0]['type'] === 'warning');
        $form->set('actual_pickup_date', '2026-10-29')->set('event_status', 'panen')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('90.000000', BoardEntry::firstOrFail()->production_actual_kg);
    }
    public function test_weight_and_reject_cannot_be_saved_without_actual_pickup_even_if_client_is_bypassed(): void
    {
        $schedule = $this->schedule();
        foreach ([['100', '0', 'weight_kg'], ['0', '0', 'weight_kg'], ['', '5', 'reject_weight_kg']] as [$weight, $reject, $error]) {
            $this->board($schedule, ['actual_pickup_date' => '', 'actual_pickup_time' => '', 'event_status' => '',
                'weight_kg' => $weight, 'reject_weight_kg' => $reject])->call('save')->assertHasErrors($error)
                ->assertDispatched('notify', fn ($event, $params) => str_contains($params[0]['message'], 'Anda belum mengisi tanggal dan jam Aktual Pick Up'));
        }
        $this->assertDatabaseCount('board_entries', 0);
        $this->board($schedule, ['actual_pickup_date' => '', 'actual_pickup_time' => '', 'event_status' => '',
            'weight_kg' => '', 'reject_weight_kg' => '0'])->call('save')->assertHasNoErrors();
        $this->assertNull(BoardEntry::firstOrFail()->weight_kg);
        $this->assertNull(BoardEntry::firstOrFail()->production_actual_kg);
    }

    public function test_diameter_and_schedule_links_are_validated_on_the_server(): void
    {
        $schedule = $this->schedule();
        $this->board($schedule, ['rod_diameter_mm' => '20'])->call('save')->assertHasErrors('rod_diameter_mm');
        $this->board($schedule)->call('save')->assertHasNoErrors();
        $entry = BoardEntry::firstOrFail();
        $other = $this->schedule();
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->set('production_schedule_id', (string) $other->id)
            ->call('save')->assertHasErrors('production_schedule_id');
        $this->assertSame($schedule->id, $entry->fresh()->production_schedule_id);
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->set('production_schedule_id', '')
            ->call('save')->assertHasErrors('production_schedule_id');
    }

    public function test_planner_cannot_cancel_or_move_a_schedule_after_a_lot_has_started(): void
    {
        $schedule = $this->schedule();
        $this->board($schedule, ['actual_pickup_date' => '', 'actual_pickup_time' => '', 'event_status' => '', 'weight_kg' => '', 'reject_weight_kg' => '0'])->call('save')->assertHasNoErrors();
        Livewire::test(ProductionPlanPage::class)->call('askCancel', $schedule->id)->call('cancel')->assertHasErrors('cancel');
        Livewire::test(ProductionPlanPage::class)->call('openEdit', $schedule->id)->set('form.planned_on', '2026-10-30')->call('save')->assertHasErrors('form.schedule');
        $this->assertSame('scheduled', $schedule->fresh()->status);
        $this->assertSame('2026-10-29', $schedule->fresh()->planned_on->format('Y-m-d'));
    }

    public function test_cancelled_or_unconfigured_schedules_cannot_receive_lots(): void
    {
        $schedule = $this->schedule(withRod: false);
        $this->board($schedule, ['rod_diameter_mm' => '8'])->call('save')->assertHasErrors('production_schedule_id');
        $configured = $this->schedule();
        app(ProductionPlanningService::class)->cancel($configured->id, 1);
        $this->board($configured)->call('save')->assertHasErrors('production_schedule_id');
        $this->assertDatabaseCount('board_entries', 0);
    }

    public function test_operator_can_use_readonly_plan_data_without_gaining_planning_edit_access(): void
    {
        $schedule = $this->schedule();
        $this->actingAs(User::factory()->create(['role' => UserRole::OperatorProduksi]));
        $this->get('/ppic/production-plan')->assertForbidden();
        Livewire::test(BoardPage::class)->call('useProductionSchedule', $schedule->id)
            ->assertSet('production_schedule_id', (string) $schedule->id)->assertSet('rod_diameter_mm', '8')
            ->assertDontSeeHtml('id="board-rod_diameter_mm"')
            ->assertSee($schedule->reference)->assertSee('TARGET PRODUKSI');
        $this->board($schedule)->call('save')->assertHasNoErrors();
        $this->assertSame('245.000000', $this->progress($schedule)['actual']);
    }

    public function test_conversion_uses_database_uom_factor_and_preserves_the_schedule_snapshot(): void
    {
        $kg = Unit::where('code', 'KG')->firstOrFail();
        Unit::create(['code' => 'TON', 'name' => 'Ton', 'related_uom_id' => $kg->id, 'factor' => 1000, 'is_active' => true]);
        $schedule = $this->schedule('TON');
        $this->board($schedule, ['weight_kg' => '200', 'reject_weight_kg' => '0'])->call('save')->assertHasNoErrors();
        $this->sameProgressOnBothPages($schedule, '0.200000');
        $this->assertSame('-0.400000', $this->progress($schedule)['difference']);
        Unit::where('code', 'TON')->update(['factor' => 500]);
        Livewire::test(ProductionPlanPage::class)->call('openEdit', $schedule->id)->set('form.notes', 'Tetap memakai satuan saat mulai produksi')->call('save')->assertHasNoErrors();
        $this->assertSame('1000.000000000', $schedule->fresh()->kg_per_unit);
        $this->sameProgressOnBothPages($schedule, '0.200000');
    }

    public function test_unmapped_non_weight_units_cannot_silently_be_treated_as_kg(): void
    {
        $schedule = $this->schedule('PCS');
        $this->assertNull($schedule->kg_per_unit);
        $this->board($schedule)->call('save')->assertHasErrors('production_schedule_id');
        $this->assertNull($this->progress($schedule)['actual']);
    }

    public function test_duplicate_lot_cycle_is_rejected_but_legacy_unlinked_weights_are_not_counted(): void
    {
        $schedule = $this->schedule();
        BoardEntry::create(['lot' => 'OLD', 'board_date' => '2026-10-29', 'weight_kg' => 1000]);
        $this->board($schedule, ['lot' => 'SAME-LOT'])->call('save')->assertHasNoErrors();
        $this->board($schedule, ['lot' => 'SAME-LOT'])->call('save')->assertHasErrors('lot');
        $this->assertSame('245.000000', $this->progress($schedule)['actual']);
        $this->assertDatabaseCount('board_entries', 2);
    }

    public function test_existing_standalone_lot_cannot_be_assigned_to_a_different_diameter_order(): void
    {
        $schedule = $this->schedule();
        $entry = BoardEntry::create(['lot' => 'LEGACY', 'board_date' => '2026-10-29', 'rod_diameter_mm' => 20,
            'take_up' => '1XL', 'cooling' => '1XR', 'crystallizer' => 'CR-TEST', 'take_up_speed' => 6000,
            'upcasting_date' => '2026-10-29', 'upcasting_time' => '08:00']);
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->call('save')->assertHasErrors('production_schedule_id');
        Livewire::test(BoardPage::class)->call('edit', $entry->id)->set('production_schedule_id', (string) $schedule->id)
            ->call('save')->assertHasErrors('production_schedule_id');
        $this->assertNull($entry->fresh()->production_schedule_id);
        $this->assertSame('0.000000', $this->progress($schedule)['actual']);
    }

    public function test_board_requires_a_schedule_linked_to_so_and_cannot_bypass_the_service(): void
    {
        $schedule = $this->schedule();
        $this->board($schedule)->set('production_schedule_id', '')->assertDontSee('Tanpa jadwal')
            ->assertDontSeeHtml('id="board-rod_diameter_mm"')->call('save')->assertHasErrors('production_schedule_id');
        $this->assertDatabaseCount('board_entries', 0);
        try {
            app(\App\Modules\Produksi\Services\ProductionPlanIntegrationService::class)->prepareBoard([], '', null, '0');
            $this->fail('A schedule-less service write must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('production_schedule_id', $exception->errors());
        }
        $this->board($schedule)->call('save')->assertHasNoErrors();
        $this->assertSame($schedule->handoff->sale->id, BoardEntry::firstOrFail()->productionSchedule->handoff->sale->id);
    }
}
