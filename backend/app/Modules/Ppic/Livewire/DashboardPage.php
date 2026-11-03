<?php

namespace App\Modules\Ppic\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Modul PPIC')]
class DashboardPage extends Component
{
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('access-ppic');
    }

    public function render()
    {
        return view('modules.ppic.livewire.dashboard-page');
    }
}
