<?php

namespace App\Modules\Produksi\Services;

use App\Modules\Produksi\Models\BoardEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LotSequenceService
{
    public function decorate(Collection $rows): Collection
    {
        $crystallizers = $rows->pluck('crystallizer')->map(fn ($value) => mb_strtoupper(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')->unique()->values()->all();
        $harvests = $crystallizers ? BoardEntry::query()
            ->whereIn(DB::raw('UPPER(TRIM(crystallizer))'), $crystallizers)
            ->where('actual_pickup_date', '<>', '')->where('actual_pickup_time', '<>', '')->get() : collect();
        $byEnd = [];
        foreach ($harvests as $row) {
            $start = ProductionRuntimeService::timestamp($row->upcasting_date, $row->upcasting_time);
            $end = ProductionRuntimeService::timestamp($row->actual_pickup_date, $row->actual_pickup_time);
            if ($row->normalized_event_status !== 'panen' || !$start || !$end || !$start->lt($end) || trim($row->lot) === '') {
                continue;
            }
            $key = $this->key($row->crystallizer, $end->format('Y-m-d H:i'));
            $byEnd[$key][] = $row;
        }

        $memo = [];
        $resolve = function (BoardEntry $row, array $visited = []) use (&$resolve, &$memo, $byEnd): array {
            if (isset($visited[$row->id])) {
                return ['lots' => [trim($row->lot)], 'complete' => false];
            }
            if (isset($memo[$row->id])) {
                return $memo[$row->id];
            }
            $visited[$row->id] = true;
            $result = ['lots' => [trim($row->lot)], 'complete' => true];
            $start = ProductionRuntimeService::timestamp($row->upcasting_date, $row->upcasting_time);
            $candidates = $start ? ($byEnd[$this->key($row->crystallizer, $start->format('Y-m-d H:i'))] ?? []) : [];
            $candidates = array_values(array_filter($candidates, fn ($candidate) => $candidate->id !== $row->id));
            if (count($candidates) === 1) {
                $result = $resolve($candidates[0], $visited);
                $result['lots'][] = trim($row->lot);
            } elseif (count($candidates) > 1) {
                // Do not invent a predecessor when historical records are ambiguous.
                $result['complete'] = false;
            }
            return $memo[$row->id] = $result;
        };

        foreach ($rows as $row) {
            $sequence = $resolve($row);
            $row->setAttribute('lot_sequence', implode(' ; ', array_filter($sequence['lots'], fn ($lot) => $lot !== '')));
            $row->setAttribute('lot_position', $sequence['complete'] ? count($sequence['lots']) : null);
        }
        return $rows;
    }

    private function key(?string $crystallizer, string $timestamp): string
    {
        return json_encode([mb_strtoupper(trim((string) $crystallizer)), $timestamp]);
    }
}
