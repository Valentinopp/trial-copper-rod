<?php

namespace App\Modules\Ppic\Services;

use App\Modules\MasterData\Models\ProductionReference;
use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\Ppic\Models\ProductionHandoff;
use App\Modules\Ppic\Models\ProductionSchedule;
use App\Modules\Produksi\Services\ProductionPlanIntegrationService;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductionPlanningService
{
    public const TIMEZONE = 'Asia/Bangkok';

    public function handoffs(string $search = '', bool $pendingOnly = true): Builder
    {
        Gate::authorize('access-ppic');
        $query = ProductionHandoff::query()->select('production_handoffs.*')
            ->join('marketing_sales_orders as sales', 'sales.id', '=', 'production_handoffs.sales_order_id')
            ->with(['sale.materialRequirements', 'activeSchedules'])
            ->withSum(['schedules as scheduled_quantity' => fn ($q) => $q->where('status', 'scheduled')], 'quantity');
        if ($pendingOnly) {
            $query->whereRaw("sales.quantity > (SELECT COALESCE(SUM(quantity), 0) FROM production_schedules WHERE handoff_id = production_handoffs.id AND status = 'scheduled')");
        }
        if (trim($search) !== '') {
            $term = '%'.mb_strtolower(trim($search)).'%';
            $query->where(function ($q) use ($term, $search) {
                $q->whereRaw('LOWER(CAST(sales.product_snapshot AS TEXT)) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(CAST(sales.customer_snapshot AS TEXT)) LIKE ?', [$term]);
                if (preg_match('/^(SO|PP)-0*(\d+)$/i', trim($search), $matches)) {
                    $q->orWhere(strtoupper($matches[1]) === 'SO' ? 'sales.id' : 'production_handoffs.id', (int) $matches[2]);
                }
            });
        }

        return $query->orderBy('sales.ship_by')->orderBy('production_handoffs.id');
    }

    public function remaining(ProductionHandoff $handoff, ?int $exceptSchedule = null): BigDecimal
    {
        $allocated = $handoff->schedules()->where('status', 'scheduled')
            ->when($exceptSchedule, fn ($q) => $q->where('id', '!=', $exceptSchedule))->pluck('quantity')
            ->reduce(fn (BigDecimal $sum, $value) => $sum->plus((string) $value), BigDecimal::of(0));

        return BigDecimal::max(0, BigDecimal::of($handoff->sale->quantity)->minus($allocated))->toScale(6);
    }

    public function rodForSale(SalesOrder $sale): array
    {
        $empty = ['rod_specification_id' => null, 'rod_diameter_mm' => null, 'rod_weight_ton_per_meter' => null];
        $snapshot = $sale->product_snapshot ?? [];
        if (array_key_exists('copper_rod', $snapshot)) {
            $rod = $snapshot['copper_rod'];
            if ($rod === null) return $empty;
            if (!is_array($rod) || !is_numeric($rod['diameter_mm'] ?? null) || (float) $rod['diameter_mm'] <= 0
                || !is_numeric($rod['weight_ton_per_meter'] ?? null) || (float) $rod['weight_ton_per_meter'] <= 0
                || !ProductionReference::where('section', 'rod-specifications')->whereKey($rod['rod_specification_id'] ?? 0)->exists()) {
                $this->fail('schedule', 'Spesifikasi Copper Rod pada SO belum lengkap. Periksa data produk pesanan.');
            }
            return ['rod_specification_id' => $rod['rod_specification_id'], 'rod_diameter_mm' => $rod['diameter_mm'],
                'rod_weight_ton_per_meter' => $rod['weight_ton_per_meter']];
        }
        // Compatibility for orders created before product specification snapshots existed.
        if (is_numeric($snapshot['diameter_mm'] ?? null) && (float) $snapshot['diameter_mm'] > 0) {
            $matches = ProductionReference::where('section', 'rod-specifications')->where('is_active', true)
                ->where('diameter_mm', $snapshot['diameter_mm'])->get();
            if ($matches->count() !== 1) {
                $this->fail('schedule', 'Diameter pada SO belum memiliki satu spesifikasi Cu Rod aktif yang sesuai. Periksa master spesifikasi.');
            }
            $rod = $matches->first();
        } else {
            $rod = $sale->product?->rodSpecification;
        }
        if (!$rod) return $empty;
        if (!$rod->is_active || !$rod->diameter_mm || !$rod->weight_ton_per_meter) {
            $this->fail('schedule', 'Spesifikasi Copper Rod produk belum aktif atau lengkap. Periksa Master Barang.');
        }
        return ['rod_specification_id' => $rod->id, 'rod_diameter_mm' => $rod->diameter_mm,
            'rod_weight_ton_per_meter' => $rod->weight_ton_per_meter];
    }

    public function save(array $data, ?int $id = null, ?int $expectedRevision = null): ProductionSchedule
    {
        Gate::authorize('access-ppic');
        $valid = Validator::make($data, [
            'handoff_id' => 'required|integer|exists:production_handoffs,id',
            'submission_token' => 'required|uuid',
            'planned_on' => 'required|date_format:Y-m-d',
            'quantity' => 'required|numeric|min:0.000001|max:999999999|decimal:0,6',
            'notes' => 'nullable|string|max:2000',
        ], [], ['planned_on' => 'tanggal produksi', 'quantity' => 'jumlah produksi', 'handoff_id' => 'pesanan dari IC'])->validate();

        return DB::transaction(function () use ($valid, $id, $expectedRevision) {
            // Serialize allocations for the same order, including simultaneous edits and cancellations.
            $handoff = ProductionHandoff::with(['sale.materialRequirements', 'sale.unit.related', 'reservations'])->lockForUpdate()->findOrFail($valid['handoff_id']);
            if (! $id && ($existing = ProductionSchedule::where('submission_token', $valid['submission_token'])->first())) {
                if ($existing->handoff_id !== $handoff->id) {
                    $this->fail('schedule', 'Permintaan jadwal tidak sesuai pesanan. Muat ulang halaman.');
                }

                return $existing;
            }
            $schedule = $id ? ProductionSchedule::lockForUpdate()->findOrFail($id) : new ProductionSchedule;
            if ($id) {
                if ($schedule->handoff_id !== $handoff->id) {
                    $this->fail('schedule', 'Pesanan pada jadwal tidak dapat diganti.');
                }
                $this->assertEditable($schedule, $expectedRevision);
            }
            $hasLots = $id && $schedule->boardEntries()->exists();
            if ($hasLots && $valid['planned_on'] !== $schedule->planned_on->format('Y-m-d')) {
                $this->fail('schedule', 'Tanggal dan diameter jadwal yang sudah memiliki LOT tidak dapat diganti.');
            }
            $factor = $hasLots ? $schedule->kg_per_unit : app(ProductionPlanIntegrationService::class)->kgPerUnit($handoff->sale->unit);
            if ($hasLots && $factor && ! BigDecimal::of($valid['quantity'])->isEqualTo($schedule->quantity)) {
                $actual = $schedule->boardEntries()->pluck('production_actual_kg')->filter(fn ($value) => $value !== null)
                    ->reduce(fn (BigDecimal $sum, $value) => $sum->plus($value), BigDecimal::of(0));
                if (BigDecimal::of($valid['quantity'])->multipliedBy($factor)->isLessThan($actual)) {
                    $this->fail('quantity', 'Target baru tidak boleh lebih kecil dari aktual produksi yang sudah tercatat.');
                }
            }
            $rodData = $id && $schedule->rod_specification_id
                ? $schedule->only(['rod_specification_id', 'rod_diameter_mm', 'rod_weight_ton_per_meter'])
                : $this->rodForSale($handoff->sale);
            if ($valid['planned_on'] < CarbonImmutable::today(self::TIMEZONE)->format('Y-m-d')) {
                $this->fail('planned_on', 'Pilih hari ini atau tanggal berikutnya untuk rencana produksi.');
            }
            $this->assertReserved($handoff);
            $remaining = $this->remaining($handoff, $id);
            if (BigDecimal::of($valid['quantity'])->isGreaterThan($remaining)) {
                $this->fail('quantity', 'Jumlah melebihi sisa pesanan yang belum dijadwalkan ('.$remaining.' '.$handoff->sale->unit_snapshot['code'].'). Muat ulang jika pesanan baru saja diubah.');
            }
            if (! $id) {
                $schedule->fill(['handoff_id' => $handoff->id, 'submission_token' => $valid['submission_token'], 'created_by' => auth()->id()]);
            }
            $schedule->fill([
                'planned_on' => $valid['planned_on'], 'quantity' => $valid['quantity'], 'notes' => trim($valid['notes'] ?? '') ?: null,
                'status' => 'scheduled', 'updated_by' => auth()->id(), 'revision' => $id ? $schedule->revision + 1 : 1,
                'kg_per_unit' => $factor,
            ] + $rodData)->save();

            return $schedule;
        });
    }

    public function cancel(int $id, int $expectedRevision): ProductionSchedule
    {
        Gate::authorize('access-ppic');
        $source = ProductionSchedule::findOrFail($id);

        return DB::transaction(function () use ($source, $expectedRevision) {
            ProductionHandoff::whereKey($source->handoff_id)->lockForUpdate()->firstOrFail();
            $schedule = ProductionSchedule::lockForUpdate()->findOrFail($source->id);
            if ($schedule->status === 'cancelled') {
                return $schedule;
            }
            $this->assertEditable($schedule, $expectedRevision);
            if ($schedule->boardEntries()->exists()) {
                $this->fail('schedule', 'Jadwal sudah memiliki LOT Copper Rod dan tidak dapat dibatalkan.');
            }
            $schedule->update(['status' => 'cancelled', 'cancelled_by' => auth()->id(), 'cancelled_at' => now(),
                'updated_by' => auth()->id(), 'revision' => $schedule->revision + 1]);

            return $schedule;
        });
    }

    private function assertEditable(ProductionSchedule $schedule, ?int $expectedRevision): void
    {
        if ($schedule->status !== 'scheduled') {
            $this->fail('schedule', 'Jadwal ini sudah dibatalkan dan tidak dapat diubah.');
        }
        if ($expectedRevision !== $schedule->revision) {
            $this->fail('schedule', 'Jadwal sudah diubah oleh pengguna lain. Tutup formulir dan buka kembali untuk mendapatkan data terbaru.');
        }
    }

    private function assertReserved(ProductionHandoff $handoff): void
    {
        if (! $handoff->sale || ! $handoff->sale->uom_id || ! $handoff->sale->materialRequirements->count()) {
            $this->fail('schedule', 'Data pesanan belum lengkap. Periksa kembali penyerahan dari Inventory Control.');
        }
        foreach ($handoff->sale->materialRequirements as $requirement) {
            $reserved = $handoff->reservations->where('material_id', $requirement->material_id)->where('uom_id', $requirement->uom_id)
                ->reduce(fn (BigDecimal $sum, $row) => $sum->plus($row->quantity), BigDecimal::of(0));
            if (! $requirement->uom_id || $reserved->isLessThan($requirement->quantity)) {
                $this->fail('schedule', 'Cadangan bahan pesanan belum lengkap. Periksa kembali di Inventory Control.');
            }
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
