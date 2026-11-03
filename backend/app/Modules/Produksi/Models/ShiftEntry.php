<?php

namespace App\Modules\Produksi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftEntry extends Model
{
    protected $table = 'shift_entries';

    protected $fillable = [
        'work_date',
        'shift',
        'board_entry_id',
        'lot_note',
        'take_up',
        'cooling',
        'servo',
        'hours',
        'crystallizer',
        'remarks',
    ];

    protected $casts = [
        'hours' => 'float',
    ];

    public function boardEntry(): BelongsTo
    {
        return $this->belongsTo(BoardEntry::class, 'board_entry_id');
    }
}
