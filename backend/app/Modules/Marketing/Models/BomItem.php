<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Ppic\Models\InventoryProduct;
use Illuminate\Database\Eloquent\Model;

class BomItem extends Model
{
    protected $table = 'marketing_bom_items';
    protected $fillable = ['material_id', 'quantity'];
    protected $casts = ['quantity' => 'decimal:3'];

    public function material()
    {
        return $this->belongsTo(InventoryProduct::class, 'material_id');
    }
}
