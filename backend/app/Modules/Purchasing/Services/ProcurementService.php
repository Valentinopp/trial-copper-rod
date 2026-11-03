<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\MasterData\Models\Item;
use App\Modules\Ppic\Models\InventoryReceipt;
use App\Modules\Ppic\Models\PurchaseRequest;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function materials(): Builder
    {
        return Item::with(['unit', 'category.parent'])->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true)->where(fn ($parent) => $parent->whereNull('parent_id')->orWhereHas('parent', fn ($q) => $q->where('is_active', true)))
                ->where(fn ($kind) => $kind->whereRaw('LOWER(name) IN (?, ?, ?, ?)', ['material', 'sparepart', 'perlengkapan', 'barang habis pakai'])
                    ->orWhereHas('parent', fn ($parent) => $parent->whereRaw('LOWER(name) IN (?, ?, ?, ?)', ['material', 'sparepart', 'perlengkapan', 'barang habis pakai'])->where('is_active', true))))
            ->whereHas('unit', fn ($q) => $q->where('is_active', true)
                ->where(fn ($base) => $base->whereNull('related_uom_id')->orWhereHas('related', fn ($related) => $related->where('is_active', true))))
            ->orderBy('code');
    }

    public function submit(array $data): PurchaseRequest
    {
        \Illuminate\Support\Facades\Gate::authorize('ppic.ic.requests.create');
        $validated = Validator::make($data, [
            'product_id' => 'required|integer|exists:master_data_items,id',
            'quantity' => 'required|numeric|min:0.000001|max:999999999|decimal:0,6',
            'required_on' => 'required|date_format:Y-m-d|after_or_equal:today',
        ], [], ['product_id' => 'produk / material', 'quantity' => 'qty', 'required_on' => 'tanggal barang dibutuhkan'])->validate();

        // Name and UoM come from the selected master record, not request input.
        return DB::transaction(function () use ($validated) {
            $material = $this->materials()->whereKey($validated['product_id'])->lockForUpdate()->first();
            if (! $material) {
                throw ValidationException::withMessages(['product_id' => 'Pilih barang persediaan aktif (Material, Sparepart, Perlengkapan, atau Barang Habis Pakai) beserta kategori dan UoM aktif.']);
            }

            return PurchaseRequest::create($validated + ['uom_id' => $material->uom_id]);
        });
    }

    public function approve(int $requestId, string $orderedOn, string $expectedOn): PurchaseOrder
    {
        \Illuminate\Support\Facades\Gate::authorize('purchasing.orders.create');
        Validator::make(['ordered_on' => $orderedOn, 'expected_on' => $expectedOn], [
            'ordered_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'expected_on' => 'required|date_format:Y-m-d|after_or_equal:ordered_on',
        ])->validate();

        return DB::transaction(function () use ($requestId, $orderedOn, $expectedOn) {
            $request = PurchaseRequest::lockForUpdate()->findOrFail($requestId);
            if ($request->order()->exists()) {
                throw ValidationException::withMessages(['ordered_on' => 'Pengajuan ini sudah memiliki pesanan pembelian.']);
            }

            return PurchaseOrder::create([
                'purchase_request_id' => $request->id, 'ordered_on' => $orderedOn, 'expected_on' => $expectedOn,
            ]);
        });
    }

    public function receive(int $orderId, string $quantity, string $receivedOn, string $token): InventoryReceipt
    {
        \Illuminate\Support\Facades\Gate::authorize('ppic.ic.receipts.create');
        Validator::make(['received_quantity' => $quantity, 'received_on' => $receivedOn, 'receipt_token' => $token], [
            'received_quantity' => 'required|numeric|min:0.000001|max:999999999|decimal:0,6',
            'received_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'receipt_token' => 'required|uuid',
        ])->validate();

        return DB::transaction(function () use ($orderId, $quantity, $receivedOn, $token) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($orderId);
            // A retried HTTP request must not add the same delivery to stock twice.
            if ($existing = $order->receipts()->where('receipt_token', $token)->first()) {
                return $existing;
            }
            if ($receivedOn < $order->ordered_on->format('Y-m-d')) {
                throw ValidationException::withMessages(['received_on' => 'Tanggal penerimaan tidak boleh sebelum tanggal pembelian.']);
            }
            $remaining = BigDecimal::of($order->request->quantity)->minus($order->receipts()->sum('quantity'));
            if (BigDecimal::of($quantity)->isGreaterThan($remaining)) {
                throw ValidationException::withMessages(['received_quantity' => 'Jumlah diterima melebihi sisa pesanan.']);
            }

            return $order->receipts()->create([
                'product_id' => $order->request->product_id, 'receipt_token' => $token,
                'quantity' => $quantity, 'received_on' => $receivedOn, 'uom_id' => $order->request->uom_id,
            ]);
        });
    }
}
