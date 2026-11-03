<?php

namespace App\Modules\Produksi\Services;

use App\Modules\Produksi\Enums\EventStatus;
use Carbon\Carbon;

class ProductionRuntimeService
{
    public const TIMEZONE = 'Asia/Jakarta';
    public const END_STATUSES = ['panen', 'putus'];

    public static function timestamp(?string $date, ?string $time): ?Carbon
    {
        $value = trim((string) $date).' '.substr(trim((string) $time), 0, 5);
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            return null;
        }
        try {
            $stamp = Carbon::createFromFormat('!Y-m-d H:i', $value, self::TIMEZONE);
            return $stamp && $stamp->format('Y-m-d H:i') === $value ? $stamp : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function productionSlot(Carbon $time): array
    {
        $local = $time->copy()->setTimezone(self::TIMEZONE);
        return [
            'date' => ($local->hour < 8 ? $local->copy()->subDay() : $local)->format('Y-m-d'),
            'shift' => $local->hour < 8 ? 'S3' : ($local->hour < 16 ? 'S1' : 'S2'),
        ];
    }

    public static function interval(array $row, ?Carbon $asOf = null): ?array
    {
        $start = self::timestamp($row['upcasting_date'] ?? null, $row['upcasting_time'] ?? null);
        if (!$start) {
            return null;
        }
        $asOf = ($asOf ?? Carbon::now(self::TIMEZONE))->copy();
        $hasEnd = !empty($row['actual_pickup_date']) || !empty($row['actual_pickup_time']);
        $end = $hasEnd ? self::timestamp($row['actual_pickup_date'] ?? null, $row['actual_pickup_time'] ?? null) : null;
        // An incomplete stop record must not silently accrue hours forever.
        if (($hasEnd && !$end) || (!$hasEnd && in_array(EventStatus::normalize($row['event_status'] ?? ''), self::END_STATUSES, true))) {
            return null;
        }
        if ($end && $end->lt($start)) {
            return null;
        }
        $end = $end ? $end->min($asOf) : $asOf;
        return $end->gte($start) ? [$start, $end] : null;
    }

    public static function hours(array $row, ?Carbon $asOf = null): ?float
    {
        $interval = self::interval($row, $asOf);
        return $interval ? $interval[0]->diffInSeconds($interval[1], false) / 3600 : null;
    }

    public static function split(array $row, Carbon $from, Carbon $until, ?Carbon $asOf = null): array
    {
        $interval = self::interval($row, $asOf);
        if (!$interval) {
            return [];
        }
        $cursor = $interval[0]->copy()->max($from)->setTimezone(self::TIMEZONE);
        $end = $interval[1]->copy()->min($until);
        $parts = [];
        while ($cursor->lt($end)) {
            $slot = self::productionSlot($cursor);
            $boundary = match ($slot['shift']) {
                'S1' => $cursor->copy()->setTime(16, 0),
                'S2' => $cursor->copy()->addDay()->startOfDay(),
                'S3' => $cursor->copy()->setTime(8, 0),
            };
            $stop = $boundary->min($end);
            $parts[] = $slot + ['hours' => $cursor->diffInSeconds($stop, false) / 3600];
            $cursor = $stop;
        }
        return $parts;
    }
}
