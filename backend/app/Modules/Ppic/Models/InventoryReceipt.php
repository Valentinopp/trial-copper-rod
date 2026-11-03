<?php

namespace App\Modules\Ppic\Models;

use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Unit;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;

class InventoryReceipt extends Model
{
    protected $fillable = ['uom_id', 'purchase_order_id', 'product_id', 'receipt_token', 'quantity', 'received_on'];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'uom_id');
    }

    protected $casts = ['quantity' => 'decimal:6', 'received_on' => 'date'];

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }
}
