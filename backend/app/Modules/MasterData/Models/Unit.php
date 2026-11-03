<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = 'master_data_units';

    protected $fillable = ['code', 'name', 'related_uom_id', 'factor', 'is_active'];

    protected $casts = ['factor' => 'decimal:9', 'is_active' => 'boolean'];

    public function related()
    {
        return $this->belongsTo(self::class, 'related_uom_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'uom_id');
    }
}
