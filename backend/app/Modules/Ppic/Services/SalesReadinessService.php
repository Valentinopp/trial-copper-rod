<?php

namespace App\Modules\Ppic\Services;

use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\MasterData\Models\Bom;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Services\BomService;
use App\Modules\Ppic\Models\InventoryReceipt;
use App\Modules\Ppic\Models\MaterialReservation;
use App\Modules\Ppic\Models\ProductionHandoff;
use App\Modules\Ppic\Models\PurchaseRequest;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SalesReadinessService
{
    public function prepare(int $saleId, ?int $bomId = null): void
    {
        \Illuminate\Support\Facades\Gate::authorize('ppic.ic.sales.check');
        DB::transaction(function () use ($saleId, $bomId) {
            $sale = SalesOrder::lockForUpdate()->findOrFail($saleId);
            if ($sale->materialRequirements()->exists()) {
                if ($bomId && $sale->bom_id !== $bomId) {
                    throw ValidationException::withMessages(['readiness' => 'BoM SO ini sudah diperiksa dan tidak dapat diganti.']);
                }

                return;
            }
            $product = Item::with(['category', 'unit'])->lockForUpdate()->find($sale->product_id);
            if (! $product || ! $product->is_active || mb_strtolower($product->category->name) !== 'produk' || ! $product->category->is_active) {
                throw ValidationException::withMessages(['readiness' => 'SO harus terhubung ke Master Barang kategori Produk yang aktif.']);
            }
            if ($sale->uom_id !== $product->uom_id || ! $product->unit->is_active ||
                ($product->unit->related && ! $product->unit->related->is_active)) {
                throw ValidationException::withMessages(['readiness' => 'UoM SO harus sama dengan UoM produk BoM yang aktif. Tidak ada konversi otomatis.']);
            }
            $boms = Bom::where('product_id', $product->id)->where('is_active', true);
            if (! $bomId && $boms->count() !== 1) {
                throw ValidationException::withMessages(['readiness' => 'Pilih satu Master BoM aktif untuk produk SO ini.']);
            }
            $bom = $boms->with('lines.material.unit')->when($bomId, fn ($q) => $q->whereKey($bomId))->lockForUpdate()->first();
            if (! $bom || $bom->lines->isEmpty() || BigDecimal::of($bom->output_quantity)->isLessThanOrEqualTo(0)) {
                throw ValidationException::withMessages(['readiness' => 'Master BoM tidak sesuai produk SO, nonaktif, atau belum lengkap.']);
            }
            // Revalidate references without changing the master recipe.
            $validator = app(BomService::class);
            $validator->validateItem($product->id, 'produk', 'readiness', true);
            $snapshot = ['code' => $bom->code, 'name' => $bom->name, 'output_quantity' => $bom->output_quantity,
                'uom_id' => $product->uom_id, 'uom' => $product->unit->code, 'lines' => []];
            foreach ($bom->lines as $item) {
                $validator->validateItem($item->material_id, 'material', 'readiness', true);
                $required = BigDecimal::of($item->quantity)->multipliedBy($sale->quantity)
                    ->dividedBy($bom->output_quantity, 6, RoundingMode::Ceiling);
                if ($required->isLessThanOrEqualTo(0) || $required->isGreaterThan('99999999999999.999999')) {
                    throw ValidationException::withMessages(['readiness' => 'Qty kebutuhan bahan di luar batas. Periksa BoM dan qty penjualan.']);
                }
                $sale->materialRequirements()->create([
                    'material_id' => $item->material_id, 'material_name' => $item->material->name,
                    'uom_id' => $item->material->uom_id, 'uom' => $item->material->unit->code, 'quantity' => (string) $required,
                ]);
                $snapshot['lines'][] = ['material_id' => $item->material_id, 'code' => $item->material->code,
                    'name' => $item->material->name, 'uom_id' => $item->material->uom_id, 'uom' => $item->material->unit->code,
                    'quantity' => $item->quantity];
            }
            $sale->update(['bom_id' => $bom->id, 'bom_snapshot' => $snapshot]);
        });
    }

    public function assess(int $saleId): array
    {
        \Illuminate\Support\Facades\Gate::authorize('ppic.ic.sales.view');
        $sale = SalesOrder::with(['materialRequirements', 'productionHandoff'])->findOrFail($saleId);
        $rows = $sale->materialRequirements->map(function ($requirement) use ($sale) {
            $balance = app(InventoryStockService::class)->balance($requirement->material_id, $requirement->uom_id, DB::transactionLevel() > 0);
            $stock = BigDecimal::of($balance['physical']);
            $reserved = BigDecimal::of($balance['reserved']);
            $own = $sale->productionHandoff
                ? MaterialReservation::where('handoff_id', $sale->productionHandoff->id)->where('material_id', $requirement->material_id)->where('uom_id', $requirement->uom_id)->sum('quantity') : '0';
            $available = BigDecimal::max(0, $stock->minus($reserved)->plus($own));
            $shortage = BigDecimal::max(0, BigDecimal::of($requirement->quantity)->minus($available));
            $outstanding = BigDecimal::of(0);
            $requests = PurchaseRequest::with(['order' => fn ($q) => $q->withSum('receipts', 'quantity')])
                ->where('sales_order_id', $sale->id)->where('product_id', $requirement->material_id)->where('uom_id', $requirement->uom_id)->get();
            foreach ($requests as $request) {
                $outstanding = $outstanding->plus(BigDecimal::max(0,
                    BigDecimal::of($request->quantity)->minus($request->order?->receipts_sum_quantity ?? 0)));
            }

            return [
                'material_id' => $requirement->material_id, 'name' => $requirement->material_name, 'uom' => $requirement->uom, 'uom_id' => $requirement->uom_id,
                'required' => $requirement->quantity, 'available' => (string) $available->toScale(6),
                'shortage' => (string) $shortage->toScale(6), 'outstanding' => (string) $outstanding->toScale(6),
                'requestable' => (string) BigDecimal::max(0, $shortage->minus($outstanding))->toScale(6),
            ];
        });

        return [
            'sale' => $sale, 'rows' => $rows, 'handoff' => $sale->productionHandoff,
            'ready' => $rows->isNotEmpty() && $rows->every(fn ($row) => BigDecimal::of($row['shortage'])->isZero()),
            'can_request' => $rows->contains(fn ($row) => BigDecimal::of($row['requestable'])->isGreaterThan(0)),
        ];
    }

    public function requestShortages(int $saleId, string $requiredOn): int
    {
        \Illuminate\Support\Facades\Gate::authorize('ppic.ic.sales.request');
        Validator::make(['materials_required_on' => $requiredOn], [
            'materials_required_on' => 'required|date_format:Y-m-d|after_or_equal:today',
        ], [], ['materials_required_on' => 'tanggal kebutuhan bahan'])->validate();

        return DB::transaction(function () use ($saleId, $requiredOn) {
            $sale = SalesOrder::lockForUpdate()->findOrFail($saleId);
            if (! $sale->materialRequirements()->exists()) {
                $this->prepare($saleId);
            }
            $this->lockMaterials($sale);
            $check = $this->assess($saleId);
            if ($check['handoff']) {
                return 0;
            }
            $count = 0;
            foreach ($check['rows'] as $row) {
                if (! BigDecimal::of($row['requestable'])->isGreaterThan(0)) {
                    continue;
                }
                if (BigDecimal::of($row['requestable'])->isGreaterThan('999999999')) {
                    throw ValidationException::withMessages(['readiness' => 'Qty pengajuan terlalu besar. Periksa skala BoM dan qty penjualan.']);
                }
                PurchaseRequest::create([
                    'product_id' => $row['material_id'], 'sales_order_id' => $saleId,
                    'quantity' => $row['requestable'], 'required_on' => $requiredOn, 'uom_id' => $row['uom_id'],
                ]);
                $count++;
            }

            return $count;
        });
    }

    public function handOver(int $saleId): ProductionHandoff
    {
        \Illuminate\Support\Facades\Gate::authorize('ppic.ic.sales.handover');
        return DB::transaction(function () use ($saleId) {
            $sale = SalesOrder::lockForUpdate()->findOrFail($saleId);
            if ($existing = $sale->productionHandoff()->first()) {
                return $existing;
            }
            if (! $sale->materialRequirements()->exists()) {
                $this->prepare($saleId);
            }
            $this->lockMaterials($sale);
            $check = $this->assess($saleId);
            if (! $check['ready']) {
                throw ValidationException::withMessages(['readiness' => 'Stok bahan belum mencukupi. Terima kekurangan bahan sebelum diserahkan ke Production Plan.']);
            }
            $handoff = ProductionHandoff::create(['sales_order_id' => $saleId, 'handed_over_at' => now()]);
            foreach ($check['rows'] as $row) {
                $handoff->reservations()->create(['material_id' => $row['material_id'], 'quantity' => $row['required'], 'uom_id' => $row['uom_id']]);
            }

            return $handoff;
        });
    }

    private function lockMaterials(SalesOrder $sale): void
    {
        // All handovers lock shared materials in the same order before checking
        // availability, so two sales cannot reserve the same free stock.
        Item::whereIn('id', $sale->materialRequirements()->pluck('material_id'))
            ->orderBy('id')->lockForUpdate()->get();
    }
}
