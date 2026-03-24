<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePortalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');

        if (!$token) {
            abort(404);
        }

        $client = Client::where('portal_token', $token)
            ->where('portal_active', true)
            ->first();

        if (!$client) {
            abort(404, 'Portal no disponible.');
        }

        // Share client with all views for convenience
        $request->merge(['portal_client' => $client]);

        return $next($request);
    }
}
