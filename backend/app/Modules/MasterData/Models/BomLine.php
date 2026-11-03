<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class BomLine extends Model
{
    protected $table = 'master_data_bom_lines';

    protected $fillable = ['material_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:6'];

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    public function material()
    {
        return $this->belongsTo(Item::class, 'material_id');
    }
}
