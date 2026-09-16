<?php

namespace App\Enums;

enum RouteStatus: string
{
    case PLANIFICADA = 'PLANIFICADA';
    case EN_CURSO = 'EN_CURSO';
    case FINALIZADA = 'FINALIZADA';

    public function allowedNextStates(): array
    {
        return match ($this) {
            self::PLANIFICADA => [self::EN_CURSO],
            self::EN_CURSO => [self::FINALIZADA],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNextStates(), true);
    }
}
