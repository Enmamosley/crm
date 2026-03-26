<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceCreated;
use App\Mail\InvoicePaymentLink;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Service;
use App\Services\FacturapiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['client', 'quote', 'fiscalDocument'])
            ->latest()
            ->paginate(20);

        return view('admin.orders.index', compact('orders'));
    }

    public function create(Request $request)
    {
        $client  = $request->filled('client_id') ? Client::find($request->client_id) : null;
        $quote   = $request->filled('quote_id')  ? Quote::with('items')->find($request->quote_id) : null;
        $clients = Client::orderBy('legal_name')->get(['id', 'name', 'legal_name', 'tax_id']);

        $paymentForms = $this->paymentForms();
        $services     = Service::active()->orderBy('name')->get(['id', 'name', 'price']);
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

        return view('admin.orders.create', compact('client', 'quote', 'clients', 'paymentForms', 'services', 'defaultItems', 'ivaRate'));
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

        $subtotal = 0; $iva = 0; $total = 0;
        $ivaRate  = (float) \App\Models\Setting::get('iva_percentage', 16) / 100;

        if (!empty($validated['quote_id'])) {
            $quote    = Quote::find($validated['quote_id']);
            $subtotal = $quote->subtotal;
            $iva      = $quote->iva_amount;
            $total    = $quote->total;
        } elseif (!empty($validated['items'])) {
            foreach ($validated['items'] as $row) {
                $subtotal += (float)$row['quantity'] * (float)$row['unit_price'];
            }
            $iva   = $subtotal * $ivaRate;
            $total = $subtotal + $iva;
        }

        $order = DB::transaction(function () use ($validated, $subtotal, $iva, $total) {
            $order = Order::create(array_merge(
                $validated,
                ['subtotal' => $subtotal, 'iva_amount' => $iva, 'total' => $total, 'status' => 'draft']
            ));

            if (empty($validated['quote_id']) && !empty($validated['items'])) {
                foreach ($validated['items'] as $row) {
                    $lineTotal = (float)$row['quantity'] * (float)$row['unit_price'];
                    $order->items()->create([
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

            return $order;
        });

        ActivityLog::log('order_created', $order, "Orden creada para cliente #{$validated['client_id']}");

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Orden creada. Ya puedes enviar el link de cobro o timbrarla.');
    }

    public function show(Order $order)
    {
        $order->load(['client.lead', 'quote.items.service', 'payments', 'items', 'fiscalDocument']);
        return view('admin.orders.show', compact('order'));
    }

    /**
     * Enviar link de cobro al cliente por email.
     */
    public function sendPaymentLink(Order $order)
    {
        $client = $order->client;

        if (!$client?->email) {
            return back()->with('error', 'El cliente no tiene email registrado.');
        }
        if (!$client->portal_token) {
            return back()->with('error', 'El cliente no tiene portal activo. Actívalo desde el perfil del cliente.');
        }

        $checkoutUrl = route('portal.checkout', [$client->portal_token, $order]);

        try {
            Mail::to($client->email)->send(new InvoicePaymentLink($order, $checkoutUrl));

            if ($order->status === 'draft') {
                $order->update(['status' => 'sent']);
            }

            ActivityLog::log('payment_link_sent', $order, "Link de cobro enviado a {$client->email}");
            return back()->with('success', "Link de cobro enviado a {$client->email}");
        } catch (\Throwable $e) {
            Log::error('Payment link email failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'No se pudo enviar el email: ' . $e->getMessage());
        }
    }

    /**
     * Registrar pago manual (efectivo, transferencia, etc.).
     */
    public function registerManualPayment(Request $request, Order $order)
    {
        if ($order->isPaid()) {
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

        $order->payments()->create([
            'amount'            => $validated['amount'],
            'currency'          => 'MXN',
            'status'            => 'approved',
            'payment_type'      => 'manual',
            'payment_method_id' => $validated['payment_form'],
            'proof_path'        => $proofPath,
            'payment_notes'     => $validated['notes'] ?? null,
            'paid_at'           => now(),
        ]);

        $updates = ['status' => 'paid', 'paid_at' => now()];
        if ($validated['payment_form'] !== '99') {
            $updates['payment_form'] = $validated['payment_form'];
        }
        $order->update($updates);

        ActivityLog::log('manual_payment_registered', $order,
            "Pago manual de \${$validated['amount']} MXN registrado");

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Pago registrado. Ahora puedes timbrar la factura.');
    }

    /**
     * Confirmar transferencia bancaria pendiente.
     */
    public function approveTransfer(Payment $payment)
    {
        abort_if($payment->status !== 'pending' || $payment->payment_type !== 'transfer', 422, 'Este pago no se puede confirmar.');

        $payment->update(['status' => 'approved', 'paid_at' => now()]);

        $order = $payment->order;
        $order->update(['status' => 'paid', 'paid_at' => now()]);

        ActivityLog::log('transfer_approved', $order,
            "Transferencia de \${$payment->amount} MXN confirmada por " . auth()->user()->name);

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Transferencia confirmada. Ahora puedes timbrar la factura.');
    }

    /**
     * Cambiar estado de la orden manualmente.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,sent,pending,paid',
        ]);

        $order->update(['status' => $validated['status']]);

        $labels = ['draft' => 'Borrador', 'sent' => 'Enviada', 'pending' => 'Procesando', 'paid' => 'Pagada'];
        ActivityLog::log('order_status_changed', $order,
            "Estado de orden {$order->folio()} cambiado a '{$labels[$validated['status']]}' por " . auth()->user()->name);

        return back()->with('success', "Estado actualizado a: {$labels[$validated['status']]}");
    }

    /**
     * Timbrar con FacturAPI — crea el FiscalDocument.
     */
    public function stamp(Order $order)
    {
        if ($order->isStamped()) {
            return back()->with('error', 'Esta orden ya tiene un CFDI timbrado.');
        }

        $apiKey = \App\Models\Setting::get('facturapi_api_key');
        if (!$apiKey) {
            return back()->with('error', 'Configura el API Key de FacturAPI en Configuración antes de facturar.');
        }

        try {
            $result = (new FacturapiService())->stampInvoice($order);

            if ($result['success']) {
                ActivityLog::log('order_stamped', $order, "Orden {$order->folio()} timbrada ante el SAT");

                $client = $order->client;
                if ($client && $client->email && $client->portal_token) {
                    try {
                        $portalUrl = route('portal.dashboard', $client->portal_token);
                        Mail::to($client->email)->send(new InvoiceCreated($order, $portalUrl));
                    } catch (\Throwable $e) {
                        Log::warning('Invoice email failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                    }
                }

                return redirect()->route('admin.orders.show', $order)
                    ->with('success', '¡CFDI timbrado exitosamente ante el SAT!');
            }

            return back()->with('error', $result['data']['message'] ?? 'Error al timbrar en FacturAPI.');
        } catch (\Throwable $e) {
            Log::error('FacturAPI stamp failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error al conectar con FacturAPI. Revisa el log de actividad.');
        }
    }

    /**
     * Anular orden sin CFDI (sin trámite ante el SAT).
     */
    public function void(Order $order)
    {
        if (!$order->isVoidable()) {
            return back()->with('error', 'No se puede anular esta orden. Si ya tiene un CFDI activo, usa "Cancelar ante SAT".');
        }

        $order->update(['status' => 'cancelled']);
        ActivityLog::log('order_voided', $order, "Orden {$order->folio()} anulada manualmente");

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Orden anulada correctamente.');
    }

    /**
     * Cancelar CFDI timbrado ante el SAT.
     */
    public function cancelFiscalDocument(Request $request, Order $order)
    {
        $request->validate(['motive' => 'required|string|in:01,02,03,04']);

        if (!$order->isCancellable()) {
            return back()->with('error', 'Esta orden no tiene un CFDI activo que cancelar.');
        }

        try {
            $result = (new FacturapiService())->cancelInvoice($order, $request->motive);

            if ($result['success']) {
                ActivityLog::log('fiscal_document_cancelled', $order, "CFDI de orden {$order->folio()} cancelado ante el SAT");
                return redirect()->route('admin.orders.show', $order)
                    ->with('success', 'CFDI cancelado ante el SAT.');
            }

            return back()->with('error', $result['data']['message'] ?? 'Error al cancelar.');
        } catch (\Throwable $e) {
            Log::error('FacturAPI cancel failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error al conectar con FacturAPI. Revisa el log de actividad.');
        }
    }

    /**
     * Descargar PDF del CFDI.
     */
    public function downloadPdf(Order $order)
    {
        $pdf = (new FacturapiService())->downloadPdf($order);

        if (!$pdf) {
            return back()->with('error', 'No se pudo obtener el PDF.');
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"factura-{$order->folio()}.pdf\"",
        ]);
    }

    /**
     * Descargar XML del CFDI.
     */
    public function downloadXml(Order $order)
    {
        $xml = (new FacturapiService())->downloadXml($order);

        if (!$xml) {
            return back()->with('error', 'No se pudo obtener el XML.');
        }

        return response($xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"factura-{$order->folio()}.xml\"",
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
