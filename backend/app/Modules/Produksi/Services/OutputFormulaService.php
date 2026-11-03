<?php

namespace App\Modules\Produksi\Services;

use Illuminate\Support\Facades\DB;

class OutputFormulaService
{
    public const ROD_WEIGHTS = ['8' => 0.00045, '12.5' => 0.0011, '20' => 0.0028];

    public static function rodWeight(float|string|null $diameter): ?float
    {
        return is_numeric($diameter) ? (self::ROD_WEIGHTS[(string) (float) $diameter] ?? null) : null;
    }

    public static function coefficientForRod(float|string|null $diameter, float|string|null $speed, ?float $snapshotWeight = null): ?float
    {
        $weight = $snapshotWeight ?? self::rodWeight($diameter);
        $metersPerMinute = self::speedMetersPerMinute($speed);
        if ($weight === null || !is_finite($weight) || $weight <= 0 || $metersPerMinute === null) {
            return null;
        }
        // Excel: MT/m × m/minute × 60 minutes/hour = MT/hour.
        return $weight * $metersPerMinute * 60;
    }

    public static function speedMetersPerMinute(float|string|null $speed): ?float
    {
        return is_numeric($speed) && is_finite((float) $speed) && (float) $speed > 0
            ? (float) $speed / 1000 : null;
    }

    // Historical rows without diameter/speed retain their previous calculation.
    public static function parameters(): array
    {
        $row = DB::table('output_formula_settings')->where('id', 1)->first();

        return [
            'a' => $row ? (float) $row->factor_a : 0.00045,
            'b' => $row ? (float) $row->factor_b : 1.59,
        ];
    }

    public static function coefficient(): float
    {
        $parameters = self::parameters();

        return $parameters['a'] * $parameters['b'] * 60;
    }

    public static function formula(): string
    {
        return 'Output shift (MT) = jumlah output tiap LOT (berat rod (MT/m) × (Take Up Speed (mm/menit) ÷ 1.000) × 60 × jam kerja pada shift)';
    }
}
