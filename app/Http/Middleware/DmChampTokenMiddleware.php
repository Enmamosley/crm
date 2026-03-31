<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DmChampTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.dmchamp.function_token');

        // Si no hay token configurado, bloquear por seguridad
        if (empty($expected)) {
            return response()->json(['error' => 'Not configured.'], 403);
        }

        $provided = $request->header('X-DmChamp-Token')
            ?? $request->query('token');

        if (! $provided || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
