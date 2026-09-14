<?php

namespace App\Enums;

enum DifferenceReason: string
{
    case FALTANTE_INVENTARIO = 'FALTANTE_INVENTARIO';
    case PRODUCTO_DANADO = 'PRODUCTO_DANADO';
    case ERROR_PEDIDO = 'ERROR_PEDIDO';
    case SUSTITUCION = 'SUSTITUCION';
    case OTRO = 'OTRO';
}
