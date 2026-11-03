<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionReference extends Model
{
    public const SECTIONS = [
        'take-ups' => 'Take Up',
        'coilings' => 'Coiling',
        'crystallizers' => 'Crystallizer',
        'rod-specifications' => 'Spesifikasi Cu Rod',
        'break-events' => 'Kejadian Saat Putus',
        'break-conditions' => 'Kondisi Setelah Putus',
        'break-categories' => 'Kategori Putus',
    ];

    protected $table = 'master_data_production_references';

    protected $fillable = ['section', 'code', 'name', 'servo', 'diameter_mm', 'weight_ton_per_meter', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'diameter_mm' => 'float', 'weight_ton_per_meter' => 'float'];

    public static function valueColumn(string $section): string
    {
        return match ($section) {
            'take-ups', 'coilings', 'crystallizers' => 'code',
            'rod-specifications' => 'diameter_mm',
            default => 'name',
        };
    }

    public function selectionValue(): string
    {
        return (string) $this->{self::valueColumn($this->section)};
    }
}
