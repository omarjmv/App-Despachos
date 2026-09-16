<?php

namespace App\Enums;

enum RouteStopStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case EN_CURSO = 'EN_CURSO';
    case COMPLETADA = 'COMPLETADA';
}
