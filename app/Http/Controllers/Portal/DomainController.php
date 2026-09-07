<?php

namespace App\Http\Controllers\Portal;

use App\Models\ActivityLog;
use App\Services\CosmotownService;
use Illuminate\Http\Request;

/**
 * Dominio del cliente en Cosmotown: elección diferida, DNS y nameservers.
 */

class DomainController extends PortalController
{
    public function __construct(
        private CosmotownService $cosmotown,
    ) {}

    /**
     * El cliente eligió su dominio después de pagar ("decidir después").
     * Se guarda en el perfil y se notifica al equipo para activar el paquete —
     * el registro/provisión NO es automático: lo confirma un admin.
     */
    public function chooseDomain(Request $request, string $token)
    {
        $client = $this->client()->load(['clientServices.service', 'invoices']);

        // Sólo aplica a clientes con un paquete pagado esperando dominio.
        if (!$client->awaitsDomain()) {
            return back()->with('error', 'No tienes un paquete pendiente de dominio. Si necesitas cambiar tu dominio actual, contáctanos.');
        }

        $validated = $request->validate([
            'domain'      => ['required', 'string', 'max:253', 'regex:/^(?!.*\.\.)[a-zA-Z0-9][a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}$/'],
            'domain_type' => 'required|in:cosmotown,own',
        ], [
            'domain.regex' => 'Escribe un dominio válido, por ejemplo: miempresa.com',
        ]);

        $client->update([
            'domain'      => strtolower(trim($validated['domain'])),
            'domain_type' => $validated['domain_type'],
        ]);

        ActivityLog::log('domain_chosen', $client,
            "El cliente eligió su dominio desde el portal: {$client->domain} (" . ($validated['domain_type'] === 'cosmotown' ? 'registrar nuevo' : 'dominio propio') . ")");

        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::notify($admin->id, 'domain_chosen',
                "Dominio elegido: {$client->domain}",
                "{$client->legal_name} eligió su dominio desde el portal. Revisa y activa su paquete.",
                route('admin.clients.show', $client));
        }

        return redirect()->route('portal.dashboard', $token)
            ->with('success', '¡Listo! Recibimos tu dominio. Nuestro equipo activará tu paquete en breve y te avisaremos por correo.');
    }

    public function domain(string $token)
    {
        $client = $this->client();

        if (!$client->domain || $client->domain_type !== 'cosmotown') {
            return redirect()->route('portal.dashboard', $token);
        }

        $cosmotown = $this->cosmotown;
        $domainInfo = null;
        $error = null;

        if ($cosmotown->isConfigured()) {
            try {
                $domainInfo = $cosmotown->normalizeDomainInfo(
                    $cosmotown->domainInfo($client->domain)
                );
            } catch (\Throwable $e) {
                $error = 'No se pudo obtener la información del dominio.';
            }
        }

        return view('portal.domain', compact('client', 'domainInfo', 'error'));
    }

    public function domainDns(string $token)
    {
        $client = $this->client();

        if (!$client->domain || $client->domain_type !== 'cosmotown') {
            return response()->json(['error' => 'Sin dominio Cosmotown asignado.'], 422);
        }

        $cosmotown = $this->cosmotown;

        if (!$cosmotown->isConfigured()) {
            return response()->json(['error' => 'Cosmotown no configurado.'], 422);
        }

        try {
            return response()->json($cosmotown->getDnsSettings($client->domain));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function saveDomainDns(Request $request, string $token)
    {
        $client = $this->client();

        if (!$client->domain || $client->domain_type !== 'cosmotown') {
            return response()->json(['error' => 'Sin dominio Cosmotown asignado.'], 422);
        }

        $validated = $request->validate(['records' => 'required|array']);

        $cosmotown = $this->cosmotown;

        if (!$cosmotown->isConfigured()) {
            return response()->json(['error' => 'Cosmotown no configurado.'], 422);
        }

        try {
            $records = $cosmotown->denormalizeDnsRecords($validated['records']);
            $cosmotown->saveDnsSettings($client->domain, $records);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function saveDomainNameservers(Request $request, string $token)
    {
        $client = $this->client();

        if (!$client->domain || $client->domain_type !== 'cosmotown') {
            return response()->json(['error' => 'Sin dominio Cosmotown asignado.'], 422);
        }

        $validated = $request->validate([
            'nameservers'   => 'required|array|min:1|max:4',
            'nameservers.*' => 'required|string|max:253',
        ]);

        $cosmotown = $this->cosmotown;

        if (!$cosmotown->isConfigured()) {
            return response()->json(['error' => 'Cosmotown no configurado.'], 422);
        }

        try {
            $cosmotown->saveNameservers($client->domain, $validated['nameservers']);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
