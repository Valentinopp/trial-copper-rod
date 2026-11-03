<?php

namespace App\Modules\Ppic\Services;

use App\Modules\MasterData\Models\Division;
use App\Modules\MasterData\Models\Item;
use App\Modules\Ppic\Models\InventoryIssue;
use App\Modules\Purchasing\Services\ProcurementService;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryIssueService
{
    public function save(array $data, string $token, ?int $id = null): InventoryIssue
    {
        Gate::authorize('ppic.ic.issues.create');
        $data = $this->validate($data);
        Validator::make(['token' => $token], ['token' => 'required|uuid'])->validate();
        try {
            return DB::transaction(function () use ($data, $token, $id) {
                // The unique token makes repeated draft submissions idempotent.
                $issue = $id ? InventoryIssue::lockForUpdate()->findOrFail($id)
                    : InventoryIssue::where('draft_token', $token)->lockForUpdate()->first();
                if ($issue && ! $id) {
                    return $issue;
                }
                if ($issue) {
                    $this->requireStatus($issue, 'draft');
                }
                $division = Division::findOrFail($data['division_id']);
                if (! $division->is_active) {
                    $this->fail('form.division_id', 'Pilih divisi aktif.');
                }
                $items = $this->lockItems(array_column($data['lines'], 'product_id'));
                $lines = [];
                foreach ($data['lines'] as $index => $line) {
                    $item = $items->get($line['product_id']);
                    if (! $item || ! app(ProcurementService::class)->materials()->whereKey($item->id)->exists()) {
                        $this->fail('form.lines.'.$index.'.product_id', 'Pilih barang persediaan aktif beserta kategori dan satuannya.');
                    }
                    $lines[] = ['product_id' => $item->id, 'uom_id' => $item->uom_id, 'product_code' => $item->code,
                        'product_name' => $item->name, 'uom_code' => $item->unit->code, 'quantity' => $line['quantity']];
                }
                unset($data['lines']);
                $values = $data + ['division_name' => $division->name, 'updated_by' => auth()->id()];
                if (! $issue) {
                    $issue = InventoryIssue::create($values + ['draft_token' => $token, 'created_by' => auth()->id()]);
                } else {
                    $issue->update($values);
                }
                $issue->lines()->delete();
                $issue->lines()->createMany($lines);

                return $issue->fresh('lines');
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent retry may have committed the same token first.
            if (! $id && ($existing = InventoryIssue::where('draft_token', $token)->first())) {
                return $existing;
            }
            throw $exception;
        }
    }

    public function confirm(int $id): InventoryIssue
    {
        Gate::authorize('ppic.ic.issues.confirm');

        return DB::transaction(function () use ($id) {
            $issue = InventoryIssue::with('lines')->lockForUpdate()->findOrFail($id);
            if ($issue->status === 'confirmed') {
                return $issue;
            }
            $this->requireStatus($issue, 'draft');
            if ($issue->issued_on->isFuture()) {
                $this->fail('issue', 'Tanggal pengeluaran tidak boleh di masa depan.');
            }
            if (! $issue->division?->is_active) {
                $this->fail('issue', 'Divisi peminta sudah tidak aktif.');
            }
            if ($issue->lines->isEmpty()) {
                $this->fail('issue', 'Dokumen harus memiliki minimal satu barang.');
            }
            $items = $this->lockItems($issue->lines->pluck('product_id')->all());
            foreach ($issue->lines as $line) {
                $item = $items->get($line->product_id);
                if (! $item || $item->uom_id !== $line->uom_id || ! app(ProcurementService::class)->materials()->whereKey($item->id)->exists()) {
                    $this->fail('issue', 'Barang/satuan '.$line->product_name.' berubah atau tidak aktif. Perbarui draft.');
                }
                $balance = app(InventoryStockService::class)->balance($item->id, $line->uom_id, true);
                if (BigDecimal::of($line->quantity)->isGreaterThan($balance['available'])) {
                    $this->fail('issue', 'Stok tersedia '.$line->product_name.' hanya '.$balance['available'].' '.$line->uom_code.'. Reservasi produksi tidak boleh digunakan.');
                }
                // Do not issue goods before the receipt date supporting the recorded stock.
                $received = app(InventoryStockService::class)->total($item->receipts()->getQuery()->where('uom_id', $line->uom_id)->whereDate('received_on', '<=', $issue->issued_on), 'quantity', true);
                $issued = BigDecimal::of($balance['issued']);
                if ($received->minus($issued)->isLessThan($line->quantity)) {
                    $this->fail('issue', 'Tanggal pengeluaran mendahului stok yang tersedia untuk '.$line->product_name.'.');
                }
            }
            $issue->update(['status' => 'confirmed', 'confirmed_by' => auth()->id(), 'confirmed_at' => now(), 'updated_by' => auth()->id()]);
            $issue->lines()->update(['issued_quantity' => DB::raw('quantity')]);

            return $issue;
        }, 3);
    }

    public function cancel(int $id): InventoryIssue
    {
        Gate::authorize('ppic.ic.issues.create');

        return DB::transaction(function () use ($id) {
            $issue = InventoryIssue::lockForUpdate()->findOrFail($id);
            if ($issue->status === 'cancelled') {
                return $issue;
            }
            $this->requireStatus($issue, 'draft');
            $issue->update(['status' => 'cancelled', 'cancelled_by' => auth()->id(), 'cancelled_at' => now(), 'updated_by' => auth()->id()]);

            return $issue;
        });
    }

    public function reverse(int $id, string $reason): InventoryIssue
    {
        Gate::authorize('ppic.ic.issues.reverse');
        $reason = trim($reason);
        Validator::make(['reversal_reason' => $reason], ['reversal_reason' => 'required|string|min:5|max:2000'])->validate();

        return DB::transaction(function () use ($id, $reason) {
            $issue = InventoryIssue::with('lines')->lockForUpdate()->findOrFail($id);
            if ($issue->status === 'reversed') {
                return $issue;
            }
            $this->requireStatus($issue, 'confirmed');
            $this->lockItems($issue->lines->pluck('product_id')->all());
            $issue->update(['status' => 'reversed', 'reversed_by' => auth()->id(), 'reversed_at' => now(),
                'reversal_reason' => $reason, 'updated_by' => auth()->id()]);
            $issue->lines()->update(['issued_quantity' => 0]);

            return $issue;
        }, 3);
    }

    private function validate(array $data): array
    {
        foreach (['recipient', 'asset', 'request_reference', 'notes'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = trim($data[$key]);
            }
        }
        $validator = Validator::make($data, [
            'issued_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'purpose' => 'required|in:maintenance,operational',
            'division_id' => 'required|integer|exists:master_data_divisions,id',
            'recipient' => 'required|string|max:255', 'asset' => 'nullable|string|max:255',
            'request_reference' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1|max:50', 'lines.*.product_id' => 'required|integer|distinct|exists:master_data_items,id',
            'lines.*.quantity' => 'required|numeric|min:0.000001|max:999999999|decimal:0,6',
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($v, $k) => ['form.'.$k => $v])->all());
        }

        return $validator->validated();
    }

    private function lockItems(array $ids)
    {
        // Same material-row lock order as Production Plan reservations.
        return Item::with('unit')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    private function requireStatus(InventoryIssue $issue, string $status): void
    {
        if ($issue->status !== $status) {
            $this->fail('issue', 'Status dokumen sudah berubah. Muat ulang sebelum melanjutkan.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
