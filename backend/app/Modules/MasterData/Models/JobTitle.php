<?php
namespace App\Modules\MasterData\Models;
use Illuminate\Database\Eloquent\Model;
class JobTitle extends Model {
 protected $table = 'master_data_job_titles';
 protected $fillable = ['code','name','description','is_active'];
 protected function casts(): array { return ['is_active'=>'boolean']; }
}
