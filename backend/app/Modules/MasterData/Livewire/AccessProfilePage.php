<?php

namespace App\Modules\MasterData\Livewire;

use App\Modules\MasterData\Models\AccessProfile;
use App\Modules\MasterData\Models\Division;
use App\Modules\MasterData\Models\JobTitle;
use App\Modules\MasterData\Services\AccessProfileService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Hak Akses - irris.next')]
class AccessProfilePage extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public bool $showForm = false;

    public array $form = [];

    public function boot(): void
    {
        Gate::authorize('access-master-data');
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
        $this->editingId = null;
        $this->form = ['job_title_id' => '', 'division_id' => '', 'is_active' => true, 'permissions' => [], 'configured_modules' => ['marketing', 'ppic_ic', 'purchasing']];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $profile = AccessProfile::findOrFail($id);
        $this->openCreate();
        $this->editingId = $profile->id;
        $this->form = $profile->only(['job_title_id', 'division_id', 'is_active']);
        $this->form['permissions'] = $profile->permissions ?? [];
        $this->form['configured_modules'] = $profile->configured_modules ?? ['marketing'];
    }

    public function updatedForm(mixed $value, ?string $key = null): void
    {
        if ($key === 'permissions' || str_starts_with($key ?? '', 'permissions.')) {
            $selected = $this->form['permissions'] ?? [];
            if (is_array($selected)) {
                $this->form['permissions'] = \App\Services\AccessPermissionService::normalize($selected);
                if (collect($selected)->contains(fn ($permission) => is_string($permission) && str_starts_with($permission, 'purchasing.'))) {
                    $this->form['configured_modules'] = array_values(array_unique([...($this->form['configured_modules'] ?? []), 'purchasing']));
                }
                if (collect($selected)->contains(fn ($permission) => is_string($permission) && str_starts_with($permission, 'ppic.ic.'))) {
                    $this->form['configured_modules'] = array_values(array_unique([...($this->form['configured_modules'] ?? []), 'ppic_ic']));
                }
            }
        }
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->form = [];
        $this->resetErrorBag();
    }

    public function save(AccessProfileService $service): void
    {
        if (! $this->showForm) {
            return;
        }
        try {
            $service->save($this->form, $this->editingId);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn ($messages, $key) => ['form.'.$key => $messages])->all());
        }
        $this->closeForm();
        $this->resetPage();
        session()->flash('access-profile-saved', 'Pengaturan jabatan dan divisi berhasil disimpan.');
    }

    public function render()
    {
        $query = AccessProfile::with(['jobTitle', 'division']);
        if (in_array($this->status, ['active', 'inactive'], true)) {
            $query->where('is_active', $this->status === 'active');
        }
        if (trim($this->search) !== '') {
            $term = '%'.mb_strtolower(trim($this->search)).'%';
            $matches = fn ($q) => $q->where(fn ($fields) => $fields
                ->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term]));
            $query->where(fn ($q) => $q->whereHas('jobTitle', $matches)->orWhereHas('division', $matches));
        }
        $existing = $this->editingId ? AccessProfile::find($this->editingId) : null;

        return view('modules.master-data.livewire.access-profile-page', [
            'rows' => $query->orderByDesc('id')->paginate(15),
            'permissionModules' => config('access_permissions'),
            'jobs' => JobTitle::where(fn ($q) => $q->where('is_active', true)
                ->when($existing, fn ($q) => $q->orWhere('id', $existing->job_title_id)))->orderBy('name')->get(),
            'divisions' => Division::where(fn ($q) => $q->where('is_active', true)
                ->when($existing, fn ($q) => $q->orWhere('id', $existing->division_id)))->orderBy('name')->get(),
        ]);
    }
}