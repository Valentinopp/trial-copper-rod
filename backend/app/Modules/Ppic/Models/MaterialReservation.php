<?php

namespace App\Modules\Ppic\Models;

use App\Modules\MasterData\Models\Unit;
use Illuminate\Database\Eloquent\Model;

class MaterialReservation extends Model
{
    protected $table = 'production_material_reservations';

    protected $fillable = ['uom_id', 'material_id', 'quantity'];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'uom_id');
    }

    protected $casts = ['quantity' => 'decimal:6'];
}
