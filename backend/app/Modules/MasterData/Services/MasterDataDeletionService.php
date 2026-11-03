<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Customer;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Supplier;
use App\Modules\MasterData\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MasterDataDeletionService
{
    private function modelClass(string $kind): string
    {
        return match ($kind) {
            'item' => Item::class, 'category' => Category::class, 'unit' => Unit::class,
            'supplier' => Supplier::class, 'customer' => Customer::class,
            default => abort(404),
        };
    }

    public function find(string $kind, int $id): Model
    {
        Gate::authorize('access-master-data');
        return ($this->modelClass($kind))::findOrFail($id);
    }

    public function delete(string $kind, int $id): void
    {
        Gate::authorize('access-master-data');
        try {
            DB::transaction(function () use ($kind, $id) {
                $row = ($this->modelClass($kind))::lockForUpdate()->findOrFail($id);
                $inUse = match ($kind) {
                    'category' => $row->items()->exists() || $row->children()->exists(),
                    'unit' => $row->items()->exists() || Unit::where('related_uom_id', $id)->exists(),
                    'item' => $row->suppliers()->exists(),
                    default => false,
                };
                if ($inUse) {
                    throw ValidationException::withMessages(['delete' => 'Data masih digunakan dan tidak dapat dihapus. Lepaskan keterkaitannya atau nonaktifkan data.']);
                }
                if ($kind === 'supplier') {
                    $row->items()->detach();
                }
                $row->delete();
            });
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[0] ?? '', ['23000', '23503'], true)) {
                throw ValidationException::withMessages(['delete' => 'Data masih digunakan dan tidak dapat dihapus.']);
            }
            throw $exception;
        }
    }
}
