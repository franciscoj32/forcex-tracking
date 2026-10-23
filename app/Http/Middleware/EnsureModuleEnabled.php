<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas de un modulo opcional: si esta apagado en Configuracion, no existen (404).
 * Uso: ->middleware('module:productividad')
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(AppSetting::moduleEnabled($module), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
