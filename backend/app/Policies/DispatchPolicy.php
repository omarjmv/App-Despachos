<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class DispatchPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR);
    }
}
