<?php

namespace App\Modules\Produksi\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class MonthlyOutputReportService
{
    public function days(array $days): array
    {
        $plan = BigDecimal::of(0);
        $actual = BigDecimal::of(0);
        $complete = true;
        foreach ($days as &$day) {
            $complete = $complete && $day['unconverted_plans'] === 0;
            $plan = $plan->plus($day['plan_mt'] ?? 0);
            $actual = $actual->plus($day['actual_mt'] ?? 0);
            $day['plan_mtd_mt'] = $complete ? (string) $plan : null;
            $day['actual_mtd_mt'] = (string) $actual;
            $day['plan_mtd_achievement_pct'] = $complete && $plan->isGreaterThan(0)
                ? (string) $actual->multipliedBy(100)->dividedBy($plan, 2, RoundingMode::HalfUp) : null;
            // Average equivalent lines operating over three eight-hour shifts.
            $day['total_line'] = array_sum(array_column($day['shifts'], 'total_hours')) / 24;
        }
        unset($day);

        return $days;
    }

    public function rows(array $days): array
    {
        $definitions = [
            ['total_line', 'TOTAL LINE', '', 'normal'],
            ['plan_mt', 'TARGET OUTPUT (MT)', '', 'normal'],
            ['plan_mtd_mt', 'TARGET MTD (MT)', '', 'normal'],
            ['actual_mt', 'AKTUAL OUTPUT (MT)', '', 'actual'],
            ['actual_mtd_mt', 'AKTUAL MTD (MT)', '', 'actual'],
            ['output_day_mt', 'Output/Day (MT)', '', 'calculated'],
            ['output_mtd_mt', 'Month to Date (MT)', '', 'normal'],
            ['plan_achievement_pct', 'Achievement (%)', '', 'percentage'],
            ['difference_mt', 'Selisih Aktual − Plan (MT)', '', 'difference'],
        ];
        if (config('copper_rod.standalone')) {
            $definitions = array_values(array_filter($definitions, fn ($row) => !in_array($row[0],
                ['plan_mt', 'plan_mtd_mt', 'plan_achievement_pct', 'difference_mt'], true)));
        }
        $rows = array_map(fn ($definition) => [
            'key' => $definition[0], 'label' => $definition[1], 'shift' => $definition[2], 'kind' => $definition[3],
            'values' => array_column($days, $definition[0]),
        ], $definitions);
        foreach (ServoInferenceService::SHIFTS as $index => $shift) {
            $rows[] = ['key' => $shift, 'label' => 'Output/Shift (MT)', 'shift' => (string) ($index + 1), 'kind' => 'shift',
                'values' => array_map(fn ($day) => $day['shifts'][$shift]['output_mt'], $days)];
        }

        return $rows;
    }

    public static function format(mixed $value, bool $percentage = false): string
    {
        return $value === null ? '—' : number_format((float) $value, 2, ',', '.').($percentage ? '%' : '');
    }
}
