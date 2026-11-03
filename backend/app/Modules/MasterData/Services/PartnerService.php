<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Customer;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartnerService
{
    public function saveSupplier(array $data, ?int $id = null): Supplier
    {
        Gate::authorize('access-master-data');
        $valid = $this->validate($data, 'master_data_suppliers', $id, true);

        return DB::transaction(function () use ($valid, $id) {
            $supplier = $id ? Supplier::lockForUpdate()->findOrFail($id) : new Supplier;
            $existing = $id ? $supplier->items()->pluck('master_data_items.id')->all() : [];
            // Omitting item_ids preserves relations. Sending [] explicitly clears
            // them. An inactive existing item may remain until explicitly removed.
            $ids = array_map('intval', $valid['item_ids'] ?? $existing);
            $items = Item::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($items->count() !== count($ids) || $items->contains(fn ($item) => ! $item->is_active && ! in_array($item->id, $existing, true))) {
                throw ValidationException::withMessages(['item_ids' => 'Barang baru yang dihubungkan harus ada dan aktif. Barang nonaktif yang sudah terhubung dapat dipertahankan atau dilepas.']);
            }
            unset($valid['item_ids']);
            $supplier->fill($valid)->save();
            $supplier->items()->sync($ids);

            return $supplier;
        });
    }

    public function saveCustomer(array $data, ?int $id = null): Customer
    {
        Gate::authorize('access-master-data');
        $valid = $this->validate($data, 'master_data_customers', $id);

        return DB::transaction(function () use ($valid, $id) {
            $customer = $id ? Customer::lockForUpdate()->findOrFail($id) : new Customer;
            $customer->fill($valid)->save();

            return $customer;
        });
    }

    private function validate(array $data, string $table, ?int $id, bool $withItems = false): array
    {
        $data['code'] = strtoupper(trim($data['code'] ?? ''));
        $data['name'] = preg_replace('/\s+/u', ' ', trim($data['name'] ?? ''));
        foreach (['contact_name', 'phone', 'email', 'address', 'notes'] as $key) {
            $value = $data[$key] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $data[$key] = $value === '' ? null : $value;
        }
        $rules = [
            'code' => ['required', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', 'max:60', Rule::unique($table, 'code')->ignore($id)],
            'name' => 'required|string|max:255', 'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:100', 'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:2000', 'notes' => 'nullable|string|max:2000', 'is_active' => 'required|boolean',
        ];
        if ($withItems) {
            $rules += ['item_ids' => 'sometimes|array', 'item_ids.*' => 'required|integer|distinct|exists:master_data_items,id'];
        }

        return Validator::make($data, $rules, [], [
            'code' => 'kode', 'name' => 'nama', 'contact_name' => 'contact person', 'phone' => 'telepon',
            'email' => 'email', 'address' => 'alamat', 'notes' => 'catatan', 'item_ids' => 'barang', 'item_ids.*' => 'barang',
        ])->validate();
    }
}
