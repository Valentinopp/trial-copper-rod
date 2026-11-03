<?php

namespace App\Modules\Produksi\Services;

use App\Modules\MasterData\Models\ProductionReference;
use App\Modules\Produksi\Models\BoardEntry;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductionMasterDataService
{
    public function options(string $section, ?string $original = null): Collection
    {
        $options = ProductionReference::where('section', $section)->where('is_active', true)
            ->orderBy($section === 'rod-specifications' ? 'diameter_mm' : 'name')->get()
            ->map(fn ($row) => [
                'value' => $row->selectionValue(),
                'label' => $section === 'rod-specifications' ? $row->diameter_mm.' mm — '.$row->name
                    : ($row->selectionValue() === $row->name ? $row->name : $row->code.' — '.$row->name),
            ]);
        if ($original !== null && $original !== '' && ! $options->contains(fn ($option) => $option['value'] === $original)) {
            $options->push(['value' => $original, 'label' => $original.' (riwayat)']);
        }

        return $options->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function resolve(string $section, string $value, string $field, ?string $original = null, ?int $originalId = null, bool $required = true): ?ProductionReference
    {
        if ($value === '' && ! $required) {
            return null;
        }
        // Retaining a historical selection is allowed, even if its master was renamed or deactivated.
        if ($value !== '' && $original !== null && $value === $original) {
            if ($originalId) {
                return ProductionReference::where('section', $section)->findOrFail($originalId);
            }

            return $this->find($section, $value, false);
        }
        $row = $value === '' ? null : $this->find($section, $value);
        if (! $row) {
            throw ValidationException::withMessages([$field => 'Pilih '.ProductionReference::SECTIONS[$section].' yang aktif dari master data.']);
        }

        return $row;
    }

    private function find(string $section, string $value, bool $activeOnly = true): ?ProductionReference
    {
        if ($section === 'rod-specifications' && (! is_numeric($value) || ! is_finite((float) $value))) {
            return null;
        }
        $query = ProductionReference::where('section', $section);
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->where(ProductionReference::valueColumn($section), $value)->first();
    }

    public function classify(string $takeUp, string $coiling, ?BoardEntry $original = null): array
    {
        $servos = [];
        foreach (['take_up' => ['take-ups', $takeUp], 'cooling' => ['coilings', $coiling]] as $field => [$section, $value]) {
            $unchanged = $original && $value === $original->$field;
            if ($unchanged) {
                $classification = $original->servo_classification;
                $servos[] = $classification[$field === 'take_up' ? 'take_up_servo' : 'cooling_servo']
                    ?? (! $classification['servo_conflict'] ? $classification['servo'] : null);
            } else {
                $servos[] = $this->find($section, $value)?->servo;
            }
        }
        $conflict = $servos[0] && $servos[1] && $servos[0] !== $servos[1];

        return ['servo' => $conflict ? null : ($servos[0] ?: $servos[1]), 'servo_conflict' => (bool) $conflict,
            'take_up_servo' => $servos[0], 'cooling_servo' => $servos[1]];
    }

    public function rodWeight(string $diameter, ?BoardEntry $original = null): ?float
    {
        if ($original && $diameter === (string) $original->rod_diameter_mm) {
            return $original->rod_weight_ton_per_meter ?? OutputFormulaService::rodWeight($diameter);
        }

        return $diameter === '' ? null : $this->find('rod-specifications', $diameter)?->weight_ton_per_meter;
    }
}
