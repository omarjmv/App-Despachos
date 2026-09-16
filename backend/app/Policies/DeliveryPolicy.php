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
     */
    public function execute(User $user, RouteStop $stop): bool
    {
        if ($user->hasRole(Role::ADMINISTRADOR)) {
            return true;
        }

        return $user->hasRole(Role::MOTORISTA) && $stop->route->driver_id === $user->id;
    }

    /**
     * Consultar evidencia ya registrada: además del motorista dueño, un
     * supervisor puede necesitarlo para resolver una diferencia
     * (secciones 3 y 30 del brief) aunque no pueda registrar la entrega.
     */
    public function view(User $user, RouteStop $stop): bool
    {
        if ($user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR)) {
            return true;
        }

        return $user->hasRole(Role::MOTORISTA) && $stop->route->driver_id === $user->id;
    }
}
