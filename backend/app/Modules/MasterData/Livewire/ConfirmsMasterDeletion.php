<?php

namespace App\Modules\MasterData\Livewire;

use App\Modules\MasterData\Services\MasterDataDeletionService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait ConfirmsMasterDeletion
{
    #[Locked]
    public ?int $pendingDeleteId = null;
    #[Locked]
    public string $pendingDeleteKind = '';
    #[Locked]
    public string $pendingDeleteLabel = '';

    abstract protected function onMasterDeleted(): void;

    protected function deletionKinds(): array
    {
        return ['item', 'category', 'unit'];
    }

    public function confirmRemoval(string $kind, int $id): void
    {
        abort_unless(in_array($kind, $this->deletionKinds(), true), 404);
        $row = app(MasterDataDeletionService::class)->find($kind, $id);
        $this->pendingDeleteId = $id;
        $this->pendingDeleteKind = $kind;
        $this->pendingDeleteLabel = $row->code.' · '.$row->name;
        $this->resetValidation('delete');
    }

    public function cancelRemoval(): void
    {
        $this->pendingDeleteId = null;
        $this->pendingDeleteKind = $this->pendingDeleteLabel = '';
        $this->resetValidation('delete');
    }

    public function deleteConfirmed(MasterDataDeletionService $service): void
    {
        if ($this->pendingDeleteId === null) {
            return;
        }
        try {
            $service->delete($this->pendingDeleteKind, $this->pendingDeleteId);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());
            return;
        }
        $this->cancelRemoval();
        $this->onMasterDeleted();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Master data berhasil dihapus.']);
    }
}
