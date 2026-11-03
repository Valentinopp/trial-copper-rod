<?php

namespace App\Modules\Purchasing\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard Purchasing — irris.next')]
class DashboardPage extends Component
{
    public function boot(): void
    {
        Gate::authorize('access-purchasing');
    }

    public function render()
    {
        return view('modules.purchasing.livewire.dashboard');
    }
}