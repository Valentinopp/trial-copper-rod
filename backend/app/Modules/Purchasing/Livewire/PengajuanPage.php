<?php

namespace App\Modules\Purchasing\Livewire;

use App\Modules\Ppic\Models\PurchaseRequest;
use App\Modules\Purchasing\Services\ProcurementService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Pengajuan Pembelian — irris.next')]
class PengajuanPage extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $selectedRequestId = null;

    public string $ordered_on = '';

    public string $expected_on = '';

    public function boot(): void
    {
        Gate::authorize('purchasing.requests.view');
    }

    public function selectRequest(int $id): void
    {
        Gate::authorize('purchasing.orders.create');
        $request = PurchaseRequest::whereDoesntHave('order')->findOrFail($id);
        $this->selectedRequestId = $request->id;
        $this->ordered_on = now()->format('Y-m-d');
        $this->expected_on = $request->required_on->format('Y-m-d');
        $this->resetErrorBag();
        $this->dispatch('scroll-to-element', ['id' => 'purchase-order-form']);
    }

    public function cancelOrder(): void
    {
        $this->reset('selectedRequestId', 'ordered_on', 'expected_on');
        $this->resetErrorBag();
    }

    public function approveOrder(ProcurementService $service): void
    {
        Gate::authorize('purchasing.orders.create');
        if (! $this->selectedRequestId) {
            return;
        }
        $order = $service->approve($this->selectedRequestId, $this->ordered_on, $this->expected_on);
        $this->dispatch('purchasing-attention-updated');
        $this->cancelOrder();
        $this->resetPage('pendingPage');
        session()->flash('purchase-order-created', $order->reference.' dibuat. IC dapat mencatat penerimaan barang.');
        $this->redirectRoute('purchasing.orders', navigate: true);
    }

    public function render()
    {
        if (! Gate::allows('purchasing.orders.create')) {
            $this->cancelOrder();
        }
        return view('modules.purchasing.livewire.pengajuan', [
            'requests' => PurchaseRequest::with(['product', 'unit', 'salesOrder'])->whereDoesntHave('order')->latest('id')->paginate(15, ['*'], 'pendingPage'),
            'selectedRequest' => $this->selectedRequestId ? PurchaseRequest::with(['product', 'unit'])->find($this->selectedRequestId) : null,
        ]);
    }
}