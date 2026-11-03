<?php

namespace App\Modules\Ppic\Models;

use App\Modules\MasterData\Models\Item;
use Illuminate\Database\Eloquent\Model;

class InventoryIssueLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['quantity' => 'decimal:6'];

    public function issue()
    {
        return $this->belongsTo(InventoryIssue::class, 'inventory_issue_id');
    }

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }
}
