<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la empresa (tenant) activa a partir del usuario autenticado.
 * A partir de aquí, todas las consultas de modelos con BelongsToEmpresa
 * quedan aisladas a la empresa del usuario.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->empresa_id) {
            Tenant::set($user->empresa_id);
        } else {
            // Sin usuario (invitado) no hay tenant. Limpiar evita fugas de
            // datos entre peticiones en procesos persistentes (p. ej. Octane).
            Tenant::clear();
        }

        return $next($request);
    }
}
