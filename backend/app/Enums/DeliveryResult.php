<?php

namespace App\Enums;

enum DeliveryResult: string
{
    case COMPLETA = 'COMPLETA';
    case PARCIAL = 'PARCIAL';
    case RECHAZADA = 'RECHAZADA';
}
