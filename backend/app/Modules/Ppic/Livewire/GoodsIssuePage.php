<?php

namespace App\Modules\Ppic\Livewire;

use App\Modules\MasterData\Models\Division;
use App\Modules\Ppic\Models\InventoryIssue;
use App\Modules\Ppic\Services\InventoryIssueService;
use App\Modules\Ppic\Services\InventoryStockService;
use App\Modules\Purchasing\Services\ProcurementService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Barang Keluar — irris.next')]
class GoodsIssuePage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $purpose = '';

    public string $division = '';

    public string $from = '';

    public string $until = '';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $selectedId = null;

    #[Locked]
    public bool $showForm = false;

    #[Locked]
    public string $draftToken = '';

    public array $form = [];

    public string $reversal_reason = '';

    public function boot(): void
    {
        Gate::authorize('ppic.ic.issues.view');
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'status', 'purpose', 'division', 'from', 'until'], true)) {
            $this->resetPage();
        }
    }

    public function openCreate(): void
    {
        Gate::authorize('ppic.ic.issues.create');
        $this->editingId = null;
        $this->selectedId = null;
        $this->draftToken = (string) Str::uuid();
        $this->form = ['issued_on' => now()->format('Y-m-d'), 'purpose' => 'maintenance', 'division_id' => '',
            'recipient' => '', 'asset' => '', 'request_reference' => '', 'notes' => '',
            'lines' => [['product_id' => '', 'quantity' => '']]];
        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function openEdit(int $id): void
    {
        Gate::authorize('ppic.ic.issues.create');
        $issue = InventoryIssue::with('lines')->findOrFail($id);
        abort_unless($issue->status === 'draft', 403);
        $this->openCreate();
        $this->editingId = $id;
        $this->draftToken = $issue->draft_token;
        $this->form = $issue->only(['purpose', 'division_id', 'recipient', 'asset', 'request_reference', 'notes']);
        $this->form['issued_on'] = $issue->issued_on->format('Y-m-d');
        $this->form['lines'] = $issue->lines->map(fn ($line) => ['product_id' => (string) $line->product_id, 'quantity' => $line->quantity])->all();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->draftToken = '';
        $this->form = [];
        $this->resetErrorBag();
    }

    public function addLine(): void
    {
        Gate::authorize('ppic.ic.issues.create');
        if ($this->showForm && count($this->form['lines'] ?? []) < 50) {
            $this->form['lines'][] = ['product_id' => '', 'quantity' => ''];
        }
    }

    public function removeLine(int $index): void
    {
        Gate::authorize('ppic.ic.issues.create');
        if ($this->showForm && isset($this->form['lines'][$index]) && count($this->form['lines']) > 1) {
            unset($this->form['lines'][$index]);
            $this->form['lines'] = array_values($this->form['lines']);
            $this->resetErrorBag();
        }
    }

    public function save(InventoryIssueService $service): void
    {
        Gate::authorize('ppic.ic.issues.create');
        if (! $this->showForm) {
            return;
        }
        $issue = $service->save($this->form, $this->draftToken, $this->editingId);
        $this->closeForm();
        $this->viewIssue($issue->id);
        $this->resetPage();
        $this->dispatch('notify', ['type' => 'success', 'message' => $issue->reference.' disimpan sebagai draft. Stok belum berubah.']);
    }

    public function viewIssue(int $id): void
    {
        Gate::authorize('ppic.ic.issues.view');
        InventoryIssue::findOrFail($id);
        if ($this->showForm) {
            $this->closeForm();
        }
        $this->selectedId = $id;
        $this->reversal_reason = '';
        $this->resetErrorBag();
    }

    public function closeDetail(): void
    {
        $this->selectedId = null;
        $this->reversal_reason = '';
        $this->resetErrorBag();
    }

    public function confirm(InventoryIssueService $service): void
    {
        Gate::authorize('ppic.ic.issues.confirm');
        if (! $this->selectedId) {
            return;
        }
        $service->confirm($this->selectedId);
        $this->resetErrorBag();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Pengeluaran dikonfirmasi. Stok gudang sudah dikurangi.']);
    }

    public function cancel(InventoryIssueService $service): void
    {
        Gate::authorize('ppic.ic.issues.create');
        if (! $this->selectedId) {
            return;
        }
        $service->cancel($this->selectedId);
        $this->resetErrorBag();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Draft dibatalkan. Stok tidak berubah.']);
    }

    public function reverse(InventoryIssueService $service): void
    {
        Gate::authorize('ppic.ic.issues.reverse');
        if (! $this->selectedId) {
            return;
        }
        $service->reverse($this->selectedId, $this->reversal_reason);
        $this->resetErrorBag();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Pengeluaran dibalikkan. Stok dikembalikan dan riwayat tersimpan.']);
    }

    public function render()
    {
        if ($this->showForm && ! Gate::allows('ppic.ic.issues.create')) {
            $this->closeForm();
        }
        Validator::make($this->only(['search', 'status', 'purpose', 'division', 'from', 'until']), ['search' => 'string|max:100', 'status' => 'in:,draft,confirmed,cancelled,reversed',
            'purpose' => 'in:,maintenance,operational', 'division' => 'nullable|integer',
            'from' => 'nullable|date_format:Y-m-d', 'until' => 'nullable|date_format:Y-m-d'.($this->from !== '' ? '|after_or_equal:from' : '')])->validate();
        $query = InventoryIssue::query()->with('creator')->withCount('lines');
        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $number = preg_replace('/^BK-0*/i', '', trim($this->search));
            $query->where(fn ($q) => $q->where('request_reference', 'like', $term)->orWhere('recipient', 'like', $term)
                ->orWhere('division_name', 'like', $term)->when(ctype_digit($number), fn ($q) => $q->orWhere('id', (int) $number)));
        }
        if ($this->status !== '') {
            $query->where('status', $this->status);
        }
        if ($this->purpose !== '') {
            $query->where('purpose', $this->purpose);
        }
        if ($this->division !== '') {
            $query->where('division_id', $this->division);
        }
        if ($this->from !== '') {
            $query->whereDate('issued_on', '>=', $this->from);
        }
        if ($this->until !== '') {
            $query->whereDate('issued_on', '<=', $this->until);
        }

        return view('modules.ppic.livewire.goods-issue-page', [
            'rows' => $query->latest('id')->paginate(15),
            'divisions' => Division::orderBy('name')->get(),
            'items' => $this->showForm ? app(InventoryStockService::class)->withBalances(app(ProcurementService::class)->materials())->get() : collect(),
            'detail' => $this->selectedId ? InventoryIssue::with(['lines', 'creator', 'updater', 'confirmer', 'canceller', 'reverser'])->find($this->selectedId) : null,
        ]);
    }
}
