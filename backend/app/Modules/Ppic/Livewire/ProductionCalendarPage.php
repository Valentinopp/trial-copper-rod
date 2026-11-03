<?php

namespace App\Modules\Ppic\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Kalender Produksi — irris.next')]
class ProductionCalendarPage extends Component
{
    public function boot(): void
    {
        Gate::authorize('access-ppic');
    }

    public function render()
    {
        return view('modules.ppic.livewire.production-calendar-page');
    }
}