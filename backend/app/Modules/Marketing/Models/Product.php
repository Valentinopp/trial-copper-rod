<?php

namespace App\Modules\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'marketing_products';
    protected $fillable = ['name', 'diameter_mm', 'purity_percent', 'bom_output_kg'];
    protected $casts = ['diameter_mm' => 'decimal:3', 'purity_percent' => 'decimal:4', 'bom_output_kg' => 'decimal:3'];

    public function bomItems()
    {
        return $this->hasMany(BomItem::class, 'product_id');
    }

    public function getReferenceAttribute(): string
    {
        return 'CR-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
