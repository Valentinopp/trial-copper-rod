<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Ppic\Models\InventoryReceipt;
use App\Modules\Ppic\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = ['purchase_request_id', 'ordered_on', 'expected_on'];

    protected $casts = ['ordered_on' => 'date', 'expected_on' => 'date'];

    public function request()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function receipts()
    {
        return $this->hasMany(InventoryReceipt::class);
    }

    public function getReferenceAttribute(): string
    {
        return 'PO-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getRemainingAttribute(): float
    {
        return round((float) $this->request->quantity - (float) ($this->receipts_sum_quantity ?? $this->receipts()->sum('quantity')), 6);
    }

    public function getStatusAttribute(): string
    {
        if ($this->remaining <= 0) {
            return 'Diterima lengkap';
        }
        if ($this->remaining < (float) $this->request->quantity) {
            return 'Diterima sebagian';
        }

        return 'Menunggu barang';
    }
}
