<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API pura sin vistas: toda petición /api/* debe tratarse como JSON,
 * sin depender de que el cliente envíe el header Accept correcto
 * (evita que Laravel intente redirigir a una ruta "login" inexistente).
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
