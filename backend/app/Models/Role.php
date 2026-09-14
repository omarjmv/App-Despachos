<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const ADMINISTRADOR = 'ADMINISTRADOR';
    public const SUPERVISOR = 'SUPERVISOR';
    public const PREPARADOR = 'PREPARADOR';
    public const REVISOR = 'REVISOR';
    public const MOTORISTA = 'MOTORISTA';

    protected $fillable = ['name', 'description'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }
}
