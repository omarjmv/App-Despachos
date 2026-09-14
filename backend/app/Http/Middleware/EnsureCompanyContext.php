<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el company_id del usuario autenticado una vez por request.
 * Es la única fuente del tenant activo: el cliente nunca lo especifica.
 *
 * IMPORTANTE: se registra como middleware GLOBAL de la API (prepend, antes
 * de SubstituteBindings) y no como middleware de ruta. Si corriera después
 * de SubstituteBindings, el route-model-binding de parámetros como
 * {order} resolvería el modelo ANTES de que el global scope de empresa
 * supiera qué empresa filtrar, permitiendo leer registros de otro tenant
 * por id (justo lo que la regla 21 del brief prohíbe). Por eso resuelve
 * el usuario aquí mismo contra el guard de Sanctum, sin depender de que
 * el middleware de ruta auth:sanctum ya se haya ejecutado.
 */
class EnsureCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        CompanyContext::set(Auth::guard('sanctum')->user()?->company_id);

        try {
            return $next($request);
        } finally {
            CompanyContext::clear();
        }
    }
}
