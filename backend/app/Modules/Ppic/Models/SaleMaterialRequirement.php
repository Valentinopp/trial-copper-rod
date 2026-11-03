<?php

namespace App\Modules\Ppic\Models;

use App\Modules\MasterData\Models\Unit;
use Illuminate\Database\Eloquent\Model;

class SaleMaterialRequirement extends Model
{
    protected $table = 'ic_sale_material_requirements';

    protected $fillable = ['uom_id', 'sales_order_id', 'material_id', 'material_name', 'uom', 'quantity'];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'uom_id');
    }

    protected $casts = ['quantity' => 'decimal:6'];
}
