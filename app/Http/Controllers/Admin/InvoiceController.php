<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceCreated;
use App\Mail\InvoicePaymentLink;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Service;
use App\Services\FacturapiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = ClientInvoice::with(['client', 'quote'])
            ->latest()
            ->paginate(20);

        return view('admin.invoices.index', compact('invoices'));
    }

    public function create(Request $request)
    {
        $client = $request->filled('client_id') ? Client::find($request->client_id) : null;
        $quote  = $request->filled('quote_id')  ? Quote::with('items')->find($request->quote_id) : null;
        $clients = Client::orderBy('name')->get(['id', 'name', 'legal_name', 'tax_id']);

        $paymentForms = $this->paymentForms();
        $services = Service::active()->orderBy('name')->get(['id', 'name', 'price']);
        $defaultItems = old('items', [[
            'description'     => '',
            'quantity'        => 1,
            'unit_price'      => '',
            'sat_product_key' => '80101501',
            'sat_unit_key'    => 'E48',
            'sat_unit_name'   => 'Servicio',
            'tax_object'      => '02',
            'iva_exempt'      => false,
        ]]);
        $ivaRate = (float) \App\Models\Setting::get('iva_percentage', 16) / 100;

        return view('admin.invoices.create', compact('client', 'quote', 'clients', 'paymentForms', 'services', 'defaultItems', 'ivaRate'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id'      => 'required|exists:clients,id',
            'quote_id'       => 'nullable|exists:quotes,id',
            'series'         => 'required|string|max:10',
            'folio_number'   => 'nullable|integer|min:1',
            'payment_form'   => 'required|string',
            'payment_method' => 'required|string|in:PUE,PPD',
            'use_cfdi'       => 'required|string',
            'notes'          => 'nullable|string',
            // Ítems manuales (cuando no hay cotización)
            'items'                    => 'nullable|array',
            'items.*.description'      => 'required_with:items|string|max:500',
            'items.*.quantity'         => 'required_with:items|numeric|min:0.001',
            'items.*.unit_price'       => 'required_with:items|numeric|min:0',
            'items.*.sat_product_key'  => 'nullable|string|max:20',
            'items.*.sat_unit_key'     => 'nullable|string|max:10',
            'items.*.sat_unit_name'    => 'nullable|string|max:100',
            'items.*.tax_object'       => 'nullable|string|max:5',
            'items.*.iva_exempt'       => 'nullable|boolean',
        ]);

        // Calcular montos
        $subtotal = 0; $iva = 0; $total = 0;
        $ivaRate  = (float) \App\Models\Setting::get('iva_percentage', 16) / 100;

        if (!empty($validated['quote_id'])) {
            $quote    = Quote::find($validated['quote_id']);
            $subtotal = $quote->subtotal;
            $iva      = $quote->iva_amount;
            $total    = $quote->total;
        } elseif (!empty($validated['items'])) {
            foreach ($validated['items'] as $row) {
                $lineTotal = (float)$row['quantity'] * (float)$row['unit_price'];
                $subtotal += $lineTotal;
            }
            $iva   = $subtotal * $ivaRate;
            $total = $subtotal + $iva;
        }

        $invoice = DB::transaction(function () use ($validated, $subtotal, $iva, $total) {
            $invoice = ClientInvoice::create(array_merge(
                $validated,
                ['subtotal' => $subtotal, 'iva_amount' => $iva, 'total' => $total, 'status' => 'draft']
            ));

            // Guardar ítems manuales si no viene de cotización
            if (empty($validated['quote_id']) && !empty($validated['items'])) {
                foreach ($validated['items'] as $row) {
                    $lineTotal = (float)$row['quantity'] * (float)$row['unit_price'];
                    $invoice->items()->create([
                        'description'     => $row['description'],
                        'sat_product_key' => $row['sat_product_key'] ?? '80101501',
                        'sat_unit_key'    => $row['sat_unit_key']    ?? 'E48',
                        'sat_unit_name'   => $row['sat_unit_name']   ?? 'Servicio',
                        'tax_object'      => $row['tax_object']      ?? '02',
                        'iva_exempt'      => !empty($row['iva_exempt']),
                        'quantity'        => $row['quantity'],
                        'unit_price'      => $row['unit_price'],
                        'total'           => $lineTotal,
                    ]);
                }
            }

            return $invoice;
        });

        ActivityLog::log('invoice_created', $invoice, "Factura creada para cliente #{$validated['client_id']}");

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Factura creada. Ya puedes timbrarla.');
    }

    public function show(ClientInvoice $invoice)
    {
        $invoice->load(['client.lead', 'quote.items.service', 'payments', 'items']);
        return view('admin.invoices.show', compact('invoice'));
    }

    /**
     * Enviar link de cobro al cliente por email.
     */
    public function sendPaymentLink(ClientInvoice $invoice)
    {
        $client = $invoice->client;

        if (!$client?->email) {
            return back()->with('error', 'El cliente no tiene email registrado.');
        }

        if (!$client->portal_token) {
            return back()->with('error', 'El cliente no tiene portal activo. Actívalo desde el perfil del cliente.');
        }

        $checkoutUrl = route('portal.checkout', [$client->portal_token, $invoice]);

        try {
            Mail::to($client->email)->send(new InvoicePaymentLink($invoice, $checkoutUrl));

            // Mark as pending once the collection link is sent.
            if ($invoice->status === 'draft') {
                $invoice->update(['status' => 'pending']);
            }

            ActivityLog::log('payment_link_sent', $invoice, "Link de cobro enviado a {$client->email}");
            return back()->with('success', "Link de cobro enviado a {$client->email}");
        } catch (\Throwable $e) {
            Log::error('Payment link email failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'No se pudo enviar el email: ' . $e->getMessage());
        }
    }

    /**
     * Registrar pago manual (efectivo, transferencia, etc.).
     */
    public function registerManualPayment(Request $request, ClientInvoice $invoice)
    {
        if ($invoice->isPaid()) {
            return back()->with('error', 'Esta orden ya está marcada como pagada.');
        }

        $validated = $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'payment_form' => 'required|string',
            'notes'        => 'nullable|string|max:500',
            'proof'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('payment-proofs', 'public');
        }

        $invoice->payments()->create([
            'amount'            => $validated['amount'],
            'currency'          => 'MXN',
            'status'            => 'approved',
            'payment_type'      => 'manual',
            'payment_method_id' => $validated['payment_form'],
            'proof_path'        => $proofPath,
            'payment_notes'     => $validated['notes'] ?? null,
            'paid_at'           => now(),
        ]);

        $updates = ['status' => 'sent', 'paid_at' => now()];
        if ($validated['payment_form'] !== '99') {
            $updates['payment_form'] = $validated['payment_form'];
        }
        $invoice->update($updates);

        ActivityLog::log('manual_payment_registered', $invoice,
            "Pago manual de \${$validated['amount']} MXN registrado");

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Pago registrado. Ahora puedes timbrar la factura.');
    }

    public function approveTransfer(Payment $payment)
    {
        abort_if($payment->status !== 'pending' || $payment->payment_type !== 'transfer', 422, 'Este pago no se puede confirmar.');

        $payment->update([
            'status'  => 'approved',
            'paid_at' => now(),
        ]);

        $invoice = $payment->invoice;
        $invoice->update(['status' => 'sent', 'paid_at' => now()]);

        ActivityLog::log('transfer_approved', $invoice,
            "Transferencia de \${$payment->amount} MXN confirmada por " . auth()->user()->name);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Transferencia confirmada. Ahora puedes timbrar la factura.');
    }

    /**
     * Cambiar estado de la factura manualmente.
     */
    public function updateStatus(Request $request, ClientInvoice $invoice)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,sent,pending',
        ]);

        $invoice->update(['status' => $validated['status']]);

        $labels = ['draft' => 'Borrador', 'sent' => 'Enviada', 'pending' => 'Procesando'];
        ActivityLog::log('invoice_status_changed', $invoice,
            "Estado de factura {$invoice->folio()} cambiado a '{$labels[$validated['status']]}' por " . auth()->user()->name);

        return back()->with('success', "Estado actualizado a: {$labels[$validated['status']]}");
    }

    /**
     * Timbrar con FacturAPI (el botón "Facturar").
     */
    public function stamp(ClientInvoice $invoice)
    {
        if ($invoice->isStamped()) {
            return back()->with('error', 'Esta factura ya fue timbrada.');
        }

        $apiKey = \App\Models\Setting::get('facturapi_api_key');
        if (!$apiKey) {
            return back()->with('error', 'Configura el API Key de FacturAPI en Configuración antes de facturar.');
        }

        try {
            $result = (new FacturapiService())->stampInvoice($invoice);

            if ($result['success']) {
                ActivityLog::log('invoice_stamped', $invoice, "Factura {$invoice->folio()} timbrada ante el SAT");

                // Enviar email de factura al cliente
                $client = $invoice->client;
                if ($client && $client->email && $client->portal_token) {
                    try {
                        $portalUrl = route('portal.dashboard', $client->portal_token);
                        Mail::to($client->email)->send(new InvoiceCreated($invoice, $portalUrl));
                    } catch (\Throwable $e) {
                        Log::warning('Invoice email failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
                    }
                }

                return redirect()->route('admin.invoices.show', $invoice)
                    ->with('success', '¡Factura timbrada exitosamente ante el SAT!');
            }

            $msg = $result['data']['message'] ?? 'Error al timbrar en FacturAPI.';
            return back()->with('error', $msg);
        } catch (\Throwable $e) {
            Log::error('FacturAPI stamp failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error al conectar con FacturAPI. Revisa el log de actividad.');
        }
    }

    /**
     * Anular orden no timbrada (draft/sent) — sin pasar por el SAT.
     */
    public function void(Request $request, ClientInvoice $invoice)
    {
        if (!$invoice->isVoidable()) {
            return back()->with('error', 'Solo se pueden anular órdenes que aún no han sido timbradas. Si ya hay un CFDI emitido, usa "Cancelar ante SAT".');
        }

        $invoice->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        ActivityLog::log('invoice_voided', $invoice, "Orden {$invoice->folio()} anulada manualmente");

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Orden anulada correctamente.');
    }

    /**
     * Cancelar factura timbrada.
     */
    public function cancel(Request $request, ClientInvoice $invoice)
    {
        $request->validate(['motive' => 'required|string|in:01,02,03,04']);

        try {
            $result = (new FacturapiService())->cancelInvoice($invoice, $request->motive);

            if ($result['success']) {
                ActivityLog::log('invoice_cancelled', $invoice, "Factura {$invoice->folio()} cancelada ante el SAT");
                return redirect()->route('admin.invoices.show', $invoice)
                    ->with('success', 'Factura cancelada ante el SAT.');
            }

            $msg = $result['data']['message'] ?? 'Error al cancelar.';
            return back()->with('error', $msg);
        } catch (\Throwable $e) {
            Log::error('FacturAPI cancel failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error al conectar con FacturAPI. Revisa el log de actividad.');
        }
    }

    /**
     * Descargar PDF de factura timbrada.
     */
    public function downloadPdf(ClientInvoice $invoice)
    {
        $pdf = (new FacturapiService())->downloadPdf($invoice);

        if (!$pdf) {
            return back()->with('error', 'No se pudo obtener el PDF.');
        }

        $filename = 'factura-' . $invoice->folio() . '.pdf';
        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Descargar XML de factura timbrada.
     */
    public function downloadXml(ClientInvoice $invoice)
    {
        $xml = (new FacturapiService())->downloadXml($invoice);

        if (!$xml) {
            return back()->with('error', 'No se pudo obtener el XML.');
        }

        $filename = 'factura-' . $invoice->folio() . '.xml';
        return response($xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function paymentForms(): array
    {
        return [
            '01' => '01 - Efectivo',
            '02' => '02 - Cheque nominativo',
            '03' => '03 - Transferencia electrónica',
            '04' => '04 - Tarjeta de crédito',
            '28' => '28 - Tarjeta de débito',
            '99' => '99 - Por definir',
        ];
    }
}
