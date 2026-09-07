<?php

namespace App\Http\Controllers\Portal;

use App\Services\TwentyIService;
use Illuminate\Http\Request;

/**
 * Registros DNS del hosting del cliente (API de 20i).
 */

class DnsController extends PortalController
{
    public function dns(string $token)
    {
        $client = $this->client();

        if (!$client->twentyi_package_id) {
            return redirect()->route('portal.dashboard', $token)
                ->with('error', 'No tienes un paquete de hosting configurado.');
        }

        $records = [];
        $error   = null;

        if (Setting::get('twentyi_api_key')) {
            try {
                $records = (new TwentyIService())->listDnsRecords($client);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('portal.dns', compact('client', 'records', 'error'));
    }

    public function storeDns(Request $request, string $token)
    {
        $client = $this->client();

        if (!$client->twentyi_package_id) {
            return back()->with('error', 'Sin paquete de hosting.');
        }

        $validated = $request->validate([
            'type'     => 'required|in:A,AAAA,CNAME,MX,TXT',
            'host'     => 'required|string|max:255',
            'value'    => 'required|string|max:2048',
            'ttl'      => 'nullable|integer|min:60|max:86400',
            'priority' => 'nullable|integer|min:0|max:65535',
        ]);

        try {
            (new TwentyIService())->addDnsRecord(
                $client,
                $validated['type'],
                $validated['host'],
                $validated['value'],
                $validated['ttl']      ?? 3600,
                $validated['priority'] ?? 10,
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al añadir registro: ' . $e->getMessage());
        }

        return back()->with('success', 'Registro DNS añadido.');
    }

    public function updateDns(Request $request, string $token, string $recordId)
    {
        $client = $this->client();

        if (!$client->twentyi_package_id) {
            return back()->with('error', 'Sin paquete de hosting.');
        }

        $validated = $request->validate([
            'type'     => 'required|in:A,AAAA,CNAME,MX,TXT',
            'host'     => 'required|string|max:255',
            'value'    => 'required|string|max:2048',
            'ttl'      => 'nullable|integer|min:60|max:86400',
            'priority' => 'nullable|integer|min:0|max:65535',
        ]);

        try {
            (new TwentyIService())->updateDnsRecord(
                $client,
                $recordId,
                $validated['type'],
                $validated['host'],
                $validated['value'],
                $validated['ttl']      ?? 3600,
                $validated['priority'] ?? 10,
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al editar registro: ' . $e->getMessage());
        }

        return back()->with('success', 'Registro DNS actualizado.');
    }

    public function destroyDns(Request $request, string $token, string $recordId)
    {
        $client = $this->client();

        if (!$client->twentyi_package_id) {
            return back()->with('error', 'Sin paquete de hosting.');
        }

        try {
            (new TwentyIService())->deleteDnsRecord($client, $recordId);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }

        return back()->with('success', 'Registro eliminado.');
    }
}
