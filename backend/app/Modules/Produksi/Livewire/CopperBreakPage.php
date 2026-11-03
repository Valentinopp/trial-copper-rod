<?php

namespace App\Modules\Produksi\Livewire;

use App\Modules\Produksi\Enums\EventStatus;
use App\Modules\Produksi\Models\CopperBreakEntry;
use App\Modules\Produksi\Services\CopperBreakService;
use App\Modules\Produksi\Services\ProductionMasterDataService;
use App\Modules\Produksi\Services\ProductionSupervisorService;
use App\Modules\Produksi\Services\WipFormatter;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Monitoring Copper Putus — WIP Board')]
class CopperBreakPage extends Component
{
    public string $search = '';
    public string $startDate = '';
    public string $endDate = '';

    // Form State
    public ?int $editingId = null;
    public bool $showCopperForm = false;
    public string $board_entry_id = '';
    public string $supervisor_user_id = '';
    public string $event_at_break = '';
    public string $event_after_break = '';
    public string $category = '';
    public string $frequency = '';
    public string $remarks = '';

    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('access-produksi');
    }

    public function mount(): void
    {
        $this->endDate = Carbon::now('Asia/Jakarta')->format('Y-m-d');
    }

    public function updatedBoardEntryId($value): void
    {
        if (!$value) {
            $this->editingId = null;
            return;
        }

        $entries = $this->getData()['items'];
        $source = collect($entries)->first(fn($e) => (string) $e['board_entry_id'] === (string) $value);

        if ($source) {
            $this->editingId = !empty($source['detail_id']) ? (int) $source['board_entry_id'] : null;
            $this->supervisor_user_id = $source['supervisor_user_id'] ? (string) $source['supervisor_user_id'] : (!empty($source['supervisor']) ? 'legacy' : '');
            $this->event_at_break = $source['event_at_break'] ?? '';
            $this->event_after_break = $source['event_after_break'] ?? '';
            $this->category = $source['category'] ?? '';
            $this->frequency = isset($source['frequency']) && $source['frequency'] !== null ? (string) $source['frequency'] : '';
            $this->remarks = $source['remarks'] ?? '';
        } else {
            $this->editingId = null;
        }
    }

    public function openCopperForm(): void
    {
        $this->resetCopperForm();
        $this->showCopperForm = true;
    }

    public function resetCopperForm(): void
    {
        $this->editingId = null;
        $this->board_entry_id = '';
        $this->supervisor_user_id = '';
        $this->event_at_break = '';
        $this->event_after_break = '';
        $this->category = '';
        $this->frequency = '';
        $this->remarks = '';
        $this->resetErrorBag();
    }

    public function resetFilters(): void
    {
        $this->startDate = '';
        $this->endDate = Carbon::now('Asia/Jakarta')->format('Y-m-d');
        $this->search = '';
    }

    public function clearSearch(): void
    {
        $this->search = '';
    }

    public function refreshEntries(): void
    {
    }

    public function edit(int $boardEntryId): void
    {
        $entries = $this->getData()['items'];
        $entry = collect($entries)->firstWhere('board_entry_id', $boardEntryId);
        if (!$entry) {
            return;
        }

        $this->editingId = $boardEntryId;
        $this->board_entry_id = (string) $boardEntryId;
        $this->supervisor_user_id = $entry['supervisor_user_id'] ? (string) $entry['supervisor_user_id'] : (!empty($entry['supervisor']) ? 'legacy' : '');
        $this->event_at_break = $entry['event_at_break'] ?? '';
        $this->event_after_break = $entry['event_after_break'] ?? '';
        $this->category = $entry['category'] ?? '';
        $this->frequency = isset($entry['frequency']) && $entry['frequency'] !== null ? (string) $entry['frequency'] : '';
        $this->remarks = $entry['remarks'] ?? '';

        $this->resetErrorBag();
        $this->showCopperForm = true;
    }

    public function save(): void
    {
        if ($this->board_entry_id === '') {
            $this->dispatch('notify', [
                'message' => 'Pilih kejadian dari Papan Digital terlebih dahulu.',
                'type'    => 'error',
            ]);
            return;
        }

        $isEditing = $this->editingId !== null;

        try {
            app(CopperBreakService::class)->upsertDetail((int) $this->board_entry_id, [
                'supervisor_user_id' => $this->supervisor_user_id,
                'event_at_break'    => $this->event_at_break,
                'event_after_break' => $this->event_after_break,
                'category'          => $this->category,
                'frequency'         => $this->frequency,
                'remarks'           => $this->remarks,
            ]);

            $this->dispatch('notify', [
                'title'   => $isEditing ? 'Perubahan berhasil disimpan' : 'Detail berhasil disimpan',
                'message' => $isEditing
                    ? 'Perubahan detail sudah tersimpan pada kejadian yang dipilih.'
                    : 'Detail kejadian tersimpan dan terhubung ke Papan Digital.',
                'type'    => 'success',
            ]);

            $this->resetCopperForm();
            $this->showCopperForm = false;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->setErrorBag($e->errors());
            $this->dispatch('notify', ['message' => collect($e->errors())->flatten()->first(), 'type' => 'error']);
        } catch (\Throwable $e) {
            $this->dispatch('notify', [
                'message' => $e->getMessage(),
                'type'    => 'error',
            ]);
        }
    }

    public function clearDetails(int $boardEntryId): void
    {
        CopperBreakEntry::where('board_entry_id', $boardEntryId)->delete();

        if ((string) $this->board_entry_id === (string) $boardEntryId) {
            $this->resetCopperForm();
        }

        $this->dispatch('notify', [
            'title'   => 'Detail dikosongkan',
            'message' => 'Detail tambahan dihapus. Catatan sumber tetap tersimpan di Papan Digital.',
            'type'    => 'success',
        ]);
    }

    protected function getData(): array
    {
        if ($this->startDate !== '' && $this->endDate !== '' && $this->endDate < $this->startDate) {
            return ['items' => [], 'total' => 0];
        }

        return app(CopperBreakService::class)->listEntries(
            $this->startDate !== '' ? $this->startDate : null,
            $this->endDate !== '' ? $this->endDate : null,
            $this->search
        );
    }

    public function exportExcel(): void
    {
        $entries = $this->getData()['items'];
        if (empty($entries)) {
            return;
        }

        $headers = [
            'No', 'Tanggal papan', 'Jam kejadian', 'Shift', 'SPV', 'No Cryst.', 'LOT',
            'Take Up', 'Coiling', 'Status papan', 'Lifetime (jam)',
            'Kejadian saat putus', 'Kejadian setelah putus', 'Kategori',
            'Berat (kg)', 'Frekuensi', 'Keterangan detail',
        ];

        $rows = [];
        foreach ($entries as $idx => $e) {
            $rows[] = [
                $idx + 1,
                $e['event_date'],
                $e['event_time'],
                $e['shift'] ? "Shift {$e['shift']}" : '',
                $e['supervisor'],
                $e['crystallizer'],
                $e['lot'],
                $e['take_up'],
                $e['cooling'],
                EventStatus::label($e['event_status']),
                $e['lifetime_hours'],
                $e['event_at_break'],
                $e['event_after_break'],
                $e['category'],
                $e['weight_kg'],
                $e['frequency'],
                $e['remarks'],
            ];
        }

        $s = $this->startDate ?: 'awal';
        $e = $this->endDate ?: 'terbaru';

        $this->dispatch('export-excel', [
            'filename'  => "monitoring-copper-putus-{$s}-{$e}.xlsx",
            'sheetName' => 'Copper Putus',
            'headers'   => $headers,
            'rows'      => $rows,
        ]);
    }

    public function exportPNG(): void
    {
        $entries = $this->getData()['items'];
        if (empty($entries)) {
            return;
        }

        $headers = [
            'No', 'Tanggal', 'Jam', 'Shift', 'SPV', 'Crystallizer', 'LOT',
            'Take Up', 'Coiling', 'Status Papan', 'Lifetime (jam)',
            'Kejadian Saat Putus', 'Kejadian Setelah Putus', 'Kategori',
            'Berat (kg)', 'Frekuensi', 'Keterangan',
        ];

        $rows = [];
        foreach ($entries as $idx => $e) {
            $rows[] = [
                $idx + 1,
                WipFormatter::prettyDate($e['event_date']),
                $e['event_time'] ?: '—',
                $e['shift'] ? "Shift {$e['shift']}" : '—',
                $e['supervisor'] ?: '—',
                $e['crystallizer'] ?: '—',
                $e['lot'] ?: '—',
                $e['take_up'] ?: '—',
                $e['cooling'] ?: '—',
                EventStatus::label($e['event_status']),
                $e['lifetime_hours'] === null ? '—' : WipFormatter::number($e['lifetime_hours'], 2),
                $e['event_at_break'] ?: '—',
                $e['event_after_break'] ?: '—',
                $e['category'] ?: '—',
                $e['weight_kg'] === null ? '—' : WipFormatter::number($e['weight_kg'], 2),
                $e['frequency'] === null ? '—' : WipFormatter::number($e['frequency'], 0),
                $e['remarks'] ?: '—',
            ];
        }

        $s = $this->startDate ?: 'awal';
        $e = $this->endDate ?: 'terbaru';
        $period = ($this->startDate ? WipFormatter::prettyDate($this->startDate) : 'Awal data') . ' – ' . ($this->endDate ? WipFormatter::prettyDate($this->endDate) : 'Terbaru');
        if (trim($this->search) !== '') {
            $period .= ' · Pencarian: ' . trim($this->search);
        }

        $this->dispatch('export-png', [
            'filename' => "monitoring-copper-putus-{$s}-{$e}.png",
            'title'    => 'Monitoring Copper Putus',
            'period'   => $period,
            'headers'  => $headers,
            'rows'     => $rows,
        ]);
    }

    public function render()
    {
        $data = $this->getData();
        $entries = $data['items'];
        $total = $data['total'];

        $completedCount = count(array_filter($entries, fn($e) => !empty($e['detail_complete'])));
        $pendingCount = max(0, $total - $completedCount);
        $totalWeight = array_reduce($entries, fn($sum, $item) => $sum + (float) ($item['weight_kg'] ?? 0), 0.0);
        $crystalCount = count(array_unique(array_filter(array_map(fn($i) => trim((string) ($i['crystallizer'] ?? '')), $entries))));

        $eventSummary = CopperBreakService::countGroups($entries, 'event_at_break');
        $categorySummary = CopperBreakService::countGroups($entries, 'category');

        $selectedSource = null;
        if ($this->board_entry_id !== '') {
            $selectedSource = collect($entries)->first(fn($e) => (string) $e['board_entry_id'] === (string) $this->board_entry_id);
        }

        $original = $this->board_entry_id !== '' ? CopperBreakEntry::where('board_entry_id', $this->board_entry_id)->first() : null;
        $masters = app(ProductionMasterDataService::class);
        return view('modules.produksi.livewire.copper-break-page', [
            'entries'             => $entries,
            'total'               => $total,
            'completedCount'      => $completedCount,
            'pendingCount'        => $pendingCount,
            'totalWeight'         => $totalWeight,
            'crystalCount'        => $crystalCount,
            'eventSummary'        => $eventSummary,
            'categorySummary'     => $categorySummary,
            'selectedSource'      => $selectedSource,
            'eventSuggestions'    => $masters->options('break-events', $original?->event_at_break),
            'afterSuggestions'    => $masters->options('break-conditions', $original?->event_after_break),
            'categorySuggestions' => $masters->options('break-categories', $original?->category),
            'supervisors'         => app(ProductionSupervisorService::class)->options($original),
        ]);
    }
}
