<?php

namespace App\Support;

/**
 * Contexto de empresa actual para la petición en curso.
 *
 * Se resuelve una única vez por request (middleware EnsureCompanyContext)
 * a partir del usuario autenticado. Nunca se llena desde input del cliente.
 */
class CompanyContext
{
    private static ?int $companyId = null;

    public static function set(?int $companyId): void
    {
        static::$companyId = $companyId;
    }

    public static function id(): ?int
    {
        return static::$companyId;
    }

    public static function clear(): void
    {
        static::$companyId = null;
    }
}
