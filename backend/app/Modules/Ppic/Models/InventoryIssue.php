<?php

namespace App\Modules\Ppic\Models;

use App\Models\User;
use App\Modules\MasterData\Models\Division;
use Illuminate\Database\Eloquent\Model;

class InventoryIssue extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['issued_on' => 'date', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime', 'reversed_at' => 'datetime'];

    public function lines()
    {
        return $this->hasMany(InventoryIssueLine::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function reverser()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function getReferenceAttribute(): string
    {
        return 'BK-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return ['draft' => 'Draft', 'confirmed' => 'Dikonfirmasi', 'cancelled' => 'Dibatalkan', 'reversed' => 'Dibalikkan'][$this->status] ?? $this->status;
    }
}
