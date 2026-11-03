<?php

namespace App\Modules\Ppic\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryProduct extends Model
{
    protected $table = 'inventory_products';
    protected $fillable = ['identity_key', 'name', 'uom'];

    public function receipts()
    {
        return $this->hasMany(InventoryReceipt::class, 'product_id');
    }

    public function reservations()
    {
        return $this->hasMany(MaterialReservation::class, 'material_id');
    }
}
