<?php

namespace App\Http\Controllers\Portal;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\FacturapiService;
use Illuminate\Http\Request;

/**
 * Datos fiscales del cliente (RFC, régimen, uso de CFDI y domicilio).
 */

class FiscalDataController extends PortalController
{
    public function __construct(
        private FacturapiService $facturapi,
    ) {}

    public function editFiscalData(string $token)
    {
        $client = $this->client();
        $taxSystems = [
            '601' => '601 - General de Ley Personas Morales',
            '603' => '603 - Personas Morales con Fines no Lucrativos',
            '605' => '605 - Sueldos y Salarios',
            '606' => '606 - Arrendamiento',
            '608' => '608 - Demás ingresos',
            '612' => '612 - Personas Físicas con Act. Empresariales',
            '616' => '616 - Sin obligaciones fiscales',
            '621' => '621 - Incorporación Fiscal',
            '625' => '625 - Plataformas Tecnológicas',
            '626' => '626 - Régimen Simplificado de Confianza',
        ];
        $cfdiUses = [
            'G01' => 'G01 - Adquisición de mercancías',
            'G02' => 'G02 - Devoluciones, descuentos o bonificaciones',
            'G03' => 'G03 - Gastos en general',
            'I01' => 'I01 - Construcciones',
            'I04' => 'I04 - Equipo de cómputo y accesorios',
            'I06' => 'I06 - Comunicaciones telefónicas',
            'S01' => 'S01 - Sin efectos fiscales',
            'CP01' => 'CP01 - Pagos',
        ];

        return view('portal.fiscal-edit', compact('client', 'taxSystems', 'cfdiUses'));
    }

    public function updateFiscalData(Request $request, string $token)
    {
        $client = $this->client();

        $validated = $request->validate([
            'legal_name'           => 'required|string|max:255',
            'tax_id'               => 'required|string|max:20',
            'tax_system'           => 'required|string',
            'cfdi_use'             => 'required|string',
            'email'                => 'nullable|email|max:255',
            'phone'                => 'nullable|string|max:50',
            'address_zip'          => 'required|string|max:10',
            'address_street'       => 'nullable|string|max:255',
            'address_exterior'     => 'nullable|string|max:50',
            'address_interior'     => 'nullable|string|max:50',
            'address_neighborhood' => 'nullable|string|max:255',
            'address_city'         => 'nullable|string|max:255',
            'address_municipality' => 'nullable|string|max:255',
            'address_state'        => 'nullable|string|max:255',
        ]);

        $client->update($validated);

        // Sincronizar con FacturAPI si hay API key
        if (Setting::get('facturapi_api_key')) {
            try {
                $this->facturapi->syncCustomer($client);
            } catch (\Throwable) {}
        }

        ActivityLog::log('fiscal_updated', $client, "Datos fiscales actualizados desde el portal por {$client->legal_name}");

        return redirect()->route('portal.dashboard', $token)
            ->with('success', 'Datos fiscales actualizados correctamente.');
    }
}
