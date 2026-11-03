<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Profiles grant permissions for integrated modules; other modules retain their existing role rules.
class AccessProfile extends Model
{
    protected $fillable = ['job_title_id', 'division_id', 'is_active', 'permissions', 'configured_modules'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'permissions' => 'array', 'configured_modules' => 'array'];
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}