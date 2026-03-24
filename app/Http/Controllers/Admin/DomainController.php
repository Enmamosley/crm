<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\CosmotownService;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index()
    {
        $service     = new CosmotownService();
        $configured  = $service->isConfigured();
        $environment = Setting::get('cosmotown_base_url', 'https://irest-ote.cosmotown.com');
        $isOte       = str_contains($environment, 'ote');

        return view('admin.domains.index', compact('configured', 'environment', 'isOte'));
    }

    /**
     * AJAX: check domain availability.
     */
    public function check(Request $request)
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}$/'],
        ]);

        $service = new CosmotownService();

        if (!$service->isConfigured()) {
            return response()->json(['error' => 'API key de Cosmotown no configurada.'], 422);
        }

        try {
            $result = $service->checkAvailability($validated['domain']);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * AJAX: register a domain.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}$/'],
        ]);

        $service = new CosmotownService();

        if (!$service->isConfigured()) {
            return response()->json(['error' => 'API key de Cosmotown no configurada.'], 422);
        }

        try {
            $result = $service->register($validated['domain']);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
