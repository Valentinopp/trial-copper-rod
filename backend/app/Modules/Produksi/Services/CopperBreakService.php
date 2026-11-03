<?php

namespace App\Modules\Produksi\Services;

use App\Modules\Produksi\Enums\EventStatus;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Models\CopperBreakEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CopperBreakService
{
    /**
     * Hitung lifetime dari Upcasting sampai Aktual Pick Up di Papan Digital (app.py: _calculate_copper_lifetime_hours)
     */
    public static function calculateLifetimeHours(array $item): ?float
    {
        $startDate = trim((string) ($item['upcasting_date'] ?? ''));
        $startTime = trim((string) ($item['upcasting_time'] ?? ''));
        $endDate = trim((string) ($item['actual_pickup_date'] ?? ''));
        $endTime = trim((string) ($item['actual_pickup_time'] ?? ''));

        if (! $startDate || ! $startTime || ! $endDate || ! $endTime) {
            return null;
        }

        try {
            $start = Carbon::createFromFormat('Y-m-d H:i', $startDate.' '.substr($startTime, 0, 5));
            $end = Carbon::createFromFormat('Y-m-d H:i', $endDate.' '.substr($endTime, 0, 5));
        } catch (\Throwable) {
            return null;
        }

        $elapsedHours = $start->diffInSeconds($end, false) / 3600.0;
        if ($elapsedHours < 0) {
            return null;
        }

        return round($elapsedHours, 4);
    }

    /**
     * Ambil daftar kejadian Copper Putus (LEFT JOIN board_entries & copper_break_entries)
     */
    public function listEntries(?string $startDate = null, ?string $endDate = null, string $q = '', int $limit = 2000): array
    {
        $query = DB::table('board_entries as be')
            ->leftJoin('copper_break_entries as cb', 'cb.board_entry_id', '=', 'be.id')
            ->whereIn('be.event_status', ['putus', 'putus_tarik_lot_baru']);

        if ($startDate) {
            $query->where('be.board_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('be.board_date', '<=', $endDate);
        }

        $trimmedQ = trim($q);
        if ($trimmedQ !== '') {
            $wildcard = "%{$trimmedQ}%";
            $query->where(function ($sub) use ($wildcard) {
                $sub->where('be.take_up', 'like', $wildcard)
                    ->orWhere('be.cooling', 'like', $wildcard)
                    ->orWhere('be.crystallizer', 'like', $wildcard)
                    ->orWhere('be.lot', 'like', $wildcard)
                    ->orWhere('be.remarks', 'like', $wildcard)
                    ->orWhere('cb.supervisor', 'like', $wildcard)
                    ->orWhere('cb.event_at_break', 'like', $wildcard)
                    ->orWhere('cb.event_after_break', 'like', $wildcard)
                    ->orWhere('cb.category', 'like', $wildcard);
            });
        }

        $total = (clone $query)->count();

        $rows = $query
            ->select([
                'be.id as id',
                'be.id as board_entry_id',
                'cb.id as detail_id',
                'be.board_date as event_date',
                DB::raw("COALESCE(NULLIF(be.actual_pickup_time, ''), '') as event_time"),
                DB::raw("CASE WHEN be.shift LIKE 'S%' THEN SUBSTR(be.shift, 2) ELSE COALESCE(be.shift, '') END as shift"),
                DB::raw("COALESCE(cb.supervisor, '') as supervisor"),
                'cb.supervisor_user_id',
                DB::raw("COALESCE(be.crystallizer, '') as crystallizer"),
                DB::raw("COALESCE(cb.event_at_break, '') as event_at_break"),
                DB::raw("COALESCE(cb.event_after_break, '') as event_after_break"),
                DB::raw("COALESCE(cb.category, '') as category"),
                'be.weight_kg as weight_kg',
                'cb.frequency as frequency',
                DB::raw("COALESCE(cb.remarks, '') as remarks"),
                'be.event_status as event_status',
                'be.take_up as take_up',
                'be.cooling as cooling',
                'be.servo as servo',
                'be.lot as lot',
                'be.upcasting_date as upcasting_date',
                'be.upcasting_time as upcasting_time',
                'be.actual_pickup_date as actual_pickup_date',
                'be.actual_pickup_time as actual_pickup_time',
                'be.operating_hours as operating_hours',
                'be.remarks as board_remarks',
                'be.created_at as board_created_at',
                'cb.created_at as detail_created_at',
                'cb.updated_at as detail_updated_at',
            ])
            ->orderByDesc('be.board_date')
            ->orderByDesc('be.id')
            ->limit($limit)
            ->get();

        $boardRows = BoardEntry::whereIn('id', $rows->pluck('board_entry_id'))->get();
        $sequences = app(LotSequenceService::class)->decorate($boardRows)->keyBy('id');
        $items = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $item['lot'] = $sequences->get($row->board_entry_id)?->lot_sequence ?: $item['lot'];
            $item['event_status'] = EventStatus::normalize($item['event_status']);
            $item['lifetime_hours'] = self::calculateLifetimeHours($item);
            $item['detail_complete'] = (bool) ($item['supervisor'] !== '' && $item['event_at_break'] !== '' && $item['category'] !== '');
            $items[] = $item;
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Upsert detail tambahan Copper Putus ke satu catatan Papan Digital
     */
    public function upsertDetail(int $boardEntryId, array $data): CopperBreakEntry
    {
        Gate::authorize('access-produksi');
        $data = Validator::make($data, [
            'supervisor_user_id' => 'required|string|max:20',
            'event_at_break' => 'nullable|string|max:255',
            'event_after_break' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'frequency' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string|max:2000',
        ])->validate();

        return DB::transaction(function () use ($boardEntryId, $data) {
            $source = BoardEntry::lockForUpdate()->findOrFail($boardEntryId);
            $status = $source->normalized_event_status;
            if ($status !== 'putus') {
                throw new \InvalidArgumentException('Copper Putus hanya tersedia untuk status Putus');
            }

            $shiftClean = ltrim((string) $source->shift, 'sS') ?: null;
            $original = CopperBreakEntry::where('board_entry_id', $boardEntryId)->lockForUpdate()->first();
            $masters = app(ProductionMasterDataService::class);
            $references = [];
            $supervisor = app(ProductionSupervisorService::class)->resolve($data['supervisor_user_id'], $original);
            foreach (['event_at_break' => 'break-events', 'event_after_break' => 'break-conditions', 'category' => 'break-categories'] as $field => $section) {
                $references[$field.'_master_id'] = $masters->resolve($section, (string) ($data[$field] ?? ''), $field,
                    $original ? (string) $original->$field : null, $original?->{$field.'_master_id'}, false)?->id;
            }

            return CopperBreakEntry::updateOrCreate(
                ['board_entry_id' => $boardEntryId],
                [
                    'event_date' => $source->board_date,
                    'event_time' => $source->actual_pickup_time ?: '',
                    'shift' => $shiftClean,
                    'crystallizer' => $source->crystallizer ?: '',
                    'event_at_break' => trim((string) ($data['event_at_break'] ?? '')),
                    'event_after_break' => trim((string) ($data['event_after_break'] ?? '')),
                    'category' => trim((string) ($data['category'] ?? '')),
                    'weight_kg' => $source->weight_kg,
                    'frequency' => isset($data['frequency']) && $data['frequency'] !== '' ? (int) $data['frequency'] : null,
                    'remarks' => trim((string) ($data['remarks'] ?? '')),
                ] + $references + $supervisor
            );
        });
    }

    /**
     * Hitung pengelompokan frekuensi untuk grafik CopperBreakBars
     */
    public static function countGroups(array $items, string $key): array
    {
        $groups = [];
        foreach ($items as $item) {
            $label = trim((string) ($item[$key] ?? ''));
            if ($label === '') {
                continue;
            }
            $groups[$label] = ($groups[$label] ?? 0) + 1;
        }

        $result = [];
        foreach ($groups as $label => $count) {
            $result[] = ['label' => $label, 'count' => $count];
        }

        usort($result, function ($a, $b) {
            if ($b['count'] !== $a['count']) {
                return $b['count'] <=> $a['count'];
            }

            return strnatcasecmp($a['label'], $b['label']);
        });

        return $result;
    }
}
