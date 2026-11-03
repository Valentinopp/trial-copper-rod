<?php

namespace App\Modules\Produksi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopperBreakEntry extends Model
{
    protected $table = 'copper_break_entries';

    protected $fillable = [
        'supervisor_user_id', 'event_at_break_master_id', 'event_after_break_master_id', 'category_master_id',
        'board_entry_id',
        'event_date',
        'event_time',
        'shift',
        'supervisor',
        'crystallizer',
        'lifetime_hours',
        'event_at_break',
        'event_after_break',
        'category',
        'weight_kg',
        'frequency',
        'remarks',
    ];

    protected $casts = [
        'lifetime_hours' => 'float',
        'weight_kg'      => 'float',
        'frequency'      => 'integer',
    ];

    public function boardEntry(): BelongsTo
    {
        return $this->belongsTo(BoardEntry::class, 'board_entry_id');
    }

    public function supervisorEmployee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'supervisor_user_id');
    }
}
