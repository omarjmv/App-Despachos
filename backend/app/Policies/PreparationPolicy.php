<?php

namespace App\Policies;

use App\Models\Preparation;
use App\Models\Role;
use App\Models\User;

class PreparationPolicy
{
    public function view(User $user, Preparation $preparation): bool
    {
        if ($user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR, Role::REVISOR)) {
            return true;
        }

        return $user->hasRole(Role::PREPARADOR) && $preparation->prepared_by === $user->id;
    }

    public function execute(User $user, Preparation $preparation): bool
    {
        if ($user->hasRole(Role::ADMINISTRADOR)) {
            return true;
        }

        return $user->hasRole(Role::PREPARADOR) && $preparation->order->assigned_to === $user->id;
    }
}
