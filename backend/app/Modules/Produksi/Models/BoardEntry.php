<?php

namespace App\Modules\Produksi\Models;

use App\Modules\Produksi\Enums\EventStatus;
use App\Modules\Produksi\Services\ServoInferenceService;
use App\Modules\Produksi\Services\ProductionRuntimeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BoardEntry extends Model
{
    protected $table = 'board_entries';

    protected $fillable = [
        'production_schedule_id', 'reject_weight_kg', 'production_actual_kg',
        'take_up_master_id', 'cooling_master_id', 'crystallizer_master_id', 'rod_diameter_mm_master_id',
        'rod_weight_ton_per_meter',
        'uses_master_servo',
        'board_date',
        'shift',
        'take_up',
        'take_up_speed',
        'rod_diameter_mm',
        'cooling',
        'servo',
        'servo_override',
        'crystallizer',
        'upcasting_date',
        'source_tag',
        'upcasting_time',
        'estimated_pickup_date',
        'estimated_pickup_time',
        'actual_pickup_date',
        'actual_pickup_time',
        'lot',
        'weight_kg',
        'operating_hours',
        'event_status',
        'remarks',
    ];

    protected $casts = [
        'reject_weight_kg' => 'decimal:6', 'production_actual_kg' => 'decimal:6',
        'rod_weight_ton_per_meter' => 'float',
        'uses_master_servo' => 'boolean',
        'weight_kg'       => 'float',
        'operating_hours' => 'float',
        'take_up_speed' => 'float', // Input mesin dalam mm/menit.
        'rod_diameter_mm' => 'float',
    ];

    public function copperBreakEntry(): HasOne
    {
        return $this->hasOne(CopperBreakEntry::class, 'board_entry_id');
    }

    public function productionSchedule()
    {
        return $this->belongsTo(\App\Modules\Ppic\Models\ProductionSchedule::class, 'production_schedule_id');
    }

    public function shiftEntries(): HasMany
    {
        return $this->hasMany(ShiftEntry::class, 'board_entry_id');
    }

    public function getServoClassificationAttribute(): array
    {
        if ($this->uses_master_servo && $this->servo) {
            return ['servo' => $this->servo, 'servo_conflict' => false, 'take_up_servo' => $this->servo, 'cooling_servo' => $this->servo];
        }
        return ServoInferenceService::infer($this->take_up, $this->cooling, $this->servo);
    }

    public function getEffectiveServoAttribute(): ?string
    {
        return $this->servo_classification['servo'];
    }

    public function getServoConflictAttribute(): bool
    {
        return $this->servo_classification['servo_conflict'];
    }

    public function getNormalizedEventStatusAttribute(): string
    {
        return EventStatus::normalize($this->event_status);
    }

    public function getOperatingHoursAttribute($value): ?float
    {
        if ($this->upcasting_date && $this->upcasting_time) {
            return ProductionRuntimeService::hours($this->getAttributes());
        }
        return $value !== null ? (float) $value : null;
    }
}
