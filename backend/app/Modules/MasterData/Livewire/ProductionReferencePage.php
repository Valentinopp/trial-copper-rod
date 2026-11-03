<?php

namespace App\Modules\MasterData\Livewire;

use App\Modules\MasterData\Models\ProductionReference;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Models\CopperBreakEntry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class ProductionReferencePage extends Component
{
    use WithPagination;

    #[Locked]
    public string $section = 'take-ups';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public bool $showForm = false;

    public string $search = '';

    public string $status = '';

    public array $form = [];

    public function boot(): void
    {
        Gate::authorize('access-master-data');
    }

    public function mount(string $section = 'take-ups'): void
    {
        abort_unless(isset(ProductionReference::SECTIONS[$section]), 404);
        $this->section = $section;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->showForm = true;
        $this->form = ['code' => '', 'name' => '', 'servo' => '', 'diameter_mm' => '', 'weight_ton_per_meter' => '', 'description' => '', 'is_active' => true];
    }

    public function openEdit(int $id): void
    {
        $row = ProductionReference::where('section', $this->section)->findOrFail($id);
        $this->openCreate();
        $this->editingId = $row->id;
        foreach (array_keys($this->form) as $field) {
            $this->form[$field] = $row->$field ?? '';
        }
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->form = [];
        $this->resetErrorBag();
    }

    public function save(): void
    {
        if (! $this->showForm) {
            return;
        }

        foreach (['code', 'name', 'description'] as $field) {
            $this->form[$field] = trim((string) ($this->form[$field] ?? ''));
        }
        $this->form['code'] = mb_strtoupper($this->form['code']);
        $unique = fn (string $column) => Rule::unique('master_data_production_references', $column)
            ->where('section', $this->section)->ignore($this->editingId);
        $rules = [
            'form.code' => ['required', 'string', 'max:255', $unique('code')],
            'form.name' => ['required', 'string', 'max:255', $unique('name')],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.is_active' => ['required', 'boolean'],
        ];
        if (in_array($this->section, ['take-ups', 'coilings'], true)) {
            $rules['form.servo'] = ['required', Rule::in(['X', 'Y'])];
        }
        if ($this->section === 'rod-specifications') {
            $rules['form.diameter_mm'] = ['required', 'numeric', 'gt:0', 'max:999.9', 'decimal:0,1', $unique('diameter_mm')];
            $rules['form.weight_ton_per_meter'] = ['required', 'numeric', 'gt:0', 'max:99', 'decimal:0,10'];
        }
        $data = $this->validate($rules, [], [
            'form.code' => 'kode', 'form.name' => 'nama', 'form.servo' => 'servo',
            'form.diameter_mm' => 'diameter', 'form.weight_ton_per_meter' => 'bobot rod per meter',
        ])['form'];
        // A historical selection must not acquire a different meaning when a code/name is reused.
        [$model, $field] = match ($this->section) {
            'take-ups' => [BoardEntry::class, 'take_up'],
            'coilings' => [BoardEntry::class, 'cooling'],
            'crystallizers' => [BoardEntry::class, 'crystallizer'],
            'rod-specifications' => [BoardEntry::class, 'rod_diameter_mm'],
            'break-events' => [CopperBreakEntry::class, 'event_at_break'],
            'break-conditions' => [CopperBreakEntry::class, 'event_after_break'],
            'break-categories' => [CopperBreakEntry::class, 'category'],
        };
        $column = ProductionReference::valueColumn($this->section);
        $history = $model::where($field, $data[$column]);
        if ($this->editingId) {
            $history->where(fn ($q) => $q->whereNull($field.'_master_id')->orWhere($field.'_master_id', '!=', $this->editingId));
        }
        if ($history->exists()) {
            throw ValidationException::withMessages(['form.'.$column => 'Nilai ini sudah digunakan oleh data lain dalam riwayat produksi. Gunakan nilai yang berbeda.']);
        }
        $row = $this->editingId
            ? ProductionReference::where('section', $this->section)->findOrFail($this->editingId)
            : new ProductionReference(['section' => $this->section]);
        $row->fill($data)->save();
        $this->closeForm();
        $this->resetPage();
        session()->flash('production-master-saved', 'Data master berhasil disimpan.');
    }

    public function render()
    {
        $query = ProductionReference::where('section', $this->section);
        if ($this->status !== '') {
            $query->where('is_active', $this->status === 'active');
        }
        if (trim($this->search) !== '') {
            $term = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term]));
        }
        $title = ProductionReference::SECTIONS[$this->section];

        return view('modules.master-data.livewire.production-reference-page', [
            'rows' => $query->orderBy('code')->paginate(15), 'title' => $title,
            'hasServo' => in_array($this->section, ['take-ups', 'coilings'], true),
            'hasSpecification' => $this->section === 'rod-specifications',
        ])->title('Master '.$title.' - irris.next');
    }
}
