<?php

namespace App\Modules\MasterData\Livewire;

use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Unit;
use App\Modules\MasterData\Models\ProductionReference;
use App\Modules\MasterData\Services\MasterDataService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Gate;

#[Layout('components.layouts.app')]
#[Title('Master Data - irris.next')]
class MasterDataPage extends Component
{
    use WithPagination;
    use ConfirmsMasterDeletion;

    #[Locked]
    public string $tab = 'items';

    public string $search = '';

    #[Locked]
    public string $modal = '';

    #[Locked]
    public ?int $editingId = null;

    public array $form = [];

    public function boot(): void
    {
        Gate::authorize('access-master-data');
    }

    public function mount(string $section = 'items'): void
    {
        abort_unless(in_array($section, ['items', 'categories', 'units'], true), 404);
        $this->tab = $section;
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['items', 'categories', 'units'], true)) {
            return;
        }
        $this->tab = $tab;
        $this->search = '';
        $this->resetPage();
        $this->closeModal();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openForm(string $type, ?int $id = null): void
    {
        abort_unless(in_array($type, ['item', 'category', 'unit'], true), 404);
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->modal = $type;
        $this->form = ['code' => '', 'name' => '', 'is_active' => true] + match ($type) {
            'category' => ['parent_id' => '', 'description' => ''],
            'unit' => ['related_uom_id' => '', 'factor' => '1'],
            'item' => ['category_id' => '', 'uom_id' => '', 'description' => '', 'rod_specification_id' => ''],
        };
        if (! $id) {
            return;
        }
        $model = match ($type) {
            'category' => Category::findOrFail($id), 'unit' => Unit::findOrFail($id), 'item' => Item::findOrFail($id),
        };
        foreach (array_keys($this->form) as $key) {
            $this->form[$key] = $model->$key ?? '';
        }
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->editingId = null;
        $this->form = [];
        $this->resetErrorBag();
    }

    public function save(MasterDataService $service): void
    {
        if (! $this->modal) {
            return;
        }
        try {
            match ($this->modal) {
                'category' => $service->saveCategory($this->form, $this->editingId),
                'unit' => $service->saveUnit($this->form, $this->editingId),
                'item' => $service->saveItem($this->form, $this->editingId),
            };
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => ['form.'.$field => $messages])->all());
        }
        $this->closeModal();
        $this->resetPage();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Master data tersimpan.']);
    }

    public function render()
    {
        $query = match ($this->tab) {
            'items' => Item::with(['category', 'unit', 'rodSpecification']), 'categories' => Category::with('parent'), 'units' => Unit::with('related'),
        };
        if (trim($this->search) !== '') {
            $term = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term]));
        }

        return view('modules.master-data.livewire.master-data-page', [
            'rows' => $query->orderBy('code')->paginate(15), 'categories' => Category::with('parent')->orderBy('code')->get(),
            'units' => Unit::orderBy('code')->get(),
            'rodSpecifications' => ProductionReference::where('section', 'rod-specifications')
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', (int) ($this->form['rod_specification_id'] ?? 0)))
                ->orderBy('diameter_mm')->get(),
        ]);
    }

    protected function onMasterDeleted(): void
    {
        $this->closeModal();
        $this->resetPage();
    }
}
