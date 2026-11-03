<?php

namespace App\Modules\Produksi\Services;

use App\Modules\Produksi\Models\BoardEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OutputSummaryService
{
    public static function summarizeShiftEntries(array $rows, ?float $coefficient = null): array
    {
        $hoursY = 0.0;
        $hoursX = 0.0;
        $outputMt = 0.0;
        $coefficient ??= OutputFormulaService::coefficient();

        foreach ($rows as $r) {
            if (($r['servo'] ?? '') === 'Y') {
                $hoursY += (float) ($r['hours'] ?? 0);
            } elseif (($r['servo'] ?? '') === 'X') {
                $hoursX += (float) ($r['hours'] ?? 0);
            } else {
                continue;
            }
            $outputMt += (float) ($r['hours'] ?? 0) * ($r['coefficient'] ?? $coefficient);
        }

        $totalHours = $hoursY + $hoursX;

        return [
            'hours_y'             => round($hoursY, 4),
            'hours_x'             => round($hoursX, 4),
            'total_hours'         => round($totalHours, 4),
            'line_equivalent'     => round($totalHours / ServoInferenceService::HOURS_PER_SHIFT, 4),
            'utilization_pct'     => round(($totalHours / (ServoInferenceService::STANDARD_LINES * ServoInferenceService::HOURS_PER_SHIFT)) * 100, 4),
            'output_mt'           => round($outputMt, 7),
        ];
    }

    /**
     * Setara dengan GET /api/output-summary di app.py
     */
    public function getSummary(string $startDate, string $endDate, float $dailyTargetMt = ServoInferenceService::DEFAULT_DAILY_TARGET_MT): array
    {
        $start = Carbon::createFromFormat('!Y-m-d', $startDate, ProductionRuntimeService::TIMEZONE);
        $legacyCoefficient = OutputFormulaService::coefficient();
        $end = Carbon::createFromFormat('!Y-m-d', $endDate, ProductionRuntimeService::TIMEZONE);

        $monthStart = $start->copy()->startOfMonth();
        $reportFrom = $monthStart->copy()->setTime(8, 0);
        $reportUntil = $end->copy()->addDay()->setTime(8, 0);
        $asOf = Carbon::now(ProductionRuntimeService::TIMEZONE);
        $grouped = [];
        $skippedBoardRows = 0;
        $legacySpecificationRows = 0;

        $boardRows = BoardEntry::query()
            ->where(function ($query) use ($monthStart, $end, $reportFrom, $reportUntil) {
                $query->where(function ($timed) use ($reportFrom, $reportUntil) {
                    $timed->where('upcasting_date', '<>', '')
                        ->where('upcasting_date', '<=', $reportUntil->format('Y-m-d'))
                        ->where(function ($finish) use ($reportFrom) {
                            $finish->whereNull('actual_pickup_date')->orWhere('actual_pickup_date', '')
                                ->orWhere('actual_pickup_date', '>=', $reportFrom->format('Y-m-d'));
                        });
                })->orWhereBetween('board_date', [$monthStart->format('Y-m-d'), $end->format('Y-m-d')]);
            })
            ->orderBy('board_date')
            ->orderBy('shift')
            ->orderBy('id')
            ->get();

        foreach ($boardRows as $row) {
            $hasStart = $row->upcasting_date && $row->upcasting_time;
            $parts = $hasStart
                ? ProductionRuntimeService::split($row->getAttributes(), $reportFrom, $reportUntil, $asOf)
                : [];
            if (!$hasStart && $row->operating_hours !== null && in_array($row->shift, ServoInferenceService::SHIFTS, true)) {
                $parts[] = ['date' => $row->board_date, 'shift' => $row->shift, 'hours' => $row->operating_hours];
            }
            if (!$parts) {
                continue;
            }
            $classification = $row->servo_classification;
            $servo = $classification['servo_conflict'] ? null : $classification['servo'];
            if (!$servo) {
                $skippedBoardRows++;
                continue;
            }
            $coefficient = $row->rod_diameter_mm === null && $row->take_up_speed === null
                ? $legacyCoefficient
                : OutputFormulaService::coefficientForRod($row->rod_diameter_mm, $row->take_up_speed, $row->rod_weight_ton_per_meter);
            if ($row->rod_diameter_mm === null && $row->take_up_speed === null) {
                $legacySpecificationRows++;
            }
            if ($coefficient === null) {
                continue;
            }
            foreach ($parts as $part) {
                $key = $part['date'].'|'.$part['shift'];
                $grouped[$key][] = ['servo' => $servo, 'hours' => $part['hours'], 'coefficient' => $coefficient];
            }
        }

        // Kompatibilitas catatan shift legacy
        $legacyRows = DB::table('shift_entries as se')
            ->leftJoin('board_entries as be', 'be.id', '=', 'se.board_entry_id')
            ->where('se.work_date', '>=', $monthStart->format('Y-m-d'))
            ->where('se.work_date', '<=', $end->format('Y-m-d'))
            ->where(function ($q) {
                $q->whereNull('be.id')
                  ->orWhere(function ($source) {
                      $source->whereNull('be.operating_hours')
                          ->where(function ($missing) {
                              $missing->whereNull('be.upcasting_date')->orWhere('be.upcasting_date', '')
                                  ->orWhereNull('be.upcasting_time')->orWhere('be.upcasting_time', '');
                          });
                  });
            })
            ->select('se.*', 'be.take_up_speed as board_take_up_speed', 'be.rod_diameter_mm as board_rod_diameter_mm', 'be.rod_weight_ton_per_meter as board_rod_weight')
            ->orderBy('se.work_date')
            ->orderBy('se.shift')
            ->orderBy('se.id')
            ->get();

        foreach ($legacyRows as $row) {
            $coefficient = $row->board_rod_diameter_mm === null && $row->board_take_up_speed === null
                ? $legacyCoefficient
                : OutputFormulaService::coefficientForRod($row->board_rod_diameter_mm, $row->board_take_up_speed, $row->board_rod_weight !== null ? (float) $row->board_rod_weight : null);
            if ($row->board_rod_diameter_mm === null && $row->board_take_up_speed === null) {
                $legacySpecificationRows++;
            }
            if ($coefficient === null) {
                continue;
            }
            $key = $row->work_date . '|' . $row->shift;
            $grouped[$key][] = [
                'servo' => $row->servo,
                'hours' => (float) ($row->hours ?? 0),
                'coefficient' => $coefficient,
            ];
        }

        $days = [];
        $cursorDate = $monthStart->copy();
        $outputMtd = 0.0;

        while ($cursorDate->lte($end)) {
            $dateStr = $cursorDate->format('Y-m-d');
            $shiftSummaries = [];
            $dayOutput = 0.0;

            foreach (ServoInferenceService::SHIFTS as $shift) {
                $key = $dateStr . '|' . $shift;
                $summary = self::summarizeShiftEntries($grouped[$key] ?? [], $legacyCoefficient);
                $shiftSummaries[$shift] = $summary;
                $dayOutput += $summary['output_mt'];
            }

            $outputMtd += $dayOutput;
            $targetMtd = $dailyTargetMt * $cursorDate->day;

            $days[] = [
                'date'            => $dateStr,
                'shifts'          => $shiftSummaries,
                'output_day_mt'   => round($dayOutput, 7),
                'target_day_mt'   => round($dailyTargetMt, 6),
                'output_mtd_mt'   => round($outputMtd, 7),
                'target_mtd_mt'   => round($targetMtd, 6),
                'achievement_pct' => $dailyTargetMt > 0 ? round(($dayOutput / $dailyTargetMt) * 100, 4) : null,
            ];

            $cursorDate->addDay();
        }

        return [
            'start_date'                      => $monthStart->format('Y-m-d'),
            'end_date'                        => $end->format('Y-m-d'),
            'daily_target_mt'                 => $dailyTargetMt,
            'days'                            => $days,
            'formula'                         => OutputFormulaService::formula(),
            'note'                            => 'Output teoritis dihitung dari Upcasting sampai Aktual Pick Up, atau sampai sekarang bila masih berjalan. Durasi dibagi ke S1 08.00–16.00, S2 16.00–00.00, S3 00.00–08.00. Shift 3 masuk tanggal produksi sebelumnya. Catatan jam manual lama tetap didukung bila belum memiliki waktu upcasting.',
            'skipped_unclassified_board_rows' => $skippedBoardRows,
            'legacy_specification_rows' => $legacySpecificationRows,
        ];
    }
}
