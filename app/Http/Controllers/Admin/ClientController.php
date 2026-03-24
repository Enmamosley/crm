<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\FacturapiService;
use App\Services\TwentyIService;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::with('lead')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('legal_name', 'like', "%{$s}%")
                  ->orWhere('tax_id', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $clients = $query->paginate(20)->withQueryString();

        return view('admin.clients.index', compact('clients'));
    }

    public function create(Request $request)
    {
        // Puede venir desde el lead con lead_id prefilled
        $lead = $request->filled('lead_id') ? Lead::find($request->lead_id) : null;
        $leads = Lead::orderBy('name')->get(['id', 'name', 'email', 'phone', 'business']);
        $taxSystems = $this->taxSystems();
        $cfdiUses   = $this->cfdiUses();
        return view('admin.clients.create', compact('lead', 'leads', 'taxSystems', 'cfdiUses'));
    }

    public function store(Request $request)
    {
        $isPublico = $request->input('billing_type') === 'publico_general';

        $validated = $request->validate([
            'lead_id'              => 'required|exists:leads,id',
            'billing_type'         => 'nullable|in:fiscal,publico_general',
            'legal_name'           => $isPublico ? 'nullable|string|max:255' : 'required|string|max:255',
            'tax_id'               => $isPublico ? 'nullable|string|max:20'  : 'required|string|max:20',
            'tax_system'           => $isPublico ? 'nullable|string'         : 'required|string',
            'cfdi_use'             => $isPublico ? 'nullable|string'         : 'required|string',
            'email'                => 'nullable|email|max:255',
            'phone'                => 'nullable|string|max:50',
            'address_zip'          => $isPublico ? 'nullable|string|max:10'  : 'required|string|max:10',
            'address_street'       => 'nullable|string|max:255',
            'address_exterior'     => 'nullable|string|max:50',
            'address_interior'     => 'nullable|string|max:50',
            'address_neighborhood' => 'nullable|string|max:255',
            'address_city'         => 'nullable|string|max:255',
            'address_municipality' => 'nullable|string|max:255',
            'address_state'        => 'nullable|string|max:255',
            'address_country'      => 'nullable|string|max:10',
            'twentyi_package_id'   => 'nullable|string|max:50',
            'domain'               => 'nullable|string|max:253',
            'domain_type'          => 'nullable|in:cosmotown,own',
            'notes'                => 'nullable|string',
        ]);

        if ($isPublico) {
            $validated['billing_type'] = 'publico_general';
            $validated['legal_name']   = 'PUBLICO EN GENERAL';
            $validated['tax_id']       = 'XAXX010101000';
            $validated['tax_system']   = '616';
            $validated['cfdi_use']     = 'S01';
            $validated['address_zip']  = $validated['address_zip'] ?? '06300';
        } else {
            $validated['billing_type'] = 'fiscal';
        }

        $client = Client::create($validated);

        ActivityLog::log('client_created', $client, "Cliente '{$client->legal_name}' creado");

        // Intentar sincronizar con FacturAPI si hay API key
        if (Setting::get('facturapi_api_key')) {
            try {
                (new FacturapiService())->syncCustomer($client);
            } catch (\Throwable $e) {
                session()->flash('warning', 'Cliente creado. No se pudo sincronizar con FacturAPI: ' . $e->getMessage());
                return redirect()->route('admin.clients.show', $client);
            }
        }

        return redirect()->route('admin.clients.show', $client)
            ->with('success', 'Cliente creado correctamente.');
    }

    public function show(Client $client)
    {
        $client->load(['lead', 'invoices.quote', 'documents', 'tasks.assignee']);
        return view('admin.clients.show', compact('client'));
    }

    public function edit(Client $client)
    {
        $taxSystems = $this->taxSystems();
        $cfdiUses   = $this->cfdiUses();
        return view('admin.clients.edit', compact('client', 'taxSystems', 'cfdiUses'));
    }

    public function update(Request $request, Client $client)
    {
        $isPublico = $request->input('billing_type') === 'publico_general';

        $validated = $request->validate([
            'billing_type'         => 'nullable|in:fiscal,publico_general',
            'legal_name'           => $isPublico ? 'nullable|string|max:255' : 'required|string|max:255',
            'tax_id'               => $isPublico ? 'nullable|string|max:20'  : 'required|string|max:20',
            'tax_system'           => $isPublico ? 'nullable|string'         : 'required|string',
            'cfdi_use'             => $isPublico ? 'nullable|string'         : 'required|string',
            'email'                => 'nullable|email|max:255',
            'phone'                => 'nullable|string|max:50',
            'address_zip'          => $isPublico ? 'nullable|string|max:10'  : 'required|string|max:10',
            'address_street'       => 'nullable|string|max:255',
            'address_exterior'     => 'nullable|string|max:50',
            'address_interior'     => 'nullable|string|max:50',
            'address_neighborhood' => 'nullable|string|max:255',
            'address_city'         => 'nullable|string|max:255',
            'address_municipality' => 'nullable|string|max:255',
            'address_state'        => 'nullable|string|max:255',
            'address_country'      => 'nullable|string|max:10',
            'twentyi_package_id'   => 'nullable|string|max:50',
            'domain'               => 'nullable|string|max:253',
            'domain_type'          => 'nullable|in:cosmotown,own',
            'notes'                => 'nullable|string',
        ]);

        if ($isPublico) {
            $validated['billing_type'] = 'publico_general';
            $validated['legal_name']   = 'PUBLICO EN GENERAL';
            $validated['tax_id']       = 'XAXX010101000';
            $validated['tax_system']   = '616';
            $validated['cfdi_use']     = 'S01';
            $validated['address_zip']  = $validated['address_zip'] ?? '06300';
        } else {
            $validated['billing_type'] = 'fiscal';
        }

        $client->update($validated);

        ActivityLog::log('client_updated', $client, "Cliente '{$client->legal_name}' actualizado");

        // Re-sincronizar con FacturAPI
        if (Setting::get('facturapi_api_key')) {
            try {
                (new FacturapiService())->syncCustomer($client);
            } catch (\Throwable $e) {
                session()->flash('warning', 'Cliente actualizado. No se pudo sincronizar con FacturAPI: ' . $e->getMessage());
                return redirect()->route('admin.clients.show', $client);
            }
        }

        return redirect()->route('admin.clients.show', $client)
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Client $client)
    {
        ActivityLog::log('client_deleted', $client, "Cliente '{$client->legal_name}' eliminado");
        $client->delete();
        return redirect()->route('admin.clients.index')
            ->with('success', 'Cliente eliminado.');
    }

    /**
     * AJAX: crea un paquete de hosting en 20i para el dominio del cliente
     * y guarda el Package ID resultante en el cliente.
     * POST /admin/clients/{client}/create-hosting
     */
    public function createHosting(Client $client)
    {
        if (!$client->domain) {
            return response()->json(['error' => 'El cliente no tiene ningún dominio configurado.'], 422);
        }

        if ($client->twentyi_package_id) {
            return response()->json(['error' => 'Este cliente ya tiene un Package ID asignado (' . $client->twentyi_package_id . ').'], 422);
        }

        if (!Setting::get('twentyi_api_key')) {
            return response()->json(['error' => 'No hay API key de 20i configurada en Ajustes.'], 422);
        }

        if (!Setting::get('twentyi_package_bundle_id')) {
            return response()->json(['error' => 'No hay Package Bundle ID configurado en Ajustes → 20i.'], 422);
        }

        try {
            $packageId = (new TwentyIService())->createHostingPackage($client->domain);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $client->update(['twentyi_package_id' => $packageId]);

        ActivityLog::log('client_updated', $client, "Paquete 20i creado automáticamente ({$packageId}) para dominio {$client->domain}");

        return response()->json([
            'success'    => true,
            'package_id' => $packageId,
            'message'    => "Paquete de hosting creado correctamente (ID: {$packageId})",
        ]);
    }

    // ──────────────────────────────────
    // Catálogos SAT helpers
    // ──────────────────────────────────

    public function syncFacturapi(Client $client)
    {
        if (!Setting::get('facturapi_api_key')) {
            return back()->with('error', 'No hay API key de FacturAPI configurada en Ajustes.');
        }

        try {
            (new FacturapiService())->syncCustomer($client);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al sincronizar con FacturAPI: ' . $e->getMessage());
        }

        ActivityLog::log('client_updated', $client, "Cliente '{$client->legal_name}' sincronizado manualmente con FacturAPI ({$client->facturapi_customer_id})");

        return back()->with('success', 'Cliente sincronizado con FacturAPI correctamente.');
    }

    private function taxSystems(): array
    {
        return [
            '601' => '601 - General de Ley Personas Morales',
            '603' => '603 - Personas Morales con Fines no Lucrativos',
            '605' => '605 - Sueldos y Salarios',
            '606' => '606 - Arrendamiento',
            '608' => '608 - Demás ingresos',
            '612' => '612 - Personas Físicas con Actividades Empresariales',
            '616' => '616 - Sin obligaciones fiscales',
            '621' => '621 - Incorporación Fiscal',
            '625' => '625 - Plataformas Tecnológicas',
            '626' => '626 - Régimen Simplificado de Confianza',
        ];
    }

    private function cfdiUses(): array
    {
        return [
            'G01' => 'G01 - Adquisición de mercancías',
            'G02' => 'G02 - Devoluciones, descuentos o bonificaciones',
            'G03' => 'G03 - Gastos en general',
            'I01' => 'I01 - Construcciones',
            'I04' => 'I04 - Equipo de cómputo y accesorios',
            'I06' => 'I06 - Comunicaciones telefónicas',
            'S01' => 'S01 - Sin efectos fiscales',
            'CP01' => 'CP01 - Pagos',
        ];
    }
}
