<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\TwentyIService;
use Illuminate\Http\Request;

class DnsController extends Controller
{
    public function index(Client $client)
    {
        if (!$client->twentyi_package_id) {
            return redirect()->route('admin.clients.show', $client)
                ->with('error', 'Este cliente no tiene un Package ID de 20i configurado.');
        }

        $records       = [];
        $error         = null;
        $configured    = (bool) \App\Models\Setting::get('twentyi_api_key');

        if ($configured) {
            try {
                $records = (new TwentyIService())->listDnsRecords($client);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('admin.dns.index', compact('client', 'records', 'error', 'configured'));
    }

    public function store(Request $request, Client $client)
    {
        if (!$client->twentyi_package_id) {
            return back()->with('error', 'Cliente sin Package ID de 20i.');
        }

        $validated = $request->validate([
            'type'     => 'required|in:A,AAAA,CNAME,MX,TXT,NS,SRV',
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

        return back()->with('success', 'Registro DNS añadido correctamente.');
    }

    public function destroy(Client $client, string $recordId)
    {
        if (!$client->twentyi_package_id) {
            return back()->with('error', 'Cliente sin Package ID de 20i.');
        }

        try {
            (new TwentyIService())->deleteDnsRecord($client, $recordId);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al eliminar registro: ' . $e->getMessage());
        }

        return back()->with('success', 'Registro DNS eliminado.');
    }
}
