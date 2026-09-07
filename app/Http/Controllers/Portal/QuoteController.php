<?php

namespace App\Http\Controllers\Portal;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Quote;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Cotizaciones enviadas al cliente: consulta, aceptación y rechazo.
 */

class QuoteController extends PortalController
{
    public function showQuote(string $token, Quote $quote)
    {
        $client = $this->client();
        $this->authorizeQuote($client, $quote);

        $quote->load('items.service');
        return view('portal.quote-show', compact('client', 'quote'));
    }

    public function acceptQuote(string $token, Quote $quote)
    {
        $client = $this->client();
        $this->authorizeQuote($client, $quote);

        if (!in_array($quote->status, ['enviada'])) {
            return back()->with('error', 'Esta cotización no puede ser aceptada.');
        }

        $quote->update(['status' => 'aceptada']);
        ActivityLog::log('quote_accepted', $quote, "Cotización {$quote->quote_number} aceptada por el cliente desde el portal");

        // Generar factura automáticamente a partir de la cotización
        $existingInvoice = Order::where('client_id', $client->id)
            ->where('quote_id', $quote->id)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if ($existingInvoice) {
            // Ya existe factura para esta cotización, llevar al pago
            if (!$existingInvoice->isPaid() && Setting::get('mp_public_key')) {
                return redirect()->route('portal.checkout', [$token, $existingInvoice])
                    ->with('success', '¡Cotización aceptada! Ya tienes una factura generada, procede al pago.');
            }
            return redirect()->route('portal.dashboard', $token)
                ->with('success', '¡Cotización aceptada!');
        }

        $order = Order::create([
            'client_id'      => $client->id,
            'quote_id'       => $quote->id,
            'series'         => 'F',
            'payment_form'   => '99',   // Por definir (el cliente elige al pagar)
            'payment_method' => 'PUE',
            'use_cfdi'       => $client->cfdi_use ?? 'G03',
            'subtotal'       => $quote->subtotal,
            'iva_amount'     => $quote->iva_amount,
            'total'          => $quote->total,
            'status'         => 'draft',
            'notes'          => "Generada automáticamente al aceptar cotización {$quote->quote_number}",
        ]);

        ActivityLog::log('invoice_created', $order, "Factura generada automáticamente al aceptar cotización {$quote->quote_number}");

        // Si Mercado Pago está configurado, llevar directo al checkout
        if (Setting::get('mp_public_key')) {
            return redirect()->route('portal.checkout', [$token, $order])
                ->with('success', '¡Cotización aceptada! Se generó tu factura, procede al pago.');
        }

        return redirect()->route('portal.dashboard', $token)
            ->with('success', '¡Cotización aceptada! Se generó tu factura.');
    }

    public function rejectQuote(Request $request, string $token, Quote $quote)
    {
        $client = $this->client();
        $this->authorizeQuote($client, $quote);

        if (!in_array($quote->status, ['enviada'])) {
            return back()->with('error', 'Esta cotización no puede ser rechazada.');
        }

        $quote->update(['status' => 'rechazada']);
        ActivityLog::log('quote_rejected', $quote, "Cotización {$quote->quote_number} rechazada por el cliente desde el portal");

        return redirect()->route('portal.quote.show', [$token, $quote])
            ->with('success', 'Cotización rechazada. Lamentamos que no haya sido de su interés.');
    }
}
