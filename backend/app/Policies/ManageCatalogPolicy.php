<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Política compartida por catálogos administrables (clientes, productos,
 * vehículos, usuarios): lectura amplia, escritura solo ADMINISTRADOR.
 */
class ManageCatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(Role::ADMINISTRADOR);
    }
}
