<?php

namespace App\Modules\MasterData\Livewire;

use App\Modules\MasterData\Models\Bom;
use App\Modules\MasterData\Services\BomService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Master BoM - irris.next')]
class BomPage extends Component
{
    use WithPagination;

    public string $search = '';

    #[Locked]
    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public array $form = [];

    public function boot(): void
    {
        Gate::authorize('access-master-data');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openForm(?int $id = null): void
    {
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->form = ['code' => '', 'name' => '', 'product_id' => '', 'output_quantity' => '1', 'notes' => '',
            'is_active' => true, 'lines' => [['material_id' => '', 'quantity' => '']]];
        if ($id) {
            $bom = Bom::with('lines')->findOrFail($id);
            $this->form = $bom->only(['code', 'name', 'product_id', 'output_quantity', 'notes', 'is_active']);
            $this->form['lines'] = $bom->lines->map(fn ($line) => $line->only(['material_id', 'quantity']))->all();
        }
        $this->showForm = true;
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->form = [];
        $this->resetErrorBag();
    }

    public function addLine(): void
    {
        if ($this->showForm && count($this->form['lines']) < 100) {
            $this->form['lines'][] = ['material_id' => '', 'quantity' => ''];
        }
    }

    public function removeLine(int $index): void
    {
        if ($this->showForm && count($this->form['lines']) > 1 && array_key_exists($index, $this->form['lines'])) {
            unset($this->form['lines'][$index]);
            $this->form['lines'] = array_values($this->form['lines']);
            $this->resetErrorBag();
        }
    }

    public function save(BomService $service): void
    {
        if (! $this->showForm) {
            return;
        }
        try {
            $service->save($this->form, $this->editingId);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn ($messages, $field) => ['form.'.$field => $messages])->all());
        }
        $this->closeModal();
        $this->resetPage();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Master BoM tersimpan.']);
    }

    public function render()
    {
        $query = Bom::with('product.unit')->withCount('lines');
        if (trim($this->search) !== '') {
            $term = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(code) LIKE ?', [$term])
                ->orWhereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereHas('product', fn ($product) => $product->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term])));
        }
        $service = app(BomService::class);

        return view('modules.master-data.livewire.bom-page', [
            'rows' => $query->orderBy('code')->paginate(15),
            'products' => $this->showForm ? $service->items('produk')->get() : collect(),
            'materials' => $this->showForm ? $service->items('material')->get() : collect(),
        ]);
    }
}
