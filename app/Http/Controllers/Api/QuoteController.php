<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'required|exists:services,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $ivaPercentage = (float) Setting::get('iva_percentage', 16);

        $quote = Quote::create([
            'quote_number' => Quote::generateQuoteNumber(),
            'lead_id' => $validated['lead_id'],
            'iva_percentage' => $ivaPercentage,
            'status' => 'borrador',
            'valid_until' => now()->addDays(30),
            'notes' => $validated['notes'] ?? null,
            'subtotal' => 0,
            'iva_amount' => 0,
            'total' => 0,
        ]);

        foreach ($validated['items'] as $item) {
            $service = Service::find($item['service_id']);
            $unitPrice = $item['unit_price'] ?? $service->price;
            $total = $item['quantity'] * $unitPrice;

            $quote->items()->create([
                'service_id' => $item['service_id'],
                'description' => $service->name,
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'total' => $total,
            ]);
        }

        $quote->recalculate();
        $quote->load(['lead', 'items.service']);

        // Update lead status
        $lead = Lead::find($validated['lead_id']);
        if (!in_array($lead->status, ['cerrado', 'cotizado'])) {
            $lead->updateStatus('cotizado', 'agente');
        }

        return response()->json([
            'success' => true,
            'data' => $quote,
            'message' => 'Cotización generada exitosamente.',
        ], 201);
    }

    public function updateStatus(Request $request, Quote $quote)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:borrador,enviada,aceptada,rechazada,vencida',
        ]);

        $quote->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'data'    => $quote->fresh(['lead', 'items']),
            'message' => 'Estado de la cotización actualizado.',
        ]);
    }

    public function downloadPdf(Quote $quote)
    {
        $quote->load(['lead', 'items.service']);

        $settings = [
            'company_name' => Setting::get('company_name', 'Mi Empresa'),
            'company_address' => Setting::get('company_address', ''),
            'company_phone' => Setting::get('company_phone', ''),
            'company_email' => Setting::get('company_email', ''),
            'company_rfc' => Setting::get('company_rfc', ''),
            'company_logo' => Setting::get('company_logo', ''),
        ];

        $pdf = Pdf::loadView('pdf.quote', compact('quote', 'settings'));

        return $pdf->download("cotizacion-{$quote->quote_number}.pdf");
    }
}
