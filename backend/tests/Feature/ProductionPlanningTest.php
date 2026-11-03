<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Ppic\Livewire\ProductionPlanPage;
use App\Modules\Ppic\Models\InventoryReceipt;
use App\Modules\Ppic\Models\MaterialReservation;
use App\Modules\Ppic\Models\ProductionHandoff;
use App\Modules\Ppic\Services\ProductionPlanningService;
use App\Modules\Ppic\Services\SalesReadinessService;
use App\Modules\Purchasing\Services\ProcurementService;
use App\Services\SinoContextService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\UsesMasterSales;
use Tests\TestCase;

class ProductionPlanningTest extends TestCase
{
    use RefreshDatabase;
    use UsesMasterSales;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-29 10:00', 'Asia/Bangkok'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29 10:00', 'Asia/Bangkok'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function ready(string $quantity = '1000', string $uom = 'KG'): ProductionHandoff
    {
        $product = $this->masterProduct($this->masterMaterial(), '1000', '1000', $uom);
        $sale = $this->masterSale($product, $quantity);
        $ic = app(SalesReadinessService::class);
        $ic->prepare($sale->id);
        foreach ($sale->materialRequirements()->get() as $requirement) {
            $procurement = app(ProcurementService::class);
            $request = $procurement->submit(['product_id' => $requirement->material_id, 'quantity' => $requirement->quantity, 'required_on' => '2026-10-29']);
            $order = $procurement->approve($request->id, '2026-10-29', '2026-10-29');
            $procurement->receive($order->id, $requirement->quantity, '2026-10-29', (string) Str::uuid());
        }

        return $ic->handOver($sale->id)->load('sale');
    }

    private function data(ProductionHandoff $handoff, string $quantity = '600', string $date = '2026-10-29'): array
    {
        return ['handoff_id' => $handoff->id, 'planned_on' => $date, 'quantity' => $quantity,
            'notes' => 'Rencana uji', 'submission_token' => (string) Str::uuid()];
    }

    private function rejected(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('The invalid planning action should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_only_handed_over_orders_are_available_and_earliest_shipping_date_is_first(): void
    {
        $later = $this->ready();
        $earlier = $this->ready();
        $earlier->sale->update(['ship_by' => '2026-10-30']);
        $notHandedOver = $this->masterSale($this->masterProduct($this->masterMaterial()));
        $page = Livewire::test(ProductionPlanPage::class)->assertSet('selectedDate', '2026-10-29')
            ->assertViewHas('handoffs', fn ($rows) => $rows->pluck('id')->all() === [$earlier->id, $later->id]);
        $page->set('search', $earlier->sale->reference)
            ->assertViewHas('handoffs', fn ($rows) => $rows->count() === 1 && $rows->first()->id === $earlier->id);
        $this->assertFalse(app(ProductionPlanningService::class)->handoffs('', false)->get()->contains('sales_order_id', $notHandedOver->id));
    }

    public function test_order_can_be_split_across_days_with_exact_remaining_and_no_stock_movements(): void
    {
        $handoff = $this->ready();
        $inventory = [InventoryReceipt::count(), InventoryReceipt::sum('quantity'), MaterialReservation::count(), MaterialReservation::sum('quantity')];
        $service = app(ProductionPlanningService::class);
        $first = $service->save($this->data($handoff));
        $this->assertSame('400.000000', (string) $service->remaining($handoff));
        $second = $service->save($this->data($handoff, '400', '2026-10-30'));
        $this->assertSame('0.000000', (string) $service->remaining($handoff));
        $this->assertSame(0, $service->handoffs()->count());
        $this->assertSame(1, $service->handoffs('', false)->count());
        Livewire::test(ProductionPlanPage::class)->assertViewHas('dailyCount', 1)
            ->assertViewHas('dailyTotals', fn ($rows) => (float) $rows->first()['quantity'] === 600.0)
            ->call('selectDate', '2026-10-30')->assertViewHas('schedules', fn ($rows) => $rows->first()->id === $second->id)
            ->assertViewHas('dailyTotals', fn ($rows) => (float) $rows->first()['quantity'] === 400.0);
        $this->rejected(fn () => $service->save($this->data($handoff, '0.000001', '2026-10-31')), 'quantity');
        $this->assertSame($inventory, [InventoryReceipt::count(), InventoryReceipt::sum('quantity'), MaterialReservation::count(), MaterialReservation::sum('quantity')]);
        $this->assertSame(auth()->id(), $first->created_by);
        $this->assertSame('1000.000000', $handoff->sale->fresh()->quantity);
    }

    public function test_unreserved_handoff_invalid_dates_and_invalid_quantities_cannot_save(): void
    {
        $handoff = $this->ready();
        $service = app(ProductionPlanningService::class);
        foreach (['0', '-1', 'abc', '1000.000001', '0.0000001'] as $value) {
            $this->rejected(fn () => $service->save($this->data($handoff, $value)), 'quantity');
        }
        foreach (['2026-10-28', '2026-02-30', 'invalid'] as $date) {
            $this->rejected(fn () => $service->save($this->data($handoff, '1', $date)), 'planned_on');
        }
        $handoff->reservations()->update(['quantity' => '1']);
        $this->rejected(fn () => $service->save($this->data($handoff)), 'schedule');
        $this->assertDatabaseCount('production_schedules', 0);
    }

    public function test_retried_submissions_are_idempotent_and_new_same_day_batches_are_allowed(): void
    {
        $handoff = $this->ready();
        $service = app(ProductionPlanningService::class);
        $data = $this->data($handoff);
        $first = $service->save($data);
        $this->assertSame($first->id, $service->save($data)->id);
        $this->assertDatabaseCount('production_schedules', 1);
        $second = $service->save($this->data($handoff, '200'));
        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('production_schedules', 2);
        $this->assertSame('200.000000', (string) $service->remaining($handoff));
        $this->rejected(fn () => $service->save($this->data($handoff, '201')), 'quantity');
        $other = $this->ready();
        $this->rejected(fn () => $service->save(array_replace($data, ['handoff_id' => $other->id])), 'schedule');
    }

    public function test_same_order_can_have_multiple_schedules_on_one_day_and_order_count_stays_distinct(): void
    {
        $handoff = $this->ready();
        $service = app(ProductionPlanningService::class);
        $first = $service->save($this->data($handoff, '600'));
        $second = $service->save($this->data($handoff, '400', '2026-10-30'));
        $service->save($this->data($handoff, '400'), $second->id, 1);
        Livewire::test(ProductionPlanPage::class)->assertViewHas('dailyCount', 2)->assertViewHas('dailyOrderCount', 1)
            ->assertViewHas('dailyTotals', fn ($rows) => (float) $rows->first()['quantity'] === 1000.0);
        $this->assertSame('0.000000', (string) $service->remaining($handoff));
        $this->rejected(fn () => $service->save($this->data($handoff, '1')), 'quantity');
        $service->cancel($first->id, 1);
        $this->assertSame('600.000000', (string) $service->remaining($handoff));
        $this->assertSame('scheduled', $second->fresh()->status);
        Livewire::test(ProductionPlanPage::class)->assertViewHas('dailyCount', 1)->assertViewHas('dailyOrderCount', 1);
    }

    public function test_rescheduling_excludes_own_quantity_and_stale_edit_cannot_overwrite_another_planner(): void
    {
        $handoff = $this->ready();
        $service = app(ProductionPlanningService::class);
        $first = $service->save($this->data($handoff));
        $service->save($this->data($handoff, '400', '2026-10-30'));
        $planner = User::factory()->create(['role' => UserRole::Ppic]);
        $this->actingAs($planner);
        $updated = $service->save($this->data($handoff, '500', '2026-10-31'), $first->id, 1);
        $this->assertSame(2, $updated->revision);
        $this->assertSame($planner->id, $updated->updated_by);
        $this->assertSame('100.000000', (string) $service->remaining($handoff));
        $this->rejected(fn () => $service->save($this->data($handoff, '400', '2026-11-01'), $first->id, 1), 'schedule');
        $this->assertSame('500.000000', $first->fresh()->quantity);
        $this->assertSame('2026-10-31', $first->fresh()->planned_on->format('Y-m-d'));
        $this->rejected(fn () => $service->save($this->data($handoff, '601', '2026-10-31'), $first->id, 2), 'quantity');
    }

    public function test_cancellation_requires_confirmation_keeps_history_and_releases_only_planned_quantity(): void
    {
        $handoff = $this->ready();
        $service = app(ProductionPlanningService::class);
        $schedule = $service->save($this->data($handoff, '1000'));
        $reserved = MaterialReservation::sum('quantity');
        $page = Livewire::test(ProductionPlanPage::class)->call('askCancel', $schedule->id)->call('closeCancel');
        $this->assertSame('scheduled', $schedule->fresh()->status);
        $page->call('askCancel', $schedule->id)->call('cancel')->assertHasNoErrors()->assertSet('showCancelled', true)
            ->assertViewHas('dailyCount', 0)->assertViewHas('pendingCount', 1)->assertSee('Dibatalkan');
        $schedule->refresh();
        $this->assertSame('cancelled', $schedule->status);
        $this->assertNotNull($schedule->cancelled_at);
        $this->assertSame(auth()->id(), $schedule->cancelled_by);
        $this->assertSame('1000.000000', (string) $service->remaining($handoff));
        $this->assertSame($reserved, MaterialReservation::sum('quantity'));
        $this->assertSame($schedule->id, $service->cancel($schedule->id, 1)->id);
        $this->rejected(fn () => $service->save($this->data($handoff), $schedule->id, 2), 'schedule');
        $service->save($this->data($handoff, '1000'));
        $this->assertDatabaseCount('production_schedules', 2);
    }

    public function test_two_open_forms_cannot_overallocate_and_can_refresh_the_remaining_quantity(): void
    {
        $handoff = $this->ready();
        $first = Livewire::test(ProductionPlanPage::class)->call('openSchedule', $handoff->id);
        $second = Livewire::test(ProductionPlanPage::class)->call('selectDate', '2026-10-30')->call('openSchedule', $handoff->id);
        $first->set('form.quantity', '700')->call('save')->assertHasNoErrors()->assertSet('showForm', false);
        $second->set('form.quantity', '600')->call('save')->assertHasErrors('form.quantity')->assertSet('showForm', true)
            ->call('useRemaining')->assertSet('form.quantity', '300')->call('save')->assertHasNoErrors();
        $this->assertSame('0.000000', (string) app(ProductionPlanningService::class)->remaining($handoff));
        $this->assertDatabaseCount('production_schedules', 2);
    }

    public function test_micro_quantities_do_not_silently_round_or_exceed_the_order(): void
    {
        $handoff = $this->ready('1.000001');
        $service = app(ProductionPlanningService::class);
        $service->save($this->data($handoff, '0.000001'));
        $this->assertSame('1.000000', (string) $service->remaining($handoff));
        $service->save($this->data($handoff, '1', '2026-10-30'));
        $this->assertSame('0.000000', (string) $service->remaining($handoff));
        $this->rejected(fn () => $service->save($this->data($handoff, '0.000001', '2026-10-31')), 'quantity');
    }

    public function test_daily_totals_keep_different_units_separate_and_sino_uses_actual_schedules(): void
    {
        $kg = $this->ready();
        $ton = $this->ready('2', 'TON');
        $service = app(ProductionPlanningService::class);
        $service->save($this->data($kg, '1000'));
        $service->save($this->data($ton, '2'));
        Livewire::test(ProductionPlanPage::class)->assertViewHas('dailyCount', 2)
            ->assertViewHas('dailyTotals', fn ($rows) => $rows->count() === 2 && $rows->pluck('unit')->sort()->values()->all() === ['KG', 'TON']);
        $context = app(SinoContextService::class)->build()['ppic'];
        $this->assertCount(2, $context['rencana_produksi_hari_ini']);
        $this->assertCount(2, $context['30_jadwal_produksi_terbaru']);
        $this->assertSame('2026-10-29', $context['tanggal_rencana_hari_ini']);
    }

    public function test_late_plan_is_visible_and_form_validation_keeps_the_selected_order(): void
    {
        $handoff = $this->ready();
        $handoff->sale->update(['ship_by' => '2026-10-28']);
        Livewire::test(ProductionPlanPage::class)->call('openSchedule', $handoff->id)
            ->assertSee('Tanggal produksi melewati batas kirim')->set('form.quantity', '2000')->call('save')
            ->assertHasErrors('form.quantity')->assertSet('handoffId', $handoff->id)
            ->set('form.quantity', '500')->call('save')->assertHasNoErrors()->assertSee('Lewat batas kirim');
        Livewire::test(ProductionPlanPage::class)->set('selectedDate', 'bad-date')->assertHasErrors('selectedDate')->assertOk();
    }

    public function test_other_departments_cannot_open_or_mutate_planning_even_with_ic_permissions(): void
    {
        $handoff = $this->ready();
        $service = app(ProductionPlanningService::class);
        $schedule = $service->save($this->data($handoff));
        foreach ([UserRole::Marketing, UserRole::Purchasing, UserRole::OperatorProduksi] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/ppic/production-plan')->assertForbidden();
            Livewire::test(ProductionPlanPage::class)->assertForbidden();
            $this->assertThrows(fn () => $service->save($this->data($handoff, '1', '2026-10-30')), AuthorizationException::class);
            $this->assertThrows(fn () => $service->cancel($schedule->id, 1), AuthorizationException::class);
        }
        $this->assertDatabaseCount('production_schedules', 1);
        $this->assertSame('scheduled', $schedule->fresh()->status);
    }

    public function test_fresh_livewire_update_can_open_scheduler_and_revoked_access_is_enforced(): void
    {
        $handoff = $this->ready();
        $response = $this->get('/ppic/production-plan')->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES, 'UTF-8'))
            ->first(fn ($value) => json_decode($value, true)['memo']['name'] === 'ppic.production-plan-page');
        $this->assertNotNull($snapshot);
        $pdo = $this->app['db']->connection()->getPdo();
        $userId = auth()->id();
        $this->refreshApplication();
        $this->app['db']->connection()->setPdo($pdo);
        $this->actingAs(User::findOrFail($userId));
        $payload = ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'openSchedule', 'params' => [$handoff->id]]]]]];
        $this->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertOk()->assertSee('Jumlah produksi');
        $this->actingAs(User::factory()->create(['role' => UserRole::Marketing]));
        $this->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertForbidden();
    }
}
