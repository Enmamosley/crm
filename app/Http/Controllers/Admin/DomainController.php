<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Setting;
use App\Services\CosmotownService;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index()
    {
        $service     = new CosmotownService();
        $configured  = $service->isConfigured();
        $environment = Setting::get('cosmotown_base_url', 'https://sandbox.cosmotown.com');
        $isSandbox   = str_contains($environment, 'sandbox');

        // Mapa dominio → cliente para mostrar en la tabla
        $clientsByDomain = Client::whereNotNull('domain')
            ->get(['id', 'name', 'domain'])
            ->keyBy('domain')
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name]);

        return view('admin.domains.index', compact('configured', 'environment', 'isSandbox', 'clientsByDomain'));
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

    /**
     * AJAX: list all domains in reseller account.
     */
    public function list(Request $request)
    {
        $service = new CosmotownService();

        if (!$service->isConfigured()) {
            return response()->json(['error' => 'API key de Cosmotown no configurada.'], 422);
        }

        try {
            $result = $service->listDomains(
                (int) $request->query('limit', 100),
                (int) $request->query('offset', 0)
            );
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * Domain detail page with info, DNS, and nameservers.
     */
    public function info(string $domain)
    {
        $service = new CosmotownService();

        if (!$service->isConfigured()) {
            return redirect()->route('admin.domains.index')->with('error', 'API key de Cosmotown no configurada.');
        }

        try {
            $raw = $service->domainInfo($domain);
        } catch (\Throwable $e) {
            return redirect()->route('admin.domains.index')->with('error', 'Error al obtener info: ' . $e->getMessage());
        }

        // Cosmotown puede devolver la info en el nivel raíz o anidada bajo 'domain'
        // Si 'domain' es un string (el nombre), los datos están en el nivel raíz
        $d = is_array($raw['domain'] ?? null) ? $raw['domain'] : $raw;

        // Normalizar campos a nombres conocidos
        $domainInfo = [
            'domain'      => $d,
            'nameservers' => $raw['nameservers']
                ?? $d['nameservers']
                ?? array_values(array_filter([
                    $d['ns1'] ?? null, $d['ns2'] ?? null,
                    $d['ns3'] ?? null, $d['ns4'] ?? null,
                ])),
            'contact' => $raw['contact'] ?? $d['contact'] ?? [],
            '_raw'    => $raw,
        ];

        // Normalizar campos booleanos con múltiples variantes de nombre
        $domainInfo['domain']['auto_billing']  = (bool) ($d['auto_billing']  ?? $d['autoRenew']    ?? $d['auto_renew']    ?? false);
        $domainInfo['domain']['whois_privacy']  = (bool) ($d['whois_privacy'] ?? $d['whoisPrivacy']  ?? $d['privacy']       ?? false);
        $domainInfo['domain']['locked']         = (bool) ($d['locked']        ?? $d['registrarLock'] ?? $d['domain_lock']   ?? false);
        $domainInfo['domain']['created']        = $d['created']         ?? $d['createdDate']    ?? $d['created_at']    ?? null;
        $domainInfo['domain']['expiration_date']= $d['expiration_date'] ?? $d['expirationDate'] ?? $d['expires']       ?? $d['expire_date'] ?? null;

        $environment = Setting::get('cosmotown_base_url', 'https://sandbox.cosmotown.com');
        $isSandbox   = str_contains($environment, 'sandbox');

        $clients        = Client::orderBy('name')->get(['id', 'name', 'email', 'domain']);
        $assignedClient = Client::where('domain', $domain)->first();

        return view('admin.domains.show', compact('domain', 'domainInfo', 'isSandbox', 'clients', 'assignedClient'));
    }

    /**
     * Asignar un dominio de Cosmotown a un cliente.
     */
    public function assignClient(Request $request, string $domain)
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
        ]);

        // Quitar el dominio de cualquier cliente que lo tuviera antes
        Client::where('domain', $domain)->update(['domain' => null, 'domain_type' => null]);

        $client              = Client::findOrFail($validated['client_id']);
        $client->domain      = $domain;
        $client->domain_type = 'cosmotown';
        $client->save();

        return response()->json([
            'success' => true,
            'message' => "Dominio {$domain} asignado a {$client->name}.",
            'client'  => ['id' => $client->id, 'name' => $client->name],
        ]);
    }

    /**
     * Desasignar el dominio del cliente actual.
     */
    public function unassignClient(string $domain)
    {
        Client::where('domain', $domain)->update(['domain' => null, 'domain_type' => null]);

        return response()->json(['success' => true, 'message' => 'Dominio desasignado.']);
    }

    /**
     * AJAX: get DNS settings for a domain.
     */
    public function dns(string $domain)
    {
        $service = new CosmotownService();

        if (!$service->isConfigured()) {
            return response()->json(['error' => 'API key de Cosmotown no configurada.'], 422);
        }

        try {
            $result = $service->getDnsSettings($domain);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * AJAX: save DNS settings for a domain.
     */
    public function saveDns(Request $request, string $domain)
    {
        $validated = $request->validate([
            'records' => ['required', 'array'],
        ]);

        $service = new CosmotownService();

        if (!$service->isConfigured()) {
            return response()->json(['error' => 'API key de Cosmotown no configurada.'], 422);
        }

        // Denormalizar: convertir nombres de campo del frontend → Cosmotown
        $records = [];
        foreach ($validated['records'] as $type => $typeRecords) {
            foreach ((array) $typeRecords as $rec) {
                $record = [
                    'type'   => strtoupper($type),
                    'host'   => $rec['host'] ?? '@',
                    'ttl'    => (int) ($rec['ttl'] ?? 300),
                ];
                if (strtoupper($type) === 'TXT') {
                    $record['data'] = $rec['content'] ?? $rec['pointto'] ?? '';
                } else {
                    $record['pointsTo'] = $rec['pointto'] ?? $rec['content'] ?? '';
                }
                if (strtoupper($type) === 'MX') {
                    $record['priority'] = (int) ($rec['priority'] ?? 10);
                }
                $records[] = $record;
            }
        }

        try {
            $service->saveDnsSettings($domain, $records);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * AJAX: save nameservers for a domain.
     */
    public function saveNameservers(Request $request, string $domain)
    {
        $validated = $request->validate([
            'nameservers' => ['required', 'array', 'min:1', 'max:4'],
            'nameservers.*' => ['required', 'string', 'max:253'],
        ]);

        $service = new CosmotownService();

        if (!$service->isConfigured()) {
            return response()->json(['error' => 'API key de Cosmotown no configurada.'], 422);
        }

        try {
            $service->saveNameservers($domain, $validated['nameservers']);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * AJAX: renew a domain.
     */
    public function renew(Request $request, string $domain)
    {
        $validated = $request->validate([
            'years' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $service = new CosmotownService();

        if (!$service->isConfigured()) {
            return response()->json(['error' => 'API key de Cosmotown no configurada.'], 422);
        }

        try {
            $result = $service->renew($domain, $validated['years']);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * AJAX: check registration status of domains.
     */
    public function status(Request $request)
    {
        $validated = $request->validate([
            'domains' => ['required', 'array', 'min:1'],
            'domains.*' => ['required', 'string', 'max:253'],
        ]);

        $service = new CosmotownService();

        if (!$service->isConfigured()) {
            return response()->json(['error' => 'API key de Cosmotown no configurada.'], 422);
        }

        try {
            $result = $service->domainStatus($validated['domains']);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /**
     * AJAX: ping Cosmotown API.
     */
    public function ping()
    {
        $service = new CosmotownService();

        if (!$service->isConfigured()) {
            return response()->json(['error' => 'API key de Cosmotown no configurada.'], 422);
        }

        try {
            $result = $service->ping();
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
