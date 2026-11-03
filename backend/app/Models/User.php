<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['job_title_id', 'division_id', 'username', 'employee_number', 'name', 'job_title', 'department', 'role', 'email', 'password', 'is_active'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'last_login_at' => 'datetime', 'is_active' => 'boolean', 'role' => UserRole::class, 'password' => 'hashed'];
    }

    protected function username(): Attribute
    {
        return Attribute::make(set: fn ($value) => mb_strtolower(trim($value)));
    }

    public function homeRoute(): string
    {
        if ($this->role === UserRole::Marketing && ! \Illuminate\Support\Facades\Gate::forUser($this)->allows('marketing.sales.view')) {
            return \Illuminate\Support\Facades\Gate::forUser($this)->allows('access-ic') ? 'ppic.inventory-control' : 'access-unavailable';
        }
        if ($this->role === UserRole::Purchasing && ! \Illuminate\Support\Facades\Gate::forUser($this)->allows('access-purchasing')) {
            foreach (['access-marketing' => 'marketing.sales', 'access-ic' => 'ppic.inventory-control'] as $permission => $route) {
                if (\Illuminate\Support\Facades\Gate::forUser($this)->allows($permission)) return $route;
            }
            return 'access-unavailable';
        }
        return $this->role->homeRoute();
    }

    public function getInitialsAttribute(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }
}
