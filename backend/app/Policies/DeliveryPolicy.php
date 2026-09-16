<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\RouteStop;
use App\Models\User;

class DeliveryPolicy
{
    /**
     * Solo el motorista dueño de la ruta (o un administrador de soporte)
     * puede operar sobre una parada de entrega: iniciarla, registrar
     * cantidades o subir evidencia.
     *
     * RouteStop no tiene company_id propio (solo lo tiene su ruta), y no
     * pasa por el Global Scope de empresa al resolverse por route-model-
     * binding. Sin el chequeo explícito de company_id aquí, un
     * ADMINISTRADOR de la empresa A podría operar sobre una parada de la
     * empresa B con solo adivinar su id (justo la fuga que prohíbe la
     * regla 21 del brief).
     */
    public function execute(User $user, RouteStop $stop): bool
    {
        $route = $stop->route;

        if (! $route || $route->company_id !== $user->company_id) {
            return false;
        }

        if ($user->hasRole(Role::ADMINISTRADOR)) {
            return true;
        }

        return $user->hasRole(Role::MOTORISTA) && $route->driver_id === $user->id;
    }

    /**
     * Consultar evidencia ya registrada: además del motorista dueño, un
     * supervisor puede necesitarlo para resolver una diferencia
     * (secciones 3 y 30 del brief) aunque no pueda registrar la entrega.
     */
    public function view(User $user, RouteStop $stop): bool
    {
        $route = $stop->route;

        if (! $route || $route->company_id !== $user->company_id) {
            return false;
        }

        if ($user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR)) {
            return true;
        }

        return $user->hasRole(Role::MOTORISTA) && $route->driver_id === $user->id;
    }
}
