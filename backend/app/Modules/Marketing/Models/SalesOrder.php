<?php

namespace App\Modules\Marketing\Models;

use App\Modules\MasterData\Models\Bom;
use App\Modules\MasterData\Models\Customer;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Unit;
use App\Modules\Ppic\Models\ProductionHandoff;
use App\Modules\Ppic\Models\SaleMaterialRequirement;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $table = 'marketing_sales_orders';

    protected $fillable = ['submission_token', 'customer_id', 'product_id', 'uom_id', 'customer_snapshot',
        'product_snapshot', 'unit_snapshot', 'quantity', 'sold_on', 'ship_by', 'notes', 'bom_id', 'bom_snapshot'];

    protected $casts = ['customer_snapshot' => 'array', 'product_snapshot' => 'array', 'unit_snapshot' => 'array',
        'quantity' => 'decimal:6', 'sold_on' => 'date', 'ship_by' => 'date', 'bom_snapshot' => 'array'];

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'uom_id');
    }

    public function materialRequirements()
    {
        return $this->hasMany(SaleMaterialRequirement::class, 'sales_order_id');
    }

    public function productionHandoff()
    {
        return $this->hasOne(ProductionHandoff::class, 'sales_order_id');
    }

    public function getReferenceAttribute(): string
    {
        return 'SO-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
