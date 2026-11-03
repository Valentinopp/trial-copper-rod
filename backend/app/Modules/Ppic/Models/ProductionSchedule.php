<?php

namespace App\Modules\Ppic\Models;

use App\Models\User;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Services\LotEstimateService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ProductionSchedule extends Model
{
    protected $fillable = ['handoff_id', 'submission_token', 'planned_on', 'quantity', 'status', 'notes',
        'revision', 'created_by', 'updated_by', 'cancelled_by', 'cancelled_at',
        'rod_specification_id', 'rod_diameter_mm', 'rod_weight_ton_per_meter', 'kg_per_unit'];

    protected $casts = ['planned_on' => 'date', 'quantity' => 'decimal:6', 'revision' => 'integer', 'cancelled_at' => 'datetime',
        'rod_diameter_mm' => 'float', 'rod_weight_ton_per_meter' => 'float', 'kg_per_unit' => 'decimal:9'];

    public function boardEntries()
    {
        return $this->hasMany(BoardEntry::class, 'production_schedule_id');
    }

    public function scopeWithActuals(Builder $query): Builder
    {
        return $query->with('estimateEntries')->withSum('boardEntries as actual_kg', 'production_actual_kg')
            ->withSum(['boardEntries as rejected_kg' => fn ($q) => $q->whereNotNull('production_actual_kg')], 'reject_weight_kg')
            ->withCount('boardEntries as lot_count')
            ->withCount(['boardEntries as running_lot_count' => fn ($q) => $q->whereNull('production_actual_kg')]);
    }

    public function estimateEntries()
    {
        return $this->boardEntries()->whereNull('production_actual_kg');
    }

    public function progress(): array
    {
        if (! array_key_exists('lot_count', $this->attributes)) {
            return self::withActuals()->findOrFail($this->id)->progress();
        }
        $actualKg = BigDecimal::of((string) ($this->actual_kg ?? 0));
        $factor = $this->kg_per_unit ? BigDecimal::of($this->kg_per_unit) : null;
        $actual = $factor && $factor->isGreaterThan(0) ? $actualKg->dividedBy($factor, 6, RoundingMode::HalfUp) : null;
        $target = BigDecimal::of($this->quantity);
        $targetKg = $factor ? $target->multipliedBy($factor) : null;
        $difference = $actual?->minus($target);
        $estimates = $this->estimateEntries->map(fn ($entry) => LotEstimateService::calculate($entry->getAttributes()));
        $estimateKg = $estimates->contains(fn ($estimate) => $estimate['current_kg'] === null)
            ? null : BigDecimal::of(number_format($estimates->sum('current_kg'), 6, '.', ''));
        $completionKg = $estimateKg === null ? null : BigDecimal::of(number_format($estimates->sum('completion_kg'), 6, '.', ''));
        $estimate = $factor && $estimateKg !== null ? $estimateKg->dividedBy($factor, 6, RoundingMode::HalfUp) : null;
        $completion = $factor && $completionKg !== null ? $completionKg->dividedBy($factor, 6, RoundingMode::HalfUp) : null;
        $percentage = $targetKg && $targetKg->isGreaterThan(0) ? (string) $actualKg->multipliedBy(100)->dividedBy($targetKg, 1, RoundingMode::HalfUp) : null;
        $status = $this->status === 'cancelled' ? 'Dibatalkan' : (! $this->lot_count ? 'Belum mulai'
            : ($this->running_lot_count ? 'Sedang produksi' : ($targetKg && $actualKg->isGreaterThanOrEqualTo($targetKg) ? 'Target tercapai' : 'Sebagian selesai')));

        return ['actual' => $actual === null ? null : (string) $actual, 'actual_kg' => (string) $actualKg,
            'estimated' => $estimate === null ? null : (string) $estimate,
            'estimated_completion' => $completion === null ? null : (string) $completion,
            'reject_kg' => (string) ($this->rejected_kg ?? 0), 'difference' => $difference === null ? null : (string) $difference,
            'remaining' => $difference === null ? null : (string) BigDecimal::max(0, $difference->negated()),
            'percentage' => $percentage, 'status' => $status, 'lots' => (int) $this->lot_count, 'running' => (int) $this->running_lot_count];
    }

    protected function plannedOn(): Attribute
    {
        return Attribute::make(set: fn ($value) => CarbonImmutable::parse($value)->format('Y-m-d'));
    }

    public function handoff()
    {
        return $this->belongsTo(ProductionHandoff::class, 'handoff_id');
    }

    public function planner()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getReferenceAttribute(): string
    {
        return 'JAD-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
