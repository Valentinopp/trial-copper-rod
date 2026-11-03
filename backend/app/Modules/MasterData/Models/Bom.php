<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class Bom extends Model
{
    protected $table = 'master_data_boms';

    protected $fillable = ['code', 'name', 'product_id', 'output_quantity', 'notes', 'is_active'];

    protected $casts = ['output_quantity' => 'decimal:6', 'is_active' => 'boolean'];

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    public function lines()
    {
        return $this->hasMany(BomLine::class);
    }
}
