<?php

namespace App\Support;

/**
 * Contenedor del tenant (empresa) activo durante el request.
 *
 * Se establece en el middleware IdentifyTenant a partir del usuario
 * autenticado, y lo consultan el trait BelongsToEmpresa y las consultas
 * en crudo (DB::table) que no pasan por los scopes de Eloquent.
 *
 * Es estático a propósito: así funciona igual en peticiones web, comandos
 * de consola, colas y seeders sin depender del contenedor.
 */
class Tenant
{
    protected static ?int $empresaId = null;

    /** Fija la empresa activa. */
    public static function set(?int $empresaId): void
    {
        static::$empresaId = $empresaId;
    }

    /** ID de la empresa activa (o null si no hay tenant en contexto). */
    public static function id(): ?int
    {
        return static::$empresaId;
    }

    /** ¿Hay un tenant activo? */
    public static function check(): bool
    {
        return static::$empresaId !== null;
    }

    /** Limpia el tenant activo (útil en tests y entre trabajos de cola). */
    public static function clear(): void
    {
        static::$empresaId = null;
    }

    /**
     * Ejecuta un callback con un tenant fijado temporalmente y restaura
     * el anterior al terminar. Útil en seeders y jobs.
     */
    public static function withTenant(?int $empresaId, callable $callback): mixed
    {
        $previo = static::$empresaId;
        static::$empresaId = $empresaId;

        try {
            return $callback();
        } finally {
            static::$empresaId = $previo;
        }
    }
}
