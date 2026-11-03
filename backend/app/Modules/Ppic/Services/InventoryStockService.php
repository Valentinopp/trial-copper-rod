<?php

namespace App\Modules\Ppic\Services;

use App\Modules\MasterData\Models\Item;
use App\Modules\Ppic\Models\InventoryIssueLine;
use App\Modules\Ppic\Models\InventoryReceipt;
use App\Modules\Ppic\Models\MaterialReservation;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;

class InventoryStockService
{
    public function balance(int $itemId, int $uomId, bool $lock = false): array
    {
        // Locking reads see the latest committed rows even under MySQL REPEATABLE READ.
        // Material rows must already be locked in ascending order by the calling transaction.
        $received = $this->total(InventoryReceipt::where('product_id', $itemId)->where('uom_id', $uomId), 'quantity', $lock);
        $issued = $this->total(InventoryIssueLine::where('product_id', $itemId)->where('uom_id', $uomId)->where('issued_quantity', '>', 0), 'issued_quantity', $lock);
        $reserved = $this->total(MaterialReservation::where('material_id', $itemId)->where('uom_id', $uomId), 'quantity', $lock);
        $physical = $received->minus($issued);

        return ['received' => (string) $received->toScale(6), 'issued' => (string) $issued->toScale(6),
            'physical' => (string) $physical->toScale(6), 'reserved' => (string) $reserved->toScale(6),
            'available' => (string) BigDecimal::max(0, $physical->minus($reserved))->toScale(6)];
    }

    public function withBalances(Builder $query): Builder
    {
        return $query->withSum(['receipts' => fn ($q) => $q->whereColumn('inventory_receipts.uom_id', 'master_data_items.uom_id')], 'quantity')
            ->withSum(['reservations' => fn ($q) => $q->whereColumn('production_material_reservations.uom_id', 'master_data_items.uom_id')], 'quantity')
            ->withSum(['issueLines as issue_lines_sum_quantity' => fn ($q) => $q->whereColumn('inventory_issue_lines.uom_id', 'master_data_items.uom_id')], 'issued_quantity');
    }

    public function total(Builder $query, string $column, bool $lock): BigDecimal
    {
        if (! $lock) {
            return BigDecimal::of($query->sum($column));
        }

        return $query->lockForUpdate()->get([$column])->reduce(fn ($sum, $row) => $sum->plus($row->$column), BigDecimal::zero());
    }

    public function physical(Item $item): string
    {
        return (string) BigDecimal::of($item->receipts_sum_quantity ?? 0)->minus($item->issue_lines_sum_quantity ?? 0)->toScale(6);
    }

    public function available(Item $item): string
    {
        return (string) BigDecimal::max(0, BigDecimal::of($this->physical($item))->minus($item->reservations_sum_quantity ?? 0))->toScale(6);
    }
}
