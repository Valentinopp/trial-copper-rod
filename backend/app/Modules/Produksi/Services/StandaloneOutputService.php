<?php

namespace App\Modules\Produksi\Services;

use App\Modules\Produksi\Models\BoardEntry;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class StandaloneOutputService
{
    public function dailyActual(string $from, string $until): Collection
    {
        Gate::authorize('access-produksi');
        // The production day starts at 08:00. Pickups before 08:00 belong to the previous day.
        $rows = BoardEntry::whereNotNull('production_actual_kg')->whereIn('event_status', ['panen', 'putus'])
            ->whereBetween('actual_pickup_date', [$from, Carbon::parse($until)->addDay()->format('Y-m-d')])->get();
        $totals = [];
        foreach ($rows as $row) {
            $pickup = ProductionRuntimeService::timestamp($row->actual_pickup_date, $row->actual_pickup_time);
            if (!$pickup || $pickup->gt(Carbon::now(ProductionRuntimeService::TIMEZONE))) continue;
            $date = ProductionRuntimeService::productionSlot($pickup)['date'];
            if ($date < $from || $date > $until) continue;
            $totals[$date] = ($totals[$date] ?? BigDecimal::of(0))->plus($row->production_actual_kg);
        }

        return collect($totals)->map(fn (BigDecimal $kg) => [
            'plan_count' => 0, 'unconverted_plans' => 0, 'plan_mt' => null,
            'actual_mt' => (string) $kg->dividedBy(1000, 9, RoundingMode::HalfUp),
            'difference_mt' => null, 'plan_achievement_pct' => null,
        ]);
    }
}
