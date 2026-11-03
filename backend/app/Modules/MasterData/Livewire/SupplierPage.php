<?php

namespace App\Modules\MasterData\Livewire;

use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Supplier;
use App\Modules\MasterData\Services\PartnerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Master Supplier - irris.next')]
class SupplierPage extends PartnerPage
{
    public array $selectedItemIds = [];

    public string $itemSearch = '';

    public string $selectedSearch = '';

    protected function partnerClass(): string
    {
        return Supplier::class;
    }

    protected function kind(): string
    {
        return 'supplier';
    }

    protected function prepareForm(?Model $partner): void
    {
        $this->selectedItemIds = $partner?->items()->pluck('master_data_items.id')->all() ?? [];
        $this->itemSearch = $this->selectedSearch = '';
        $this->resetPage('catalogPage');
        $this->resetPage('selectedPage');
    }

    public function updatedItemSearch(): void
    {
        $this->resetPage('catalogPage');
    }

    public function updatedSelectedSearch(): void
    {
        $this->resetPage('selectedPage');
    }

    public function toggleItem(int $id): void
    {
        if (! $this->showForm) {
            return;
        }
        $ids = $this->displayItemIds();
        if (in_array($id, $ids, true)) {
            $this->selectedItemIds = array_values(array_filter($ids, fn ($value) => $value !== $id));
        } elseif (Item::whereKey($id)->where('is_active', true)->exists()) {
            $this->selectedItemIds[] = $id;
        } else {
            $this->addError('item_ids', 'Pilih barang aktif dari Master Barang.');

            return;
        }
        $this->resetErrorBag('item_ids');
        $this->resetPage('catalogPage');
        $this->resetPage('selectedPage');
    }

    protected function persist(PartnerService $service): void
    {
        $service->saveSupplier(array_merge($this->form, ['item_ids' => $this->selectedItemIds]), $this->editingId);
    }

    protected function listQuery(): Builder
    {
        return Supplier::withCount('items');
    }

    protected function extraViewData(): array
    {
        if (! $this->showForm) {
            return [];
        }
        $ids = $this->displayItemIds();
        $catalog = Item::with(['category', 'unit'])->where('is_active', true)->whereNotIn('id', $ids);
        $selected = Item::with(['category', 'unit'])->whereIn('id', $ids);
        foreach ([[$catalog, $this->itemSearch], [$selected, $this->selectedSearch]] as [$query, $search]) {
            if (trim($search) !== '') {
                $term = '%'.mb_strtolower(trim($search)).'%';
                $query->where(fn ($q) => $q->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term]));
            }
        }

        return [
            'catalogItems' => $catalog->orderBy('code')->paginate(8, ['*'], 'catalogPage'),
            'selectedItems' => $selected->orderBy('code')->paginate(8, ['*'], 'selectedPage'),
        ];
    }

    private function displayItemIds(): array
    {
        // Render only safe numeric IDs. Keep the submitted array unchanged so
        // PartnerService can report invalid/duplicate selections on save.
        $ids = [];
        foreach ($this->selectedItemIds as $value) {
            $id = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
            if ($id !== false) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
