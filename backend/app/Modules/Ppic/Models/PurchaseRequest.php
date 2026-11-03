<?php

namespace App\Modules\Ppic\Models;

use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Unit;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    protected $fillable = ['uom_id', 'product_id', 'quantity', 'required_on', 'sales_order_id'];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'uom_id');
    }

    protected $casts = ['quantity' => 'decimal:6', 'required_on' => 'date'];

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    public function order()
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function getReferenceAttribute(): string
    {
        return 'PR-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
