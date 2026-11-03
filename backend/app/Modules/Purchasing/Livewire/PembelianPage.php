<?php

namespace App\Modules\Purchasing\Livewire;

use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Pesanan Pembelian — irris.next')]
class PembelianPage extends Component
{
    use WithPagination;

    public function boot(): void
    {
        Gate::authorize('purchasing.orders.view');
    }

    public function render()
    {
        return view('modules.purchasing.livewire.pembelian', [
            'orders' => PurchaseOrder::with(['request.product', 'request.unit'])->withSum('receipts', 'quantity')->latest('id')->paginate(15, ['*'], 'purchaseOrdersPage'),
        ]);
    }
}