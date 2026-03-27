<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\QuoteSent;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Order;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function index()
    {
        $quotes = Quote::with('lead')->latest()->paginate(15)->appends(request()->query());
        return view('admin.quotes.index', compact('quotes'));
    }

    public function create(Request $request)
    {
        $leads = Lead::all();
        $services = Service::active()->with('category')->get();
        $selectedLead = $request->filled('lead_id') ? Lead::find($request->lead_id) : null;
        $ivaPercentage = Setting::get('iva_percentage', 16);
        $bundles = \App\Models\ServiceBundle::active()->with(['services', 'items'])->get();

        return view('admin.quotes.create', compact('leads', 'services', 'selectedLead', 'ivaPercentage', 'bundles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'required|exists:services,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
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
            $total = $item['quantity'] * $item['unit_price'];

            $quote->items()->create([
                'service_id' => $item['service_id'],
                'description' => $service->name,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $total,
            ]);
        }

        $quote->recalculate();

        // Update lead status to cotizado
        $lead = Lead::find($validated['lead_id']);
        if ($lead->status !== 'cerrado') {
            $lead->updateStatus('cotizado', 'admin');
        }

        ActivityLog::log('quote_created', $quote, "Cotización {$quote->quote_number} creada para {$lead->name}");

        return redirect()->route('admin.quotes.show', $quote)
            ->with('success', 'Cotización generada exitosamente.');
    }

    public function show(Quote $quote)
    {
        $quote->load(['lead', 'items.service', 'invoices']);
        $client = Client::where('lead_id', $quote->lead_id)->first();
        return view('admin.quotes.show', compact('quote', 'client'));
    }

    public function edit(Quote $quote)
    {
        $leads = Lead::all();
        $services = Service::active()->with('category')->get();
        $quote->load('items');
        return view('admin.quotes.edit', compact('quote', 'leads', 'services'));
    }

    public function update(Request $request, Quote $quote)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'required|exists:services,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $quote->items()->delete();

        foreach ($validated['items'] as $item) {
            $service = Service::find($item['service_id']);
            $total = $item['quantity'] * $item['unit_price'];

            $quote->items()->create([
                'service_id' => $item['service_id'],
                'description' => $service->name,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $total,
            ]);
        }

        $quote->update(['notes' => $validated['notes'] ?? null]);
        $quote->recalculate();

        ActivityLog::log('quote_updated', $quote, "Cotización {$quote->quote_number} actualizada");

        return redirect()->route('admin.quotes.show', $quote)
            ->with('success', 'Cotización actualizada.');
    }

    public function destroy(Quote $quote)
    {
        ActivityLog::log('quote_deleted', $quote, "Cotización {$quote->quote_number} eliminada");
        $quote->delete();
        return redirect()->route('admin.quotes.index')
            ->with('success', 'Cotización eliminada.');
    }

    public function markAsSent(Quote $quote)
    {
        $quote->update(['status' => 'enviada']);
        ActivityLog::log('quote_sent', $quote, "Cotización {$quote->quote_number} enviada");

        // Enviar email al cliente si tiene portal
        $lead = $quote->lead;
        $client = Client::where('lead_id', $lead->id)->first();
        if ($client && $client->email && $client->portal_token) {
            $portalUrl = route('portal.quote.show', [$client->portal_token, $quote]);
            try {
                Mail::to($client->email)->send(new QuoteSent($quote, $portalUrl));
            } catch (\Throwable $e) {
                Log::warning('Quote email failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
            }
        }

        return redirect()->route('admin.quotes.show', $quote)
            ->with('success', 'Cotización marcada como enviada.');
    }

    public function convertToOrder(Quote $quote)
    {
        $client = Client::where('lead_id', $quote->lead_id)->first();

        if (!$client) {
            return redirect()->route('admin.clients.create', ['lead_id' => $quote->lead_id])
                ->with('warning', 'Crea el cliente con datos fiscales antes de generar la orden de servicio.');
        }

        $existing = $quote->invoices()->first();
        if ($existing) {
            return redirect()->route('admin.invoices.show', $existing)
                ->with('info', 'Esta cotización ya tiene una orden de servicio generada.');
        }

        $order = Order::create([
            'client_id'      => $client->id,
            'quote_id'       => $quote->id,
            'series'         => 'F',
            'payment_form'   => '99',
            'payment_method' => 'PPD',
            'use_cfdi'       => 'G03',
            'subtotal'       => $quote->subtotal,
            'iva_amount'     => $quote->iva_amount,
            'total'          => $quote->total,
            'status'         => 'draft',
        ]);

        if (in_array($quote->status, ['borrador', 'enviada'])) {
            $quote->update(['status' => 'aceptada']);
        }

        ActivityLog::log('quote_converted', $order, "Cotización {$quote->quote_number} convertida a orden de servicio");

        return redirect()->route('admin.invoices.show', $order)
            ->with('success', '¡Orden creada! Configura los datos fiscales y envía el link de cobro al cliente.');
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
