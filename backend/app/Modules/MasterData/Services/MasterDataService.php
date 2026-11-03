<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\BomLine;
use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Unit;
use App\Modules\MasterData\Models\ProductionReference;
use App\Modules\Ppic\Models\PurchaseRequest;
use App\Modules\Ppic\Models\SaleMaterialRequirement;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MasterDataService
{
    public function saveCategory(array $data, ?int $id = null): Category
    {
        Gate::authorize('access-master-data');
        $data = $this->normalize($data);
        $data['parent_id'] = ($data['parent_id'] ?? '') ?: null;
        $valid = Validator::make($data, $this->commonRules('master_data_categories', $id, 30) + [
            'parent_id' => 'nullable|integer|exists:master_data_categories,id', 'description' => 'nullable|string|max:2000',
        ])->validate();

        return DB::transaction(function () use ($valid, $id) {
            $category = $id ? Category::lockForUpdate()->findOrFail($id) : new Category;
            if ($valid['parent_id']) {
                $parent = Category::lockForUpdate()->findOrFail($valid['parent_id']);
                if ($parent->id === $id || $parent->parent_id || ! $parent->is_active || ($id && $category->children()->exists())) {
                    $this->fail('parent_id', 'Pilih kategori utama yang aktif. Maksimal dua tingkat tanpa siklus.');
                }
            }
            $category->fill($valid)->save();

            return $category;
        });
    }

    public function saveUnit(array $data, ?int $id = null): Unit
    {
        Gate::authorize('access-master-data');
        $data = $this->normalize($data);
        $data['related_uom_id'] = ($data['related_uom_id'] ?? '') ?: null;
        $valid = Validator::make($data, $this->commonRules('master_data_units', $id, 30) + [
            'related_uom_id' => 'nullable|integer|exists:master_data_units,id',
            'factor' => 'required|numeric|gt:0|max:999999999|decimal:0,9',
        ])->validate();

        return DB::transaction(function () use ($valid, $id) {
            $unit = $id ? Unit::lockForUpdate()->findOrFail($id) : new Unit;
            if ($valid['related_uom_id']) {
                $base = Unit::lockForUpdate()->findOrFail($valid['related_uom_id']);
                if ($base->id === $id || $base->related_uom_id || ! $base->is_active || ($id && Unit::where('related_uom_id', $id)->exists())) {
                    $this->fail('related_uom_id', 'Pilih UoM utama yang aktif. Acuan tidak boleh menunjuk dirinya sendiri atau membentuk rantai.');
                }
            } elseif (BigDecimal::of($valid['factor'])->compareTo(1) !== 0) {
                $this->fail('factor', 'UoM tanpa acuan memakai faktor 1.');
            }
            $unit->fill($valid)->save();

            return $unit;
        });
    }

    public function saveItem(array $data, ?int $id = null): Item
    {
        Gate::authorize('access-master-data');
        if (array_key_exists('rod_specification_id', $data)) $data['rod_specification_id'] = $data['rod_specification_id'] ?: null;
        $valid = Validator::make($this->normalize($data), $this->commonRules('master_data_items', $id, 60) + [
            'category_id' => 'required|integer|exists:master_data_categories,id',
            'uom_id' => 'required|integer|exists:master_data_units,id', 'description' => 'nullable|string|max:2000',
            'rod_specification_id' => 'nullable|integer|exists:master_data_production_references,id',
        ])->validate();

        return DB::transaction(function () use ($valid, $id) {
            $item = $id ? Item::lockForUpdate()->findOrFail($id) : new Item;
            if (array_key_exists('rod_specification_id', $valid) && $valid['rod_specification_id']) {
                $rod = ProductionReference::where('section', 'rod-specifications')->find($valid['rod_specification_id']);
                if (!$rod || !$rod->diameter_mm || !$rod->weight_ton_per_meter
                    || (!$rod->is_active && (int) $item->rod_specification_id !== (int) $rod->id)) {
                    $this->fail('rod_specification_id', 'Pilih spesifikasi Cu Rod aktif dengan diameter dan bobot yang lengkap.');
                }
            }
            if ($id && $item->uom_id !== (int) $valid['uom_id'] && (
                $item->boms()->exists() || BomLine::where('material_id', $id)->exists() ||
                $item->salesOrders()->exists() || $item->receipts()->exists() || $item->issueLines()->exists() || $item->reservations()->exists() ||
                PurchaseRequest::where('product_id', $id)->exists() ||
                SaleMaterialRequirement::where('material_id', $id)->exists())) {
                $this->fail('uom_id', 'UoM barang yang sudah dipakai pada BoM atau transaksi tidak dapat diubah. Buat barang baru untuk satuan berbeda.');
            }
            $category = Category::with('parent')->lockForUpdate()->findOrFail($valid['category_id']);
            $unit = Unit::lockForUpdate()->findOrFail($valid['uom_id']);
            if ($valid['is_active'] && (! $category->is_active || ($category->parent && ! $category->parent->is_active))) {
                $this->fail('category_id', 'Pilih kategori aktif untuk barang aktif.');
            }
            if ($valid['is_active'] && (! $unit->is_active || ($unit->related && ! $unit->related->is_active))) {
                $this->fail('uom_id', 'Pilih UoM aktif untuk barang aktif.');
            }
            $item->fill($valid)->save();

            return $item;
        });
    }

    private function normalize(array $data): array
    {
        $data['code'] = strtoupper(trim($data['code'] ?? ''));
        $data['name'] = preg_replace('/\s+/u', ' ', trim($data['name'] ?? ''));

        return $data;
    }

    private function commonRules(string $table, ?int $id, int $length): array
    {
        return [
            'code' => ['required', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', 'max:'.$length, Rule::unique($table, 'code')->ignore($id)],
            'name' => 'required|string|max:255', 'is_active' => 'required|boolean',
        ];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
