<?php

namespace App\Modules\MasterData\Livewire;

use App\Modules\MasterData\Models\Customer;
use App\Modules\MasterData\Services\PartnerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Master Customer - irris.next')]
class CustomerPage extends PartnerPage
{
    protected function partnerClass(): string
    {
        return Customer::class;
    }

    protected function kind(): string
    {
        return 'customer';
    }

    protected function persist(PartnerService $service): void
    {
        $service->saveCustomer($this->form, $this->editingId);
    }
}
