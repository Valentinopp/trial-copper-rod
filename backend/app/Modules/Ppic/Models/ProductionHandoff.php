<?php

namespace App\Modules\Ppic\Models;

use App\Modules\Marketing\Models\SalesOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;

class ProductionHandoff extends Model
{
    protected $fillable = ['sales_order_id', 'handed_over_at'];

    protected $casts = ['handed_over_at' => 'datetime'];

    public function sale()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function reservations()
    {
        return $this->hasMany(MaterialReservation::class, 'handoff_id');
    }

    public function schedules()
    {
        return $this->hasMany(ProductionSchedule::class, 'handoff_id');
    }

    public function activeSchedules()
    {
        return $this->schedules()->where('status', 'scheduled')->withSum('boardEntries as actual_kg', 'production_actual_kg');
    }

    public function getActualQuantityAttribute(): ?string
    {
        $total = BigDecimal::of(0);
        foreach ($this->activeSchedules as $schedule) {
            if (!$schedule->kg_per_unit || BigDecimal::of($schedule->kg_per_unit)->isLessThanOrEqualTo(0)) {
                return null;
            }
            $total = $total->plus(BigDecimal::of((string) ($schedule->actual_kg ?? 0))
                ->dividedBy($schedule->kg_per_unit, 6, RoundingMode::HalfUp));
        }

        return (string) $total->toScale(6);
    }

    public function getRemainingQuantityAttribute(): string
    {
        $scheduled = array_key_exists('scheduled_quantity', $this->attributes) ? $this->scheduled_quantity
            : $this->schedules()->where('status', 'scheduled')->sum('quantity');

        return (string) BigDecimal::max(0, BigDecimal::of($this->sale->quantity)
            ->minus((string) ($scheduled ?? 0)))->toScale(6);
    }

    public function getRemainingActualQuantityAttribute(): ?string
    {
        $actual = $this->actual_quantity;

        return $actual === null ? null : (string) BigDecimal::of($actual)->minus($this->sale->quantity)->toScale(6);
    }

    public function getReferenceAttribute(): string
    {
        return 'PP-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
