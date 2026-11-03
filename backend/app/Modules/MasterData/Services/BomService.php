<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Bom;
use App\Modules\MasterData\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BomService
{
    public function items(string $category): Builder
    {
        return Item::with(['category.parent', 'unit.related'])
            ->whereHas('category', function ($query) use ($category) {
                $query->whereRaw('LOWER(name) = ?', [$category]);
                if ($category === 'material') {
                    $query->orWhereHas('parent', fn ($parent) => $parent->whereRaw('LOWER(name) = ?', ['material']));
                }
            })
            ->orderBy('code');
    }

    public function save(array $data, ?int $id = null): Bom
    {
        Gate::authorize('access-master-data');
        $data['code'] = mb_strtoupper(trim((string) ($data['code'] ?? '')));
        $data['name'] = preg_replace('/\s+/u', ' ', trim((string) ($data['name'] ?? '')));
        $valid = Validator::make($data, [
            'code' => ['required', 'string', 'max:60', Rule::unique('master_data_boms', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'integer', 'exists:master_data_items,id'],
            'output_quantity' => ['required', 'numeric', 'min:0.000001', 'max:999999999', 'decimal:0,6'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.material_id' => ['required', 'integer', 'distinct', 'different:product_id', 'exists:master_data_items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.000001', 'max:999999999', 'decimal:0,6'],
        ], [], [
            'product_id' => 'produk hasil', 'output_quantity' => 'jumlah hasil acuan',
            'lines' => 'material penyusun', 'lines.*.material_id' => 'material', 'lines.*.quantity' => 'jumlah material',
        ])->validate();

        return DB::transaction(function () use ($valid, $id) {
            $bom = $id ? Bom::lockForUpdate()->findOrFail($id) : new Bom;
            $this->validateItem((int) $valid['product_id'], 'produk', 'product_id', (bool) $valid['is_active']);
            foreach ($valid['lines'] as $index => $line) {
                $this->validateItem((int) $line['material_id'], 'material', "lines.$index.material_id", (bool) $valid['is_active']);
            }
            $bom->fill(collect($valid)->except('lines')->all())->save();
            $bom->lines()->delete();
            $bom->lines()->createMany($valid['lines']);

            return $bom;
        });
    }

    public function validateItem(int $id, string $category, string $field, bool $active): void
    {
        $item = $this->items($category)->whereKey($id)->lockForUpdate()->first();
        if (! $item) {
            throw ValidationException::withMessages([$field => "Pilih barang dengan kategori $category dari Master Barang."]);
        }
        if ($active && (! $item->is_active || ! $item->category->is_active ||
            ($item->category->parent && ! $item->category->parent->is_active) ||
            ! $item->unit->is_active || ($item->unit->related && ! $item->unit->related->is_active))) {
            throw ValidationException::withMessages([$field => 'Barang, kategori, dan UoM harus aktif untuk BoM aktif.']);
        }
    }
}
