<?php

namespace App\Modules\Produksi\Livewire;

use App\Modules\Produksi\Enums\EventStatus;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Models\CopperBreakEntry;
use App\Modules\Produksi\Models\ShiftEntry;
use App\Modules\Produksi\Services\ServoInferenceService;
use App\Modules\Produksi\Services\WipFormatter;
use App\Modules\Produksi\Services\ProductionRuntimeService;
use App\Modules\Produksi\Services\OutputFormulaService;
use App\Modules\Produksi\Services\LotSequenceService;
use App\Modules\Produksi\Services\ProductionMasterDataService;
use App\Modules\Produksi\Services\ProductionPlanIntegrationService;
use App\Modules\Produksi\Services\LotEstimateService;
use App\Modules\Ppic\Models\ProductionSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Digital WIP Coiling Board')]
class BoardPage extends Component
{
    public string $search = '';
    public string $startDate = '';
    public string $endDate = '';

    #[Locked] public ?int $editingId = null;
    public bool $showBoardForm = false;
    public string $board_date = '';
    public string $shift = '';
    public string $take_up = '';
    public string $take_up_speed = '';
    public string $rod_diameter_mm = '';
    public string $cooling = '';
    public string $crystallizer = '';
    public string $upcasting_date = '';
    public string $upcasting_time = '';
    public string $estimated_pickup_date = '';
    public string $estimated_pickup_time = '';
    public string $actual_pickup_date = '';
    public string $actual_pickup_time = '';
    public string $lot = '';
    public string $weight_kg = '';
    public string $operating_hours = '';
    public string $event_status = '';
    public string $remarks = '';
    public string $production_schedule_id = '';
    public string $reject_weight_kg = '0';
    public string $planDate = '';

    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('access-produksi');
    }

    public function mount(): void
    {
        $today = Carbon::now('Asia/Jakarta');
        $this->startDate = $today->copy()->subDays(60)->format('Y-m-d');
        $this->endDate = $today->format('Y-m-d');
        $this->planDate = $today->format('Y-m-d');
        $this->resetBoardForm();
    }

    public function openBoardForm(): void
    {
        $this->resetBoardForm();
        $this->showBoardForm = true;
    }

    public function updatedProductionScheduleId($value): void
    {
        if (config('copper_rod.standalone')) return;
        $schedule = app(ProductionPlanIntegrationService::class)->selected((string) $value);
        if ($schedule?->rod_diameter_mm !== null) $this->rod_diameter_mm = (string) $schedule->rod_diameter_mm;
        $this->resetValidation('production_schedule_id');
    }

    public function useProductionSchedule(int $id): void
    {
        abort_if(config('copper_rod.standalone'), 422);
        $schedule = app(ProductionPlanIntegrationService::class)->selected((string) $id);
        if (!$schedule || $schedule->status !== 'scheduled' || !$schedule->rod_specification_id || !$schedule->kg_per_unit) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Periksa spesifikasi produk pada SO dan konversi KG untuk jadwal aktif.']);
            return;
        }
        $this->resetBoardForm();
        $this->production_schedule_id = (string) $id;
        $this->updatedProductionScheduleId((string) $id);
        $this->showBoardForm = true;
    }

    public function resetBoardForm(): void
    {
        $this->editingId = null;
        $this->board_date = Carbon::now('Asia/Jakarta')->format('Y-m-d');
        $this->shift = '';
        $this->take_up = '';
        $this->take_up_speed = '';
        $this->rod_diameter_mm = '';
        $this->cooling = '';
        $this->crystallizer = '';
        $this->upcasting_date = '';
        $this->upcasting_time = '';
        $this->estimated_pickup_date = '';
        $this->estimated_pickup_time = '';
        $this->actual_pickup_date = '';
        $this->actual_pickup_time = '';
        $this->lot = '';
        $this->weight_kg = '';
        $this->operating_hours = '';
        $this->event_status = '';
        $this->remarks = '';
        $this->production_schedule_id = '';
        $this->reject_weight_kg = '0';
        $this->resetErrorBag();
    }

    public function setQuickPeriod(int $days): void
    {
        $today = Carbon::now('Asia/Jakarta');
        $this->startDate = $today->copy()->subDays($days - 1)->format('Y-m-d');
        $this->endDate = $today->format('Y-m-d');
    }

    public function clearSearch(): void
    {
        $this->search = '';
    }

    public function refreshEntries(): void
    {
    }

    public function updatedPlanDate(): void
    {
        $this->validateOnly('planDate', ['planDate' => 'required|date_format:Y-m-d'], [], ['planDate' => 'tanggal rencana']);
    }

    public function selectPlanDate(string $date): void
    {
        \Illuminate\Support\Facades\Validator::make(['planDate' => $date], ['planDate' => 'required|date_format:Y-m-d'])->validate();
        $this->planDate = $date;
        $this->resetValidation('planDate');
    }

    public function movePlanWeek(int $direction): void
    {
        $this->updatedPlanDate();
        $this->selectPlanDate(Carbon::parse($this->planDate)->addDays($direction < 0 ? -7 : 7)->format('Y-m-d'));
    }

    public function planToday(): void
    {
        $this->selectPlanDate(Carbon::now(ProductionRuntimeService::TIMEZONE)->format('Y-m-d'));
    }

    protected function buildFilteredQuery(): Builder
    {
        $query = BoardEntry::query();

        if ($this->startDate !== '') {
            $query->where('board_date', '>=', $this->startDate);
        }
        if ($this->endDate !== '') {
            $query->where('board_date', '<=', $this->endDate);
        }

        $q = trim($this->search);
        if ($q !== '') {
            $wildcard = "%{$q}%";
            $query->where(function (Builder $sub) use ($wildcard) {
                $sub->where('take_up', 'like', $wildcard)
                    ->orWhere('cooling', 'like', $wildcard)
                    ->orWhere('servo', 'like', $wildcard)
                    ->orWhere('crystallizer', 'like', $wildcard)
                    ->orWhere('lot', 'like', $wildcard)
                    ->orWhere('remarks', 'like', $wildcard);
            });
        }

        return $query;
    }

    public function save(): void
    {
        $hasWeight = trim($this->weight_kg) !== '';
        $hasReject = trim($this->reject_weight_kg) !== '' && (!is_numeric($this->reject_weight_kg) || (float) $this->reject_weight_kg !== 0.0);
        if (($hasWeight || $hasReject) && ($this->actual_pickup_date === '' || $this->actual_pickup_time === '')) {
            $message = 'Anda belum mengisi tanggal dan jam Aktual Pick Up. Lengkapi terlebih dahulu sebelum menyimpan berat dan reject.';
            $this->setErrorBag([$hasWeight ? 'weight_kg' : 'reject_weight_kg' => $message]);
            $this->dispatch('notify', ['type' => 'warning', 'title' => 'Aktual Pick Up belum lengkap', 'message' => $message]);
            return;
        }
        $this->syncEstimatedPickup();
        $original = $this->editingId ? BoardEntry::findOrFail($this->editingId) : null;
        $integration = app(ProductionPlanIntegrationService::class);
        $selectedPlan = config('copper_rod.standalone') ? null : $integration->selected($this->production_schedule_id);
        if ($this->rod_diameter_mm === '' && $selectedPlan?->rod_diameter_mm !== null) {
            $this->rod_diameter_mm = (string) $selectedPlan->rod_diameter_mm;
        }
        $rodContext = $selectedPlan?->rod_diameter_mm ?? $original?->rod_diameter_mm;
        $masters = app(ProductionMasterDataService::class);
        $inferred = $masters->classify($this->take_up, $this->cooling, $original);

        try {
            $this->validate([
                'production_schedule_id' => config('copper_rod.standalone') ? ['prohibited'] : ['bail', 'required', 'integer', 'exists:production_schedules,id'],
                'lot' => ['required', 'string', 'max:255'],
                'take_up' => ['required', 'string', 'max:255', Rule::in($masters->options('take-ups', $original?->take_up)->pluck('value')->all())],
                'take_up_speed' => ['required', 'numeric', 'min:0.0001', 'max:99999999.9999', 'decimal:0,4'],
                'rod_diameter_mm' => ['required', 'numeric', 'gt:0', Rule::in($masters->options('rod-specifications', $rodContext !== null ? (string) $rodContext : null)->pluck('value')->all())],
                'cooling' => ['required', 'string', 'max:255', Rule::in($masters->options('coilings', $original?->cooling)->pluck('value')->all())],
                'crystallizer' => ['required', 'string', 'max:255', Rule::in($masters->options('crystallizers', $original?->crystallizer)->pluck('value')->all())],
                'upcasting_date' => ['required', 'date_format:Y-m-d'],
                'upcasting_time' => ['required', 'date_format:H:i'],
                'actual_pickup_date' => ['nullable', 'required_with:actual_pickup_time', 'date_format:Y-m-d'],
                'actual_pickup_time' => ['nullable', 'required_with:actual_pickup_date', 'date_format:H:i'],
                'weight_kg'       => ['nullable', 'numeric', 'min:0'],
                'event_status'    => ['nullable', 'required_with:actual_pickup_date,actual_pickup_time', Rule::in(['', ...EventStatus::allowedValues()])],
            ], [
                'required' => ':attribute wajib diisi.',
                'production_schedule_id.required' => 'Pilih jadwal Production Plan yang terhubung ke SO. Pencatatan tanpa SO tidak diperbolehkan.',
                'required_with' => ':attribute wajib diisi bersama tanggal dan jam pasangannya.',
                'date_format' => 'Format :attribute tidak valid.',
                'take_up.in' => 'Pilih No. Take Up dari daftar yang tersedia.',
                'cooling.in' => 'Pilih Coiling dari daftar yang tersedia.',
                'crystallizer.in' => 'Pilih Crystallizer aktif dari master data.',
                'rod_diameter_mm.in' => 'Pilih diameter Cu Rod aktif dari master data.',
                'event_status.required_with' => 'Aktual Pick Up sudah diisi. Pilih status Panen atau Putus sebelum menyimpan.',
                'take_up_speed.min' => 'Take Up Speed harus lebih besar dari 0 mm/menit.',
                'take_up_speed.numeric' => 'Take Up Speed harus berupa angka dalam mm/menit.',
                'take_up_speed.decimal' => 'Take Up Speed maksimal 4 angka desimal.',
            ], [
                'production_schedule_id' => 'Jadwal Production Plan / SO',
                'lot' => 'LOT', 'take_up' => 'No. Take Up', 'cooling' => 'Coiling',
                'crystallizer' => 'No. Crystallizer',
                'take_up_speed' => 'Take Up Speed', 'rod_diameter_mm' => 'Diameter Cu Rod',
                'upcasting_date' => 'Tanggal upcasting', 'upcasting_time' => 'Jam upcasting',
                'estimated_pickup_date' => 'Tanggal estimasi pick up', 'estimated_pickup_time' => 'Jam estimasi pick up',
                'actual_pickup_date' => 'Tanggal aktual pick up', 'actual_pickup_time' => 'Jam aktual pick up',
            ]);
            $references = [];
            foreach (['take_up' => 'take-ups', 'cooling' => 'coilings', 'crystallizer' => 'crystallizers', 'rod_diameter_mm' => 'rod-specifications'] as $field => $section) {
                if ($field === 'rod_diameter_mm' && $selectedPlan?->rod_specification_id) {
                    $references[$field.'_master_id'] = $masters->resolve($section, $this->$field, $field, (string) $selectedPlan->rod_diameter_mm, $selectedPlan->rod_specification_id)?->id;
                    continue;
                }
                $references[$field.'_master_id'] = $masters->resolve($section, $this->$field, $field,
                    $original ? (string) $original->$field : null, $original?->{$field.'_master_id'})?->id;
            }
            $start = ProductionRuntimeService::timestamp($this->upcasting_date, $this->upcasting_time);
            $finish = ProductionRuntimeService::timestamp($this->actual_pickup_date, $this->actual_pickup_time);
            $now = Carbon::now(ProductionRuntimeService::TIMEZONE);
            $errors = [];
            if ($inferred['servo_conflict']) {
                $errors['cooling'] = 'Servo X/Y pada No. Take Up dan Coiling harus sama.';
            }
            if ($start->gt($now)) {
                $errors['upcasting_time'] = 'Waktu upcasting tidak boleh di masa depan.';
            }
            if ($finish && ($finish->lt($start) || $finish->gt($now))) {
                $errors['actual_pickup_time'] = 'Aktual pick up harus setelah upcasting dan tidak boleh di masa depan.';
            }
            if ($errors) {
                throw \Illuminate\Validation\ValidationException::withMessages($errors);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->setErrorBag($e->errors());
            $statusError = $e->errors()['event_status'][0] ?? null;
            $firstError = $statusError ?? collect($e->errors())->flatten()->first() ?? 'Data belum valid.';
            $this->dispatch('notify', [
                'title' => $statusError ? 'Lengkapi status kejadian' : 'Periksa data',
                'message' => $firstError,
                'messages' => $statusError ? [$statusError] : collect($e->errors())->flatten()->unique()->values()->all(),
                'type'    => 'error',
            ]);
            return;
        }

        $slot = ProductionRuntimeService::productionSlot($start);
        $this->board_date = $slot['date'];
        $this->shift = $slot['shift'];
        $payload = [
            'board_date'            => $this->board_date,
            'shift'                 => $this->shift !== '' ? strtoupper($this->shift) : null,
            'take_up'               => trim($this->take_up),
            'take_up_speed'         => (float) $this->take_up_speed,
            'rod_diameter_mm'       => (float) $this->rod_diameter_mm,
            'cooling'               => trim($this->cooling),
            'servo'                 => $inferred['servo_conflict'] ? null : ($inferred['servo'] ?: null),
            'crystallizer'          => trim($this->crystallizer),
            'upcasting_date'        => $this->upcasting_date,
            'upcasting_time'        => $this->upcasting_time,
            'estimated_pickup_date' => $this->estimated_pickup_date,
            'estimated_pickup_time' => $this->estimated_pickup_time,
            'actual_pickup_date'    => $this->actual_pickup_date,
            'actual_pickup_time'    => $this->actual_pickup_time,
            'lot'                   => trim($this->lot),
            'weight_kg'             => $this->weight_kg === '' ? null : $this->weight_kg,
            'operating_hours'       => $finish ? $start->diffInSeconds($finish, false) / 3600 : null,
            'event_status'          => EventStatus::normalize($this->event_status),
            'remarks'               => trim($this->remarks),
            'rod_weight_ton_per_meter' => $masters->rodWeight($this->rod_diameter_mm, $original),
            'uses_master_servo' => true,
        ] + $references;

        $isEditing = $this->editingId !== null;
        $lotLabel = $payload['lot'] !== '' ? $payload['lot'] : 'tanpa LOT';

        try {
            DB::transaction(function () use ($payload, $isEditing, $integration, $original) {
                $payload = $integration->prepareBoard($payload, $this->production_schedule_id, $original, $this->reject_weight_kg ?: '0');
                if ($isEditing) {
                    $entry = BoardEntry::lockForUpdate()->findOrFail($this->editingId);
                    if ((int) $entry->production_schedule_id !== (int) $original?->production_schedule_id) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['production_schedule_id' => 'Tautan jadwal catatan sudah berubah. Tutup lalu buka kembali formulir.']);
                    }
                    $entry->update($payload);

                    $classification = $entry->servo_classification;
                    if (!$classification['servo_conflict'] && $classification['servo']) {
                        ShiftEntry::where('board_entry_id', $entry->id)->update([
                            'take_up'      => $entry->take_up,
                            'cooling'      => $entry->cooling,
                            'servo'        => $classification['servo'],
                            'crystallizer' => $entry->crystallizer,
                        ]);
                    }
                } else {
                    BoardEntry::create($payload);
                }
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->setErrorBag($e->errors());
            $this->dispatch('notify', ['type' => 'error', 'title' => 'Periksa data produksi', 'message' => collect($e->errors())->flatten()->first()]);
            return;
        }

        $this->dispatch('notify', [
            'title'   => $isEditing ? 'Perubahan berhasil disimpan' : 'Data berhasil disimpan',
            'message' => $isEditing
                ? "Perubahan untuk LOT {$lotLabel} berhasil disimpan."
                : "Catatan LOT {$lotLabel} tersimpan di riwayat Papan Digital.",
            'type'    => 'success',
        ]);

        $this->resetBoardForm();
        $this->showBoardForm = false;
    }

    public function edit(int $id): void
    {
        $entry = BoardEntry::find($id);
        if (!$entry) {
            $this->dispatch('notify', ['message' => 'Catatan tidak ditemukan.', 'type' => 'error']);
            return;
        }

        $this->editingId = $entry->id;
        $this->production_schedule_id = $entry->production_schedule_id ? (string) $entry->production_schedule_id : '';
        $this->reject_weight_kg = $entry->reject_weight_kg ?? '0';
        $this->board_date = $entry->board_date;
        $this->shift = $entry->shift ?? '';
        $this->take_up = $entry->take_up ?? '';
        $this->take_up_speed = $entry->take_up_speed !== null ? (string) $entry->take_up_speed : '';
        $this->rod_diameter_mm = $entry->rod_diameter_mm !== null ? (string) $entry->rod_diameter_mm : '';
        $this->cooling = $entry->cooling ?? '';
        $this->crystallizer = $entry->crystallizer ?? '';
        $this->upcasting_date = $entry->upcasting_date ?? '';
        $this->upcasting_time = substr($entry->upcasting_time ?? '', 0, 5);
        $this->estimated_pickup_date = $entry->estimated_pickup_date ?? '';
        $this->estimated_pickup_time = $entry->estimated_pickup_time ?? '';
        $this->actual_pickup_date = $entry->actual_pickup_date ?? '';
        $this->actual_pickup_time = substr($entry->actual_pickup_time ?? '', 0, 5);
        $this->lot = $entry->lot ?? '';
        $this->weight_kg = $entry->weight_kg !== null ? (string) $entry->weight_kg : '';
        $this->operating_hours = $entry->operating_hours !== null ? (string) $entry->operating_hours : '';
        $this->event_status = $entry->normalized_event_status;
        $this->remarks = $entry->remarks ?? '';

        $this->resetErrorBag();
        $this->showBoardForm = true;
        $this->syncEstimatedPickup();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['actual_pickup_date', 'actual_pickup_time', 'event_status'], true)) {
            $this->resetValidation('event_status');
        }
        if (in_array($property, ['actual_pickup_date', 'actual_pickup_time'], true)) {
            $this->resetValidation('weight_kg');
            $this->resetValidation('reject_weight_kg');
        }
        if (in_array($property, ['upcasting_date', 'upcasting_time'], true)) $this->syncEstimatedPickup();
    }

    protected function syncEstimatedPickup(): void
    {
        $start = ProductionRuntimeService::timestamp($this->upcasting_date, $this->upcasting_time);
        $estimate = $start?->copy()->addHours(LotEstimateService::CYCLE_HOURS);
        $this->estimated_pickup_date = $estimate?->format('Y-m-d') ?? '';
        $this->estimated_pickup_time = $estimate?->format('H:i') ?? '';
    }

    public function remove(int $id): void
    {
        $entry = BoardEntry::find($id);
        if (!$entry) {
            $this->dispatch('notify', ['message' => 'Catatan tidak ditemukan.', 'type' => 'error']);
            return;
        }

        DB::transaction(function () use ($entry) {
            if ($entry->production_schedule_id) ProductionSchedule::whereKey($entry->production_schedule_id)->lockForUpdate()->first();
            BoardEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            CopperBreakEntry::where('board_entry_id', $entry->id)->delete();
            ShiftEntry::where('board_entry_id', $entry->id)->update(['board_entry_id' => null]);
            $entry->delete();
        });

        if ($this->editingId === $id) {
            $this->resetBoardForm();
        }

        $this->dispatch('notify', [
            'title'   => 'Catatan dihapus',
            'message' => 'Catatan sudah dihapus dari riwayat digital.',
            'type'    => 'success',
        ]);
    }

    public function exportExcel(): void
    {
        $entries = $this->getEntriesCollection();
        if ($entries->isEmpty()) {
            return;
        }

        $headers = [
            'Tanggal Operasi / Shift', 'Shift', 'Take Up', 'Take Up Speed (mm/menit)', 'Diameter Cu Rod (mm)', 'Coiling', 'Servo Otomatis',
            'No Crystallizer', 'Upcasting Tanggal', 'Upcasting Jam',
            'Estimasi Pick Up Tanggal', 'Estimasi Pick Up Jam',
            'Aktual Pick Up Tanggal', 'Aktual Pick Up Jam',
            'LOT', 'Jam Operasi', 'Status Kejadian', 'Estimasi berjalan (kg)', 'Berat (kg)', 'Reject (kg)', 'Aktual hasil baik (kg)', 'Keterangan',
        ];

        $rows = $entries->map(function (BoardEntry $e) {
            $estimate = LotEstimateService::calculate($e->getAttributes());
            return [
                $e->board_date,
                $e->shift,
                $e->take_up,
                $e->take_up_speed,
                $e->rod_diameter_mm,
                $e->cooling,
                $e->servo_conflict ? 'Konflik' : ($e->effective_servo ?? ''),
                $e->crystallizer,
                $e->upcasting_date,
                $e->upcasting_time,
                $e->estimated_pickup_date,
                $e->estimated_pickup_time,
                $e->actual_pickup_date,
                $e->actual_pickup_time,
                $e->lot_sequence ?: $e->lot,
                $e->operating_hours,
                EventStatus::label($e->event_status),
                $estimate['current_kg'],
                $e->weight_kg,
                $e->reject_weight_kg,
                $e->production_actual_kg,
                $e->remarks,
            ];
        })->all();

        $this->dispatch('export-excel', [
            'filename'  => "wip-papan-{$this->startDate}-{$this->endDate}.xlsx",
            'sheetName' => 'Papan Digital',
            'headers'   => $headers,
            'rows'      => $rows,
        ]);
    }

    public function exportPNG(): void
    {
        $entries = $this->getEntriesCollection();
        if ($entries->isEmpty()) {
            return;
        }

        $headers = [
            'Tanggal Operasi', 'Shift', 'Take Up', 'Take Up Speed (mm/menit)', 'Diameter Cu Rod (mm)', 'Coiling', 'Servo',
            'No. Crystallizer', 'Upcasting', 'Estimasi Pick Up', 'Aktual Pick Up',
            'LOT', 'Jam Operasi', 'Status Kejadian', 'Estimasi berjalan (kg)', 'Berat (kg)', 'Reject (kg)', 'Aktual hasil baik (kg)', 'Keterangan',
        ];

        $rows = $entries->map(function (BoardEntry $e) {
            $estimate = LotEstimateService::calculate($e->getAttributes());
            $servoText = $e->servo_conflict
                ? 'Konflik'
                : ($e->effective_servo ? "Servo {$e->effective_servo}" : 'Belum terbaca');

            return [
                WipFormatter::prettyDate($e->board_date),
                $e->shift ?: '—',
                $e->take_up ?: '—',
                $e->take_up_speed === null ? '—' : WipFormatter::number($e->take_up_speed, 4),
                $e->rod_diameter_mm === null ? '—' : (string) $e->rod_diameter_mm,
                $e->cooling ?: '—',
                $servoText,
                $e->crystallizer ?: '—',
                WipFormatter::dateTimeDisplay($e->upcasting_date, $e->upcasting_time),
                WipFormatter::dateTimeDisplay($e->estimated_pickup_date, $e->estimated_pickup_time),
                WipFormatter::dateTimeDisplay($e->actual_pickup_date, $e->actual_pickup_time),
                $e->lot_sequence ?: ($e->lot ?: '—'),
                $e->operating_hours === null ? '—' : WipFormatter::number($e->operating_hours, 2),
                EventStatus::label($e->event_status),
                $estimate['current_kg'] === null ? '—' : WipFormatter::number($estimate['current_kg'], 3),
                $e->weight_kg === null ? '—' : WipFormatter::number($e->weight_kg, 2),
                WipFormatter::number($e->reject_weight_kg, 2),
                $e->production_actual_kg === null ? '—' : WipFormatter::number($e->production_actual_kg, 2),
                $e->remarks ?: '—',
            ];
        })->all();

        $period = WipFormatter::prettyDate($this->startDate) . ' – ' . WipFormatter::prettyDate($this->endDate);
        if (trim($this->search) !== '') {
            $period .= ' · Pencarian: ' . trim($this->search);
        }

        $this->dispatch('export-png', [
            'filename' => "wip-papan-{$this->startDate}-{$this->endDate}.png",
            'title'    => 'Digital WIP Coiling Board',
            'period'   => $period,
            'headers'  => $headers,
            'rows'     => $rows,
        ]);
    }

    protected function getEntriesCollection(): Collection
    {
        $entries = $this->buildFilteredQuery()
            ->orderByDesc('board_date')
            ->orderByDesc('id')
            ->limit(1000)
            ->get();
        return app(LotSequenceService::class)->decorate($entries);
    }

    public function render()
    {
        $this->syncEstimatedPickup();
        $baseQuery = $this->buildFilteredQuery();
        $total = (clone $baseQuery)->count();
        $entries = $baseQuery->when(!config('copper_rod.standalone'), fn ($q) => $q->with('productionSchedule'))->orderByDesc('board_date')->orderByDesc('id')->limit(1000)->get();
        app(LotSequenceService::class)->decorate($entries);

        $original = $this->editingId ? BoardEntry::findOrFail($this->editingId) : null;
        $masters = app(ProductionMasterDataService::class);
        $integration = app(ProductionPlanIntegrationService::class);
        $selectedPlan = config('copper_rod.standalone') ? null : $integration->selected($this->production_schedule_id);
        if ($this->rod_diameter_mm === '' && $selectedPlan?->rod_diameter_mm !== null) {
            $this->rod_diameter_mm = (string) $selectedPlan->rod_diameter_mm;
        }
        $rodContext = $selectedPlan?->rod_diameter_mm ?? $original?->rod_diameter_mm;
        $validPlanDate = \Illuminate\Support\Facades\Validator::make(['date' => $this->planDate], ['date' => 'required|date_format:Y-m-d'])->passes();
        $planDate = $validPlanDate ? $this->planDate : now('Asia/Bangkok')->format('Y-m-d');
        $choices = $productionSchedules = $planWeek = collect();
        if (!config('copper_rod.standalone')) {
            $choices = $integration->schedules()->where('status', 'scheduled')->orderByDesc('planned_on')->limit(200)->get();
            if ($selectedPlan && !$choices->contains('id', $selectedPlan->id)) $choices->push($selectedPlan);
            $productionSchedules = $integration->schedules()->where('planned_on', $planDate)->where('status', 'scheduled')->orderBy('id')->get();
            $weekStart = Carbon::parse($planDate, ProductionRuntimeService::TIMEZONE)->startOfWeek();
            $weekSchedules = ProductionSchedule::with('handoff.sale')->where('status', 'scheduled')
                ->whereBetween('planned_on', [$weekStart->format('Y-m-d'), $weekStart->copy()->addDays(6)->format('Y-m-d')])
                ->get()->groupBy(fn ($schedule) => $schedule->planned_on->format('Y-m-d'));
            $planWeek = collect(range(0, 6))->map(function ($offset) use ($weekStart, $weekSchedules) {
                $day = $weekStart->copy()->addDays($offset)->locale('id');
                $schedules = $weekSchedules->get($day->format('Y-m-d'), collect());
                return ['date' => $day->format('Y-m-d'), 'day' => $day->translatedFormat('D'), 'number' => $day->format('d'),
                    'count' => $schedules->count(), 'targets' => $schedules->groupBy(fn ($s) => $s->handoff->sale->uom_id)->map(fn ($rows) => [
                        'quantity' => (string) $rows->reduce(fn ($sum, $s) => $sum->plus($s->quantity), \Brick\Math\BigDecimal::of(0)),
                        'unit' => $rows->first()->handoff->sale->unit_snapshot['code'],
                    ])];
            });
        }
        $detectedFull = $masters->classify($this->take_up, $this->cooling, $original);

        $start = ProductionRuntimeService::timestamp($this->upcasting_date, $this->upcasting_time);
        if ($start) {
            $slot = ProductionRuntimeService::productionSlot($start);
            $this->board_date = $slot['date'];
            $this->shift = $slot['shift'];
        }
        $calculatedHours = ProductionRuntimeService::hours([
            'upcasting_date' => $this->upcasting_date,
            'upcasting_time' => $this->upcasting_time,
            'actual_pickup_date' => $this->actual_pickup_date,
            'actual_pickup_time' => $this->actual_pickup_time,
            'event_status' => $this->event_status,
        ]);
        $this->operating_hours = $calculatedHours !== null ? (string) $calculatedHours : '';
        $inlineOutputMt = null;
        $weight = $selectedPlan?->rod_weight_ton_per_meter ?? $masters->rodWeight($this->rod_diameter_mm, $original);
        $coefficient = OutputFormulaService::coefficientForRod($this->rod_diameter_mm, $this->take_up_speed, $weight);
        if ($calculatedHours !== null && $detectedFull['servo'] && $coefficient !== null) {
            $inlineOutputMt = $coefficient * $calculatedHours;
        }
        $lotEstimate = LotEstimateService::preview([
            'upcasting_date' => $this->upcasting_date, 'upcasting_time' => $this->upcasting_time,
            'actual_pickup_date' => $this->actual_pickup_date, 'actual_pickup_time' => $this->actual_pickup_time,
            'event_status' => $this->event_status, 'weight_kg' => $this->weight_kg === '' ? null : $this->weight_kg,
            'rod_diameter_mm' => $this->rod_diameter_mm, 'take_up_speed' => $this->take_up_speed,
            'rod_weight_ton_per_meter' => $weight,
        ]);

        return view('modules.produksi.livewire.board-page', [
            'standalone' => (bool) config('copper_rod.standalone'),
            'entries'          => $entries,
            'total'            => $total,
            'shifts'           => ServoInferenceService::SHIFTS,
            'takeupOptions'    => $masters->options('take-ups', $original?->take_up),
            'coilingOptions'   => $masters->options('coilings', $original?->cooling),
            'crystallizerOptions' => $masters->options('crystallizers', $original?->crystallizer),
            'rodDiameters'     => $masters->options('rod-specifications', $rodContext !== null ? (string) $rodContext : null),
            'productionSchedules' => $productionSchedules,
            'planWeek' => $planWeek, 'planDateKey' => $planDate,
            'planDateLabel' => Carbon::parse($planDate)->locale('id')->translatedFormat('l, d F Y'),
            'planToday' => Carbon::now(ProductionRuntimeService::TIMEZONE)->format('Y-m-d'),
            'lotEstimate' => $lotEstimate,
            'productionProgressTotals' => config('copper_rod.standalone') ? collect() : $integration->totals($productionSchedules),
            'scheduleChoices' => $choices, 'selectedProductionPlan' => $selectedPlan,
            'existingLinkedPlan' => (bool) $original?->production_schedule_id,
            'eventStatuses'    => EventStatus::OPTIONS,
            'detectedFull'     => $detectedFull,
            'inlineOutputMt'   => $inlineOutputMt,
            'calculatedHours' => $calculatedHours,
        ]);
    }
}
