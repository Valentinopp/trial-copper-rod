<?php

namespace App\Modules\Produksi\Services;

use App\Modules\Produksi\Models\BoardEntry;
use Carbon\Carbon;

class CrystallizerAnalyticsService
{
    /**
     * Setara dengan GET /api/crystallizer-summary di app.py
     */
    public function getSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $query = BoardEntry::query()
            ->whereNotNull('crystallizer')
            ->whereRaw("TRIM(crystallizer) <> ''");

        if ($startDate) {
            $query->where('board_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('board_date', '<=', $endDate);
        }

        $rows = $query->orderByDesc('board_date')->orderByDesc('id')->get();
        app(LotSequenceService::class)->decorate($rows);

        $details = [];
        $grouped = [];
        $incompleteCount = 0;

        foreach ($rows as $item) {
            $crystallizer = trim((string) $item->crystallizer);

            if (!($item->upcasting_date && $item->upcasting_time && $item->actual_pickup_date && $item->actual_pickup_time)) {
                $incompleteCount++;
            }

            $startDt = null;
            $endDt = null;

            if ($item->upcasting_date && $item->upcasting_time) {
                try {
                    $startDt = Carbon::createFromFormat('Y-m-d H:i', $item->upcasting_date . ' ' . substr($item->upcasting_time, 0, 5));
                } catch (\Throwable) {
                    $startDt = null;
                }
            }

            if ($item->actual_pickup_date && $item->actual_pickup_time) {
                try {
                    $endDt = Carbon::createFromFormat('Y-m-d H:i', $item->actual_pickup_date . ' ' . substr($item->actual_pickup_time, 0, 5));
                } catch (\Throwable) {
                    $endDt = null;
                }
            }

            $elapsed = null;
            if ($startDt && $endDt) {
                if ($endDt->lt($startDt)) {
                    if ($item->actual_pickup_date && $item->upcasting_date && $item->actual_pickup_date !== $item->upcasting_date) {
                        $elapsed = null;
                    } else {
                        $endDt->addDay();
                    }
                }
                if ($elapsed === null && $endDt->gte($startDt)) {
                    $elapsed = max(0.0, $startDt->diffInSeconds($endDt, false) / 3600.0);
                }
            }

            $hasLifetimeStamp = (bool) ($startDt || $endDt);
            if ($hasLifetimeStamp) {
                $lifetimeDays = $elapsed !== null ? (int) floor($elapsed / 24) : null;
                $lifetimeRemainderHours = $elapsed !== null ? round(fmod($elapsed, 24), 4) : null;

                $details[] = [
                    'id'                   => $item->id,
                    'board_date'           => $item->board_date,
                    'shift'                => $item->shift,
                    'crystallizer'         => $crystallizer,
                    'take_up'              => $item->take_up ?: '',
                    'start_date'           => $item->upcasting_date ?: '',
                    'start_time'           => $item->upcasting_time ?: '',
                    'stop_date'            => $item->actual_pickup_date ?: '',
                    'stop_time'            => $item->actual_pickup_time ?: '',
                    'lifetime_days'        => $lifetimeDays,
                    'lifetime_hours_part'  => $lifetimeRemainderHours,
                    'lifetime_total_hours' => $elapsed !== null ? round($elapsed, 4) : null,
                    'operating_hours'      => $item->operating_hours,
                    'weight_kg'            => $item->weight_kg,
                    'remarks'              => $item->remarks ?: '',
                    'lot'                  => $item->lot_sequence ?: ($item->lot ?: ''),
                    'lot_position'         => $item->lot_position,
                    'event_status'         => $item->normalized_event_status,
                ];
            }

            if ($elapsed !== null) {
                if (!isset($grouped[$crystallizer])) {
                    $grouped[$crystallizer] = [
                        'crystallizer' => $crystallizer,
                        'total_hours'  => 0.0,
                        'frequency'    => 0,
                        'rows'         => 0,
                        'putus_count'  => 0,
                    ];
                }
                $grouped[$crystallizer]['total_hours'] += $elapsed;
                $grouped[$crystallizer]['frequency'] += 1;
                $grouped[$crystallizer]['rows'] += 1;
                if ($item->normalized_event_status === 'putus') {
                    $grouped[$crystallizer]['putus_count'] += 1;
                }
            }
        }

        $summary = [];
        foreach ($grouped as $bucket) {
            $totalHours = $bucket['total_hours'];
            $freq = $bucket['frequency'];
            $summary[] = [
                'crystallizer'           => $bucket['crystallizer'],
                'total_hours'            => round($totalHours, 4),
                'frequency'              => $freq,
                'average_lifetime_hours' => $freq > 0 ? round($totalHours / $freq, 4) : null,
                'board_rows'             => $bucket['rows'],
                'putus_count'            => $bucket['putus_count'],
                'putus_percentage'       => $freq > 0 ? round($bucket['putus_count'] / $freq * 100, 2) : 0.0,
            ];
        }

        usort($summary, function ($a, $b) {
            if ($b['total_hours'] !== $a['total_hours']) {
                return $b['total_hours'] <=> $a['total_hours'];
            }
            return strnatcasecmp((string) $b['crystallizer'], (string) $a['crystallizer']);
        });

        $totalOperatingHours = round(array_sum(array_column($summary, 'total_hours')), 4);

        return [
            'items'                    => $details,
            'summary'                  => $summary,
            'total_crystallizers'      => count($summary),
            'total_operating_hours'    => $totalOperatingHours,
            'note'                     => 'Rekap hanya menghitung siklus lifetime crystallizer yang lengkap: nomor crystallizer serta tanggal/jam Upcasting dan Aktual Pick Up yang valid. Total Jam adalah durasi antara mulai dan akhir; Frek. Pasang adalah jumlah siklus lengkap. Jam Operasi shift tidak digunakan sebagai pengganti lifetime.',
            'incomplete_lifetime_rows' => $incompleteCount,
        ];
    }
}
