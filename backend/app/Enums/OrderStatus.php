<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case PREPARANDO = 'PREPARANDO';
    case PREPARADO = 'PREPARADO';
    case EN_REVISION = 'EN_REVISION';
    case APROBADO = 'APROBADO';
    case DESPACHADO = 'DESPACHADO';
    case EN_RUTA = 'EN_RUTA';
    case ENTREGADO = 'ENTREGADO';
    case PARCIAL = 'PARCIAL';
    case CANCELADO = 'CANCELADO';

    /**
     * Transiciones válidas desde cada estado (máquina de estados, regla 21:
     * nunca permitir saltos inválidos que rompan la trazabilidad).
     */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::PENDIENTE => [self::PREPARANDO, self::CANCELADO],
            self::PREPARANDO => [self::PREPARADO, self::CANCELADO],
            self::PREPARADO => [self::EN_REVISION, self::CANCELADO],
            self::EN_REVISION => [self::APROBADO, self::PREPARANDO],
            self::APROBADO => [self::DESPACHADO, self::CANCELADO],
            self::DESPACHADO => [self::EN_RUTA],
            self::EN_RUTA => [self::ENTREGADO, self::PARCIAL],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNextStates(), true);
    }
}
