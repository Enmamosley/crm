<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DmChampTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('DmChamp:middleware', [
            'path'   => $request->path(),
            'method' => $request->method(),
        ]);

        $expected = config('services.dmchamp.function_token');

        // Si no hay token configurado, bloquear por seguridad
        if (empty($expected)) {
            Log::warning('DmChamp:middleware — token NOT configured in .env');
            return response()->json(['error' => 'Not configured.'], 403);
        }

        // Solo por header: en query string terminaría en logs, proxies e historial.
        $provided = $request->header('X-DmChamp-Token');

        if (! $provided || ! hash_equals($expected, (string) $provided)) {
            Log::warning('DmChamp:middleware — unauthorized', ['provided' => $provided ? 'yes' : 'no']);
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
