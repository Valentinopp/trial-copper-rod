<?php

namespace App\Modules\Purchasing\Livewire;

use App\Modules\Ppic\Models\PurchaseRequest;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class AttentionIndicator extends Component
{
    #[On('purchasing-attention-updated')]
    public function refreshAttention(): void
    {
        // Refresh the indicator independently of any open form.
    }

    public function render()
    {
        $count = Gate::allows('purchasing.requests.view')
            ? PurchaseRequest::whereDoesntHave('order')->count() : 0;

        return view('modules.purchasing.livewire.attention-indicator', [
            'count' => $count,
            'label' => $count.' pengajuan pembelian belum diproses',
        ]);
    }
}