<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class ReviewPolicy
{
    public function execute(User $user): bool
    {
        return $user->hasRole(Role::ADMINISTRADOR, Role::REVISOR);
    }
}
