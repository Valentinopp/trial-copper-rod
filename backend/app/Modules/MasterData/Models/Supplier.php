<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'master_data_suppliers';

    protected $fillable = ['code', 'name', 'contact_name', 'phone', 'email', 'address', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items()
    {
        return $this->belongsToMany(Item::class, 'master_data_supplier_items', 'supplier_id', 'item_id')->withTimestamps();
    }
}
