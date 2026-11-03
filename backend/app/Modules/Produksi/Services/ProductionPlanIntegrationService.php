<?php

namespace App\Modules\Produksi\Services;

use App\Modules\MasterData\Models\Unit;
use App\Modules\Ppic\Models\ProductionSchedule;
use App\Modules\Produksi\Models\BoardEntry;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductionPlanIntegrationService
{
    public function dailyOutputComparison(string $from, string $until): Collection
    {
        return $this->schedules()->where('status', 'scheduled')->whereBetween('planned_on', [$from, $until])->get()
            ->groupBy(fn ($row) => $row->planned_on->format('Y-m-d'))->map(function ($rows) {
                $unconverted = $rows->filter(fn ($row) => ! $row->kg_per_unit || BigDecimal::of($row->kg_per_unit)->isLessThanOrEqualTo(0))->count();
                $planKg = $unconverted ? null : $rows->reduce(fn (BigDecimal $sum, $row) => $sum->plus(BigDecimal::of($row->quantity)->multipliedBy($row->kg_per_unit)), BigDecimal::of(0));
                $actualKg = $rows->reduce(fn (BigDecimal $sum, $row) => $sum->plus($row->actual_kg ?? 0), BigDecimal::of(0));

                return [
                    'plan_count' => $rows->count(), 'unconverted_plans' => $unconverted,
                    'plan_mt' => $planKg === null ? null : (string) $planKg->dividedBy(1000, 9, RoundingMode::HalfUp),
                    'actual_mt' => (string) $actualKg->dividedBy(1000, 9, RoundingMode::HalfUp),
                    'difference_mt' => $planKg === null ? null : (string) $actualKg->minus($planKg)->dividedBy(1000, 9, RoundingMode::HalfUp),
                    'plan_achievement_pct' => $planKg?->isGreaterThan(0)
                        ? (string) $actualKg->multipliedBy(100)->dividedBy($planKg, 2, RoundingMode::HalfUp) : null,
                ];
            });
    }

    public function totals(Collection $schedules): Collection
    {
        return $schedules->groupBy(fn ($row) => $row->handoff->sale->uom_id)->map(function ($rows) {
            $target = $rows->reduce(fn (BigDecimal $sum, $row) => $sum->plus($row->quantity), BigDecimal::of(0));
            $actual = $rows->every(fn ($row) => (bool) $row->kg_per_unit)
                ? $rows->reduce(fn (BigDecimal $sum, $row) => $sum->plus($row->progress()['actual']), BigDecimal::of(0)) : null;
            $progress = $rows->map(fn ($row) => $row->progress());
            $estimated = $progress->every(fn ($row) => $row['estimated'] !== null)
                ? $progress->reduce(fn (BigDecimal $sum, $row) => $sum->plus($row['estimated']), BigDecimal::of(0)) : null;
            $completion = $progress->every(fn ($row) => $row['estimated_completion'] !== null)
                ? $progress->reduce(fn (BigDecimal $sum, $row) => $sum->plus($row['estimated_completion']), BigDecimal::of(0)) : null;

            return ['quantity' => (string) $target->toScale(6), 'actual' => $actual === null ? null : (string) $actual->toScale(6),
                'estimated' => $estimated === null ? null : (string) $estimated->toScale(6),
                'estimated_completion' => $completion === null ? null : (string) $completion->toScale(6),
                'difference' => $actual === null ? null : (string) $actual->minus($target)->toScale(6), 'unit' => $rows->first()->handoff->sale->unit_snapshot['code']];
        })->values();
    }

    public function schedules(): Builder
    {
        abort_unless(Gate::allows('access-produksi') || Gate::allows('access-ppic'), 403);

        return ProductionSchedule::withActuals()->with('handoff.sale');
    }

    public function selected(string $id): ?ProductionSchedule
    {
        return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false ? $this->schedules()->find($id) : null;
    }

    public function kgPerUnit(?Unit $unit): ?string
    {
        if (! $unit) {
            return null;
        }
        if (mb_strtoupper(trim($unit->code)) === 'KG' && ! $unit->related_uom_id) {
            return '1';
        }
        $base = $unit->related;

        return $base && mb_strtoupper(trim($base->code)) === 'KG' && ! $base->related_uom_id && BigDecimal::of($unit->factor)->isGreaterThan(0)
            ? (string) $unit->factor : null;
    }

    // Called inside the board transaction. Locking the schedule also serializes planner changes/cancellation.
    public function prepareBoard(array $payload, string $scheduleId, ?BoardEntry $original, string $reject): array
    {
        Gate::authorize('access-produksi');
        if ($original?->production_schedule_id && (string) $original->production_schedule_id !== $scheduleId) {
            $this->fail('production_schedule_id', 'Jadwal LOT yang sudah terhubung tidak dapat diganti. Koreksi catatan pada jadwal yang sama.');
        }
        if (config('copper_rod.standalone')) {
            if ($scheduleId !== '') {
                $this->fail('production_schedule_id', 'Trial Copper Rod menggunakan pencatatan mandiri tanpa jadwal Production Plan.');
            }
            $master = app(ProductionMasterDataService::class)->resolve('rod-specifications', (string) $payload['rod_diameter_mm'],
                'rod_diameter_mm', $original ? (string) $original->rod_diameter_mm : null, $original?->rod_diameter_mm_master_id);
            $weight = app(ProductionMasterDataService::class)->rodWeight((string) $payload['rod_diameter_mm'], $original);
            if (!$master || !$weight || $weight <= 0) {
                $this->fail('rod_diameter_mm', 'Pilih spesifikasi Cu Rod dengan bobot per meter yang lengkap.');
            }
            if (BoardEntry::where('lot', $payload['lot'])->where('crystallizer', $payload['crystallizer'])
                ->where('upcasting_date', $payload['upcasting_date'])->where('upcasting_time', $payload['upcasting_time'])
                ->when($original, fn ($q) => $q->whereKeyNot($original->id))->exists()) {
                $this->fail('lot', 'LOT pada crystallizer dan waktu upcasting ini sudah tercatat. Ubah catatan yang sudah ada.');
            }

            return array_replace($payload, ['production_schedule_id' => null,
                'rod_diameter_mm_master_id' => $master->id, 'rod_weight_ton_per_meter' => $weight],
                $this->weighedActual($payload, $reject));
        }
        if ($scheduleId === '') {
            $this->fail('production_schedule_id', 'Pilih jadwal Production Plan yang terhubung ke SO. Pencatatan tanpa SO tidak diperbolehkan.');
        }
        $schedule = ctype_digit($scheduleId) ? ProductionSchedule::with('handoff.sale')->lockForUpdate()->find($scheduleId) : null;
        if (! $schedule || $schedule->status !== 'scheduled' || !$schedule->handoff?->sale) {
            $this->fail('production_schedule_id', 'Pilih jadwal Production Plan aktif yang belum dibatalkan.');
        }
        if (! $schedule->kg_per_unit) {
            $this->fail('production_schedule_id', 'Satuan jadwal belum memiliki konversi ke KG. Minta planner memeriksa satuannya.');
        }
        if (! $schedule->rod_specification_id || ! $schedule->rod_diameter_mm || ! $schedule->rod_weight_ton_per_meter) {
            $this->fail('production_schedule_id', 'Spesifikasi Cu Rod pada jadwal belum tersedia. Periksa spesifikasi produk di Master Barang dan SO.');
        }
        if ($original && ! $original->production_schedule_id && $original->rod_diameter_mm !== null
            && ! BigDecimal::of((string) $original->rod_diameter_mm)->isEqualTo((string) $schedule->rod_diameter_mm)) {
            $this->fail('production_schedule_id', 'Diameter pada catatan lama tidak sesuai jadwal yang dipilih. Pilih jadwal dengan spesifikasi yang sama.');
        }
        if (! BigDecimal::of((string) $payload['rod_diameter_mm'])->isEqualTo((string) $schedule->rod_diameter_mm)) {
            $this->fail('rod_diameter_mm', 'Diameter harus sesuai spesifikasi pada jadwal produksi. Tutup lalu buka kembali formulir untuk memuat spesifikasi terbaru.');
        }
        $duplicate = BoardEntry::where('production_schedule_id', $schedule->id)
            ->where('lot', $payload['lot'])->where('crystallizer', $payload['crystallizer'])
            ->where('upcasting_date', $payload['upcasting_date'])->where('upcasting_time', $payload['upcasting_time'])
            ->when($original, fn ($q) => $q->where('id', '!=', $original->id))->exists();
        if ($duplicate) {
            $this->fail('lot', 'LOT pada crystallizer dan waktu upcasting ini sudah tercatat. Ubah catatan yang sudah ada agar aktual tidak terhitung dua kali.');
        }
        return array_replace($payload, ['production_schedule_id' => $schedule->id,
            'rod_diameter_mm_master_id' => $schedule->rod_specification_id,
            'rod_weight_ton_per_meter' => $schedule->rod_weight_ton_per_meter], $this->weighedActual($payload, $reject));
    }

    private function weighedActual(array $payload, string $reject): array
    {
        $pickupComplete = ! empty($payload['actual_pickup_date']) && ! empty($payload['actual_pickup_time']);
        $finished = $pickupComplete && in_array($payload['event_status'], ['panen', 'putus'], true);
        $valid = Validator::make(['weight_kg' => $payload['weight_kg'], 'reject_weight_kg' => $reject], [
            'weight_kg' => [$finished ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,6'],
            'reject_weight_kg' => 'required|numeric|min:0|max:999999999|decimal:0,6',
        ], ['weight_kg.required' => 'Isi berat timbang aktual setelah LOT selesai pick up.'], ['reject_weight_kg' => 'berat reject'])->validate();
        $gross = BigDecimal::of((string) ($valid['weight_kg'] ?? 0));
        $rejected = BigDecimal::of($valid['reject_weight_kg']);
        if (!$pickupComplete && isset($valid['weight_kg']) && $valid['weight_kg'] !== '') {
            $this->fail('weight_kg', 'Isi tanggal dan jam Aktual Pick Up terlebih dahulu sebelum mengisi berat.');
        }
        if (!$pickupComplete && !$rejected->isZero()) {
            $this->fail('reject_weight_kg', 'Isi tanggal dan jam Aktual Pick Up terlebih dahulu sebelum mengisi reject.');
        }
        if ($rejected->isGreaterThan($gross)) {
            $this->fail('reject_weight_kg', 'Berat reject tidak boleh melebihi berat timbang.');
        }

        return ['reject_weight_kg' => (string) $rejected,
            'production_actual_kg' => $finished ? (string) $gross->minus($rejected)->toScale(6) : null];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
