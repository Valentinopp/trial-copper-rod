<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\MasterData\Models\Customer;
use App\Modules\MasterData\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SalesService
{
    public function products(): Builder
    {
        return Item::with(['category', 'unit', 'rodSpecification'])
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['produk'])
                ->where('is_active', true)
                ->where(fn ($parent) => $parent->whereNull('parent_id')->orWhereHas('parent', fn ($q) => $q->where('is_active', true))))
            ->whereHas('unit', fn ($query) => $query->where('is_active', true)
                ->where(fn ($related) => $related->whereNull('related_uom_id')->orWhereHas('related', fn ($q) => $q->where('is_active', true))))
            ->orderBy('code');
    }

    public function record(array $data, string $token): SalesOrder
    {
        \Illuminate\Support\Facades\Gate::authorize('marketing.sales.create');
        Validator::make(['submission_token' => $token], ['submission_token' => 'required|uuid'])->validate();
        if ($existing = SalesOrder::where('submission_token', $token)->first()) {
            return $existing;
        }
        $valid = Validator::make($data, [
            'customer_id' => 'required|integer|exists:master_data_customers,id',
            'product_id' => 'required|integer|exists:master_data_items,id',
            'quantity' => 'required|numeric|min:0.000001|max:999999999|decimal:0,6',
            'sold_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'ship_by' => 'required|date_format:Y-m-d|after_or_equal:sold_on',
            'notes' => 'nullable|string|max:2000',
        ], [], ['customer_id' => 'customer', 'product_id' => 'produk', 'quantity' => 'jumlah penjualan',
            'sold_on' => 'tanggal penjualan', 'ship_by' => 'tanggal pengiriman'])->validate();

        return DB::transaction(function () use ($valid, $token) {
            $customer = Customer::lockForUpdate()->findOrFail($valid['customer_id']);
            if ($existing = SalesOrder::where('submission_token', $token)->first()) {
                return $existing;
            }
            if (! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => 'Pilih customer aktif dari Master Customer.']);
            }
            $product = $this->products()->whereKey($valid['product_id'])->lockForUpdate()->first();
            if (! $product) {
                throw ValidationException::withMessages(['product_id' => 'Pilih barang aktif berkategori Produk dengan kategori dan UoM aktif.']);
            }
            $rod = $product->rodSpecification;
            if ($rod && (!$rod->is_active || !$rod->diameter_mm || !$rod->weight_ton_per_meter)) {
                throw ValidationException::withMessages(['product_id' => 'Spesifikasi Cu Rod produk belum aktif atau lengkap. Periksa Master Barang.']);
            }

            return SalesOrder::create(collect($valid)->except('notes')->all() + [
                'notes' => trim($valid['notes'] ?? '') ?: null,
                'submission_token' => $token,
                'uom_id' => $product->uom_id,
                'customer_snapshot' => $customer->only(['code', 'name', 'contact_name', 'phone', 'email', 'address']),
                'product_snapshot' => $product->only(['code', 'name', 'description']) + ['copper_rod' => $rod ? [
                    'rod_specification_id' => $rod->id, 'diameter_mm' => $rod->diameter_mm,
                    'weight_ton_per_meter' => $rod->weight_ton_per_meter,
                ] : null],
                'unit_snapshot' => $product->unit->only(['code', 'name']),
            ]);
        });
    }
}
