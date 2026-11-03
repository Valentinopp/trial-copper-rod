<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'master_data_customers';

    protected $fillable = ['code', 'name', 'contact_name', 'phone', 'email', 'address', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function salesOrders()
    {
        return $this->hasMany(\App\Modules\Marketing\Models\SalesOrder::class);
    }
}
