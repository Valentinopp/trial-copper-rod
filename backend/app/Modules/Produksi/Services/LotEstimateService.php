<?php

namespace App\Modules\Produksi\Services;

use App\Modules\Produksi\Enums\EventStatus;
use Carbon\Carbon;

class LotEstimateService
{
    public const CYCLE_HOURS = 70;

    public static function preview(array $row, ?Carbon $asOf = null): array
    {
        // The form shows theoretical output even when weighed actual has been entered.
        // Completed LOTs are still excluded from running WIP by calculate().
        unset($row['weight_kg'], $row['production_actual_kg']);

        return self::calculate($row, $asOf);
    }

    public static function calculate(array $row, ?Carbon $asOf = null): array
    {
        $speed = OutputFormulaService::speedMetersPerMinute($row['take_up_speed'] ?? null);
        $status = EventStatus::normalize($row['event_status'] ?? '');
        $weighed = isset($row['production_actual_kg']) || (isset($row['weight_kg'])
            && !empty($row['actual_pickup_date']) && !empty($row['actual_pickup_time']));
        if ($status === 'putus' || $weighed) {
            return ['current_kg' => 0.0, 'current_mt' => 0.0, 'completion_kg' => 0.0, 'hours' => 0.0,
                'speed_m_per_minute' => $speed, 'length_m' => 0.0];
        }
        // Selecting Panen before filling pickup time must not hide the running estimate.
        if ($status === 'panen' && empty($row['actual_pickup_date']) && empty($row['actual_pickup_time'])) {
            $row['event_status'] = '';
        }
        $hours = ProductionRuntimeService::hours($row, $asOf);
        $weight = isset($row['rod_weight_ton_per_meter']) ? (float) $row['rod_weight_ton_per_meter']
            : OutputFormulaService::rodWeight($row['rod_diameter_mm'] ?? null);
        $rate = OutputFormulaService::coefficientForRod($row['rod_diameter_mm'] ?? null,
            $row['take_up_speed'] ?? null, $weight);
        if ($hours === null || $rate === null || $rate <= 0) {
            return ['current_kg' => null, 'current_mt' => null, 'completion_kg' => null, 'hours' => $hours,
                'speed_m_per_minute' => $speed, 'length_m' => null];
        }
        $duration = min($hours, self::CYCLE_HOURS);
        $lengthMeters = $speed * $duration * 60;
        $massMt = $lengthMeters * $weight;
        return ['current_kg' => $massMt * 1000, 'current_mt' => $massMt,
            'completion_kg' => $rate * self::CYCLE_HOURS * 1000, 'hours' => $hours,
            'speed_m_per_minute' => $speed, 'length_m' => $lengthMeters];
    }
}
