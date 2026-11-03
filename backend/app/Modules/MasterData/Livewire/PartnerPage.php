<?php

namespace App\Modules\MasterData\Livewire;

use App\Modules\MasterData\Services\PartnerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Gate;

abstract class PartnerPage extends Component
{
    use WithPagination;
    use ConfirmsMasterDeletion;

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

    abstract protected function partnerClass(): string;

    abstract protected function kind(): string;

    abstract protected function persist(PartnerService $service): void;

    public function updatedSearch(): void
    {
        $this->resetPage('partnersPage');
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form = ['code' => '', 'name' => '', 'contact_name' => '', 'phone' => '', 'email' => '', 'address' => '', 'notes' => '', 'is_active' => true];
        $this->prepareForm(null);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $partner = ($this->partnerClass())::findOrFail($id);
        $this->openCreate();
        $this->editingId = $id;
        foreach (array_keys($this->form) as $field) {
            $this->form[$field] = $partner->$field ?? '';
        }
        $this->prepareForm($partner);
    }

    protected function prepareForm(?Model $partner): void {}

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->form = [];
        $this->prepareForm(null);
        $this->resetErrorBag();
    }

    public function save(PartnerService $service): void
    {
        if (! $this->showForm) {
            return;
        }
        try {
            $this->persist($service);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => [
                str_starts_with($key, 'item_ids') ? $key : 'form.'.$key => $messages,
            ])->all());
        }
        $this->closeForm();
        $this->resetPage('partnersPage');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Master '.$this->kind().' tersimpan.']);
    }

    protected function listQuery(): Builder
    {
        return ($this->partnerClass())::query();
    }

    protected function extraViewData(): array
    {
        return [];
    }

    public function render()
    {
        $query = $this->listQuery();
        if (trim($this->search) !== '') {
            $term = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term]));
        }

        return view('modules.master-data.livewire.partners-page', [
            'kind' => $this->kind(), 'rows' => $query->orderBy('code')->paginate(15, ['*'], 'partnersPage'),
        ] + $this->extraViewData());
    }

    protected function deletionKinds(): array
    {
        return [$this->kind()];
    }

    protected function onMasterDeleted(): void
    {
        $this->closeForm();
        $this->resetPage('partnersPage');
    }
}
