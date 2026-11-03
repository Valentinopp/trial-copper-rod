<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'master_data_categories';

    protected $fillable = ['code', 'name', 'parent_id', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
