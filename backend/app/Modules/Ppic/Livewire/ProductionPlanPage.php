<?php

namespace App\Modules\Ppic\Livewire;

use App\Modules\Ppic\Models\ProductionHandoff;
use App\Modules\Ppic\Models\ProductionSchedule;
use App\Modules\Ppic\Services\ProductionPlanningService;
use App\Modules\Produksi\Services\ProductionPlanIntegrationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Production Plan — irris.next')]
class ProductionPlanPage extends Component
{
    use WithPagination;

    public string $selectedDate = '';

    public string $search = '';

    public string $backlogFilter = 'pending';

    public bool $showCancelled = false;

    #[Locked]
    public bool $showForm = false;

    #[Locked]
    public ?int $handoffId = null;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $revision = null;

    #[Locked]
    public string $submissionToken = '';

    #[Locked]
    public ?int $cancelId = null;

    #[Locked]
    public ?int $cancelRevision = null;

    public array $form = ['planned_on' => '', 'quantity' => '', 'notes' => ''];

    public function boot(): void
    {
        Gate::authorize('access-ppic');
    }

    public function mount(): void
    {
        $this->selectedDate = $this->today();
    }

    private function today(): string
    {
        return CarbonImmutable::today(ProductionPlanningService::TIMEZONE)->format('Y-m-d');
    }

    private function date(): CarbonImmutable
    {
        $valid = Validator::make(['date' => $this->selectedDate], ['date' => 'required|date_format:Y-m-d'])->passes();

        return CarbonImmutable::parse($valid ? $this->selectedDate : $this->today(), ProductionPlanningService::TIMEZONE)->locale('id');
    }

    public function updatedSelectedDate(): void
    {
        $this->resetPage('schedulesPage');
        $this->validateOnly('selectedDate', ['selectedDate' => 'required|date_format:Y-m-d'], [], ['selectedDate' => 'tanggal rencana']);
    }

    public function selectDate(string $date): void
    {
        Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->validate();
        $this->selectedDate = $date;
        $this->resetValidation('selectedDate');
        $this->resetPage('schedulesPage');
    }

    public function moveDate(int $days): void
    {
        abort_unless(in_array($days, [-7, -1, 1, 7], true), 422);
        $this->selectDate($this->date()->addDays($days)->format('Y-m-d'));
    }

    public function goToToday(): void
    {
        $this->selectDate($this->today());
    }

    public function updatedSearch(): void
    {
        $this->resetPage('handoffsPage');
    }

    public function updatedBacklogFilter(): void
    {
        $this->resetPage('handoffsPage');
    }

    public function updatedShowCancelled(): void
    {
        $this->resetPage('schedulesPage');
    }

    public function openSchedule(int $handoffId): void
    {
        $handoff = ProductionHandoff::with('sale')->findOrFail($handoffId);
        $remaining = app(ProductionPlanningService::class)->remaining($handoff);
        if ($remaining->isZero()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Seluruh jumlah pesanan sudah dijadwalkan. Ubah jadwal yang sudah ada jika diperlukan.']);

            return;
        }
        $this->closeForm();
        $this->handoffId = $handoffId;
        $this->submissionToken = (string) Str::uuid();
        $this->form = ['planned_on' => max($this->date()->format('Y-m-d'), $this->today()), 'quantity' => $this->quantityInput((string) $remaining), 'notes' => ''];
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $schedule = ProductionSchedule::findOrFail($id);
        abort_unless($schedule->status === 'scheduled', 422);
        $this->closeForm();
        $this->editingId = $id;
        $this->handoffId = $schedule->handoff_id;
        $this->revision = $schedule->revision;
        $this->submissionToken = $schedule->submission_token;
        $this->form = ['planned_on' => $schedule->planned_on->format('Y-m-d'), 'quantity' => $this->quantityInput($schedule->quantity), 'notes' => $schedule->notes ?? ''];
        $this->showForm = true;
    }

    public function useRemaining(): void
    {
        if (! $this->showForm || ! $this->handoffId) {
            return;
        }
        $handoff = ProductionHandoff::with('sale')->findOrFail($this->handoffId);
        $this->form['quantity'] = $this->quantityInput((string) app(ProductionPlanningService::class)->remaining($handoff, $this->editingId));
        $this->resetValidation('form.quantity');
    }

    private function quantityInput(string $quantity): string
    {
        return str_contains($quantity, '.') ? rtrim(rtrim($quantity, '0'), '.') : $quantity;
    }

    public function save(): void
    {
        if (! $this->showForm || ! $this->handoffId) {
            return;
        }
        try {
            $schedule = app(ProductionPlanningService::class)->save([
                'handoff_id' => $this->handoffId, 'submission_token' => $this->submissionToken,
                'planned_on' => $this->form['planned_on'] ?? '', 'quantity' => $this->form['quantity'] ?? '', 'notes' => $this->form['notes'] ?? '',
            ], $this->editingId, $this->revision);
        } catch (ValidationException $e) {
            $this->setErrorBag(collect($e->errors())->mapWithKeys(fn ($messages, $key) => ['form.'.$key => $messages])->all());

            return;
        }
        $this->selectedDate = $schedule->planned_on->format('Y-m-d');
        $this->closeForm();
        $this->resetPage('schedulesPage');
        $this->resetPage('handoffsPage');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Jadwal produksi berhasil disimpan.']);
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->handoffId = $this->editingId = $this->revision = null;
        $this->submissionToken = '';
        $this->form = ['planned_on' => '', 'quantity' => '', 'notes' => ''];
        $this->resetErrorBag();
    }

    public function askCancel(int $id): void
    {
        $schedule = ProductionSchedule::where('status', 'scheduled')->findOrFail($id);
        $this->resetErrorBag();
        $this->cancelId = $id;
        $this->cancelRevision = $schedule->revision;
    }

    public function closeCancel(): void
    {
        $this->cancelId = $this->cancelRevision = null;
        $this->resetValidation('cancel');
    }

    public function cancel(): void
    {
        if (! $this->cancelId || ! $this->cancelRevision) {
            return;
        }
        try {
            app(ProductionPlanningService::class)->cancel($this->cancelId, $this->cancelRevision);
        } catch (ValidationException $e) {
            $this->addError('cancel', collect($e->errors())->flatten()->first());

            return;
        }
        $this->closeCancel();
        $this->showCancelled = true;
        $this->resetPage('handoffsPage');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Jadwal dibatalkan. Jumlahnya tersedia untuk dijadwalkan kembali.']);
    }

    public function render()
    {
        $planner = app(ProductionPlanningService::class);
        $date = $this->date();
        $dateKey = $date->format('Y-m-d');
        $weekStart = $date->startOfWeek();
        $weekStats = DB::table('production_schedules as plans')
            ->join('production_handoffs as handoffs', 'handoffs.id', '=', 'plans.handoff_id')
            ->join('marketing_sales_orders as sales', 'sales.id', '=', 'handoffs.sales_order_id')
            ->where('plans.status', 'scheduled')->whereBetween('plans.planned_on', [$weekStart->format('Y-m-d'), $weekStart->addDays(6)->format('Y-m-d')])
            ->selectRaw('plans.planned_on, sales.uom_id, SUM(plans.quantity) as quantity, COUNT(*) as schedule_count, COUNT(DISTINCT plans.handoff_id) as order_count, MIN(CAST(sales.unit_snapshot AS TEXT)) as unit_snapshot')
            ->groupBy('plans.planned_on', 'sales.uom_id')->get()->groupBy('planned_on');
        $dailyGroups = $weekStats->get($dateKey, collect());
        $week = collect(range(0, 6))->map(function ($offset) use ($weekStart, $weekStats) {
            $day = $weekStart->addDays($offset);

            return ['date' => $day->format('Y-m-d'), 'day' => $day->translatedFormat('D'), 'number' => $day->format('d'),
                'count' => $weekStats->get($day->format('Y-m-d'), collect())->sum('schedule_count')];
        });
        $selectedHandoff = $this->showForm && $this->handoffId
            ? ProductionHandoff::with('sale.materialRequirements')->findOrFail($this->handoffId) : null;
        $cancelSchedule = $this->cancelId ? ProductionSchedule::with('handoff.sale')->findOrFail($this->cancelId) : null;
        $dailySchedules = ProductionSchedule::withActuals()->with('handoff.sale')->where('planned_on', $dateKey)->where('status', 'scheduled')->orderBy('id')->get();
        $editingHasLots = $this->editingId ? ProductionSchedule::findOrFail($this->editingId)->boardEntries()->exists() : false;
        $planningRod = null;
        $planningRodError = null;
        if ($selectedHandoff) {
            $existing = $this->editingId ? ProductionSchedule::findOrFail($this->editingId) : null;
            try {
                $planningRod = $existing?->rod_specification_id ? $existing->only(['rod_specification_id', 'rod_diameter_mm', 'rod_weight_ton_per_meter'])
                    : $planner->rodForSale($selectedHandoff->sale);
            } catch (ValidationException $exception) {
                $planningRodError = collect($exception->errors())->flatten()->first();
            }
        }

        return view('modules.ppic.livewire.production-plan-page', [
            'dateLabel' => $date->translatedFormat('l, d F Y'), 'dateKey' => $dateKey, 'today' => $this->today(), 'week' => $week,
            'dailyCount' => $dailyGroups->sum('schedule_count'),
            'dailyOrderCount' => $dailyGroups->sum('order_count'),
            'dailyTotals' => app(ProductionPlanIntegrationService::class)->totals($dailySchedules),
            'pendingCount' => $planner->handoffs()->count(),
            'schedules' => ProductionSchedule::withActuals()->with(['handoff.sale', 'planner'])->where('planned_on', $dateKey)
                ->when(! $this->showCancelled, fn ($q) => $q->where('status', 'scheduled'))
                ->orderBy('status', 'desc')->orderBy('id')->paginate(12, ['*'], 'schedulesPage'),
            'handoffs' => $planner->handoffs(mb_substr($this->search, 0, 200), $this->backlogFilter !== 'all')->paginate(12, ['*'], 'handoffsPage'),
            'selectedHandoff' => $selectedHandoff,
            'maximumQuantity' => $selectedHandoff ? (string) $planner->remaining($selectedHandoff, $this->editingId) : '0',
            'cancelSchedule' => $cancelSchedule,
            'editingHasLots' => $editingHasLots,
            'planningRod' => $planningRod, 'planningRodError' => $planningRodError,
        ]);
    }
}
