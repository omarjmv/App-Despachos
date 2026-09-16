<?php

namespace App\Policies;

use App\Models\RouteModel;
use App\Models\Role;
use App\Models\User;

class RoutePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RouteModel $route): bool
    {
        if ($user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR)) {
            return true;
        }

        // El motorista solo ve su propia ruta (Documento 3 §5: "el motorista
        // debe ver únicamente las entregas que le corresponden").
        return $user->hasRole(Role::MOTORISTA) && $route->driver_id === $user->id;
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR);
    }
}
