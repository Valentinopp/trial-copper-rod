<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\Ppic\Models\InventoryReceipt;
use App\Modules\Ppic\Models\MaterialReservation;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'master_data_items';

    protected $fillable = ['code', 'name', 'category_id', 'uom_id', 'description', 'is_active', 'rod_specification_id'];

    protected $casts = ['is_active' => 'boolean'];

    public function rodSpecification()
    {
        return $this->belongsTo(ProductionReference::class, 'rod_specification_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'uom_id');
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'master_data_supplier_items', 'item_id', 'supplier_id')->withTimestamps();
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'product_id');
    }

    public function boms()
    {
        return $this->hasMany(Bom::class, 'product_id');
    }

    public function receipts()
    {
        return $this->hasMany(InventoryReceipt::class, 'product_id');
    }

    public function issueLines()
    {
        return $this->hasMany(\App\Modules\Ppic\Models\InventoryIssueLine::class, 'product_id');
    }

    public function reservations()
    {
        return $this->hasMany(MaterialReservation::class, 'material_id');
    }
}
