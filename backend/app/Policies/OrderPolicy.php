<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR, Role::REVISOR)) {
            return true;
        }

        // El preparador solo ve los pedidos que le fueron asignados.
        if ($user->hasRole(Role::PREPARADOR)) {
            return $order->assigned_to === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR)
            && $order->status === \App\Enums\OrderStatus::PENDIENTE;
    }

    public function assign(User $user): bool
    {
        return $user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR);
    }

    public function cancel(User $user): bool
    {
        return $user->hasRole(Role::ADMINISTRADOR, Role::SUPERVISOR);
    }
}
