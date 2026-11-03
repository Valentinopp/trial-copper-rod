<?php

namespace App\Modules\Ppic\Livewire;

use App\Modules\Ppic\Services\PpicAttentionService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class AttentionIndicator extends Component
{
    #[Locked]
    public string $kind = 'inventory';

    public function mount(string $kind = 'inventory'): void
    {
        abort_unless(in_array($kind, ['inventory', 'sales', 'incoming'], true), 404);
        $this->kind = $kind;
    }

    #[On('ppic-attention-updated')]
    public function refreshAttention(): void
    {
        // Re-render this small indicator without refreshing the surrounding form.
    }

    public function render()
    {
        return view('modules.ppic.livewire.attention-indicator', app(PpicAttentionService::class)->for($this->kind));
    }
}
