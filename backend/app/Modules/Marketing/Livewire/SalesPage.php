<?php

namespace App\Modules\Marketing\Livewire;

use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\Marketing\Services\SalesService;
use App\Modules\MasterData\Models\Customer;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Penjualan - irris.next')]
class SalesPage extends Component
{
    use WithPagination;

    #[Locked]
    public bool $showForm = false;

    #[Locked]
    public string $submissionToken = '';

    public string $customer_id = '';

    public string $product_id = '';

    public string $quantity = '';

    public string $sold_on = '';

    public string $ship_by = '';

    public string $notes = '';

    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('marketing.sales.view');
    }

    public function openSale(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('marketing.sales.create');
        $this->reset('customer_id', 'product_id', 'quantity', 'ship_by', 'notes');
        $this->sold_on = now()->format('Y-m-d');
        $this->submissionToken = (string) Str::uuid();
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function closeSale(): void
    {
        $this->showForm = false;
        $this->resetErrorBag();
    }

    public function saveSale(SalesService $service): void
    {
        \Illuminate\Support\Facades\Gate::authorize('marketing.sales.create');
        if (! $this->showForm) {
            return;
        }
        $sale = $service->record($this->only([
            'customer_id', 'product_id', 'quantity', 'sold_on', 'ship_by', 'notes',
        ]), $this->submissionToken);
        $this->closeSale();
        $this->resetPage('salesPage');
        $this->dispatch('notify', ['type' => 'success', 'message' => $sale->reference.' tercatat sebagai penjualan final.']);
    }

    public function render()
    {
        $products = $this->showForm ? app(SalesService::class)->products()->get() : collect();

        return view('modules.marketing.livewire.sales-page', [
            'sales' => SalesOrder::latest('id')->paginate(15, ['*'], 'salesPage'),
            'products' => $products,
            'selectedProduct' => $products->firstWhere('id', (int) $this->product_id),
            'customers' => $this->showForm ? Customer::where('is_active', true)->orderBy('code')->get() : collect(),
        ]);
    }
}
