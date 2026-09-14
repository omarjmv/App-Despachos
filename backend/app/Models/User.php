<?php

namespace App\Models;

use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToCompany, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'company_id', 'role_id', 'name', 'email', 'phone',
        'password', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string ...$names): bool
    {
        return in_array($this->role?->name, $names, true);
    }

    public function hasPermission(string $slug): bool
    {
        return (bool) $this->role?->hasPermission($slug);
    }

    public function createdOrders()
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function assignedOrders()
    {
        return $this->hasMany(Order::class, 'assigned_to');
    }
}
