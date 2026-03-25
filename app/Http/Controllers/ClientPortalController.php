<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientInvoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Services\FacturapiService;
use App\Services\CosmotownService;
use App\Services\MercadoPagoService;
use App\Services\TwentyIService;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientPortalController extends Controller
{
    public function show(string $token)
    {
        $client = Client::where('portal_token', $token)
            ->where('portal_active', true)
            ->with(['lead', 'invoices.quote', 'documents'])
            ->firstOrFail();

        $mailboxes = [];
        $emailDomain = null;
        $hasEmailService = $this->clientHasEmailService($client);

        if ($hasEmailService && $client->twentyi_package_id && Setting::get('twentyi_api_key')) {
            try {
                $service     = new TwentyIService();
                $emailDomain = $service->getDomain($client);
                $mailboxes   = $service->listMailboxes($client);
            } catch (\Throwable) {}
        }

        return view('portal.dashboard', compact('client', 'mailboxes', 'emailDomain', 'hasEmailService'));
    }

    public function webmail(string $token, string $mailbox)
    {
        $client = $this->resolveClient($token);

        if (!$this->clientHasEmailService($client)) {
            abort(403, 'No tienes un servicio de correo profesional contratado.');
        }

        if (!$client->twentyi_package_id || !Setting::get('twentyi_api_key')) {
            abort(404);
        }

        $url = (new TwentyIService())->getWebmailUrl($client, $mailbox);

        if (!$url) {
            return back()->with('error', 'No se pudo obtener el enlace de webmail.');
        }

        return redirect()->away($url);
    }

    public function downloadInvoicePdf(string $token, ClientInvoice $invoice)
    {
        $client = Client::where('portal_token', $token)
            ->where('portal_active', true)
            ->firstOrFail();

        abort_if($invoice->client_id !== $client->id, 403);

        if (!$invoice->isStamped()) {
            abort(404, 'Factura no disponible.');
        }

        $pdf = (new FacturapiService())->downloadPdf($invoice);

        if (!$pdf) {
            abort(500, 'No se pudo obtener el PDF.');
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="factura-' . $invoice->folio() . '.pdf"',
        ]);
    }

    public function downloadInvoiceXml(string $token, ClientInvoice $invoice)
    {
        $client = Client::where('portal_token', $token)
            ->where('portal_active', true)
            ->firstOrFail();

        abort_if($invoice->client_id !== $client->id, 403);

        if (!$invoice->isStamped()) {
            abort(404, 'Factura no disponible.');
        }

        $xml = (new FacturapiService())->downloadXml($invoice);

        if (!$xml) {
            abort(500, 'No se pudo obtener el XML.');
        }

        return response($xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="factura-' . $invoice->folio() . '.xml"',
        ]);
    }

    public function downloadDocument(string $token, ClientDocument $document)
    {
        $client = Client::where('portal_token', $token)
            ->where('portal_active', true)
            ->firstOrFail();

        abort_if($document->client_id !== $client->id, 403);

        return response()->download(
            storage_path('app/' . $document->file_path),
            $document->name
        );
    }

    // ── Gestión de correos ───────────────────────────────

    private function resolveClient(string $token): Client
    {
        return Client::where('portal_token', $token)
            ->where('portal_active', true)
            ->firstOrFail();
    }

    private function clientHasEmailService(Client $client): bool
    {
        $emailServiceIds = Service::where('name', 'like', 'Correo Profesional%')
            ->pluck('id');

        // Camino 1: Factura pagada con cotización que tiene ítems de correo
        $viaQuote = $emailServiceIds->isNotEmpty()
            && ClientInvoice::where('client_id', $client->id)
                ->whereNotNull('paid_at')
                ->whereHas('quote.items', fn ($q) => $q->whereIn('service_id', $emailServiceIds))
                ->exists();

        if ($viaQuote) {
            return true;
        }

        // Camino 2: Compra directa — notes contiene "Compra directa: Correo Profesional…"
        $emailServiceNames = Service::where('name', 'like', 'Correo Profesional%')
            ->pluck('name');

        foreach ($emailServiceNames as $name) {
            if (ClientInvoice::where('client_id', $client->id)
                ->whereNotNull('paid_at')
                ->where('notes', 'Compra directa: ' . $name)
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    public function mailboxes(string $token)
    {
        $client = $this->resolveClient($token);

        if (!$this->clientHasEmailService($client)) {
            abort(403, 'No tienes un servicio de correo profesional contratado.');
        }

        $mailboxes = [];
        $domain = null;
        $error = null;

        if ($client->twentyi_package_id && Setting::get('twentyi_api_key')) {
            try {
                $service   = new TwentyIService();
                $domain    = $service->getDomain($client);
                $mailboxes = $service->listMailboxes($client);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('portal.mailboxes', compact('client', 'mailboxes', 'domain', 'error'));
    }

    public function storeMailbox(Request $request, string $token)
    {
        $client = $this->resolveClient($token);

        if (!$this->clientHasEmailService($client)) {
            abort(403, 'No tienes un servicio de correo profesional contratado.');
        }

        $validated = $request->validate([
            'local'    => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._+-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        if (!$client->twentyi_package_id || !Setting::get('twentyi_api_key')) {
            return back()->with('error', 'Servicio de correo no configurado.');
        }

        try {
            (new TwentyIService())->createMailbox($client, $validated['local'], $validated['password']);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo crear el buzón: ' . $e->getMessage());
        }

        return redirect()->route('portal.mailboxes', $client->portal_token)
            ->with('success', "Buzón {$validated['local']} creado correctamente.");
    }

    public function destroyMailbox(string $token, string $mailbox)
    {
        $client = $this->resolveClient($token);

        if (!$this->clientHasEmailService($client)) {
            abort(403, 'No tienes un servicio de correo profesional contratado.');
        }

        if (!$client->twentyi_package_id || !Setting::get('twentyi_api_key')) {
            return back()->with('error', 'Servicio de correo no configurado.');
        }

        try {
            (new TwentyIService())->deleteMailbox($client, $mailbox);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo eliminar el buzón: ' . $e->getMessage());
        }

        return redirect()->route('portal.mailboxes', $client->portal_token)
            ->with('success', 'Buzón eliminado.');
    }

    public function changeMailboxPassword(Request $request, string $token, string $mailbox)
    {
        $client = $this->resolveClient($token);

        if (!$this->clientHasEmailService($client)) {
            abort(403, 'No tienes un servicio de correo profesional contratado.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        if (!$client->twentyi_package_id || !Setting::get('twentyi_api_key')) {
            return back()->with('error', 'Servicio de correo no configurado.');
        }

        try {
            (new TwentyIService())->updateMailboxPassword($client, $mailbox, $validated['password']);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo cambiar la contraseña: ' . $e->getMessage());
        }

        return back()->with('success', 'Contraseña actualizada.');
    }

    // ── Pagos Mercado Pago ────────────────────────────────

    public function checkout(string $token, ClientInvoice $invoice)
    {
        $client = $this->resolveClient($token);
        abort_if($invoice->client_id !== $client->id, 403);
        abort_if($invoice->status === 'cancelled', 404, 'Factura cancelada.');

        if ($invoice->paid_at) {
            return redirect()->route('portal.dashboard', $token)
                ->with('success', 'Esta factura ya fue pagada.');
        }

        $mpPublicKey = Setting::get('mp_public_key', '');

        $bankData = [
            'name'        => Setting::get('bank_name', ''),
            'beneficiary' => Setting::get('bank_beneficiary', ''),
            'account'     => Setting::get('bank_account', ''),
            'clabe'       => Setting::get('bank_clabe', ''),
            'reference'   => Setting::get('bank_reference', ''),
        ];
        $hasBankData = !empty($bankData['clabe']) || !empty($bankData['account']);

        return view('portal.checkout', compact('client', 'invoice', 'mpPublicKey', 'bankData', 'hasBankData'));
    }

    public function payWithCard(Request $request, string $token, ClientInvoice $invoice)
    {
        $client = $this->resolveClient($token);
        abort_if($invoice->client_id !== $client->id, 403);

        if ($invoice->paid_at) {
            return response()->json(['success' => false, 'error' => 'Esta factura ya fue pagada.'], 422);
        }

        $validated = $request->validate([
            'token'              => 'required|string',
            'payment_method_id'  => 'required|string',
            'email'              => 'required|email',
            'installments'       => 'nullable|integer|min:1|max:24',
            'issuer_id'          => 'nullable|integer',
            'billing_preference' => 'nullable|in:fiscal,publico_general,none',
        ]);

        if (!empty($validated['billing_preference'])) {
            $invoice->update(['billing_preference' => $validated['billing_preference']]);
        }

        try {
            $payment = (new MercadoPagoService())->createCardPayment(
                $invoice,
                $validated['token'],
                $validated['payment_method_id'],
                $validated['email'],
                (int) ($validated['installments'] ?? 1),
                $validated['issuer_id'] ?? null,
            );

            return response()->json([
                'success'       => $payment->status === 'approved',
                'status'        => $payment->status,
                'status_detail' => $payment->status_detail,
                'redirect'      => route('portal.payment.status', [$token, $payment]),
                'error'         => null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function payWithOxxo(Request $request, string $token, ClientInvoice $invoice)
    {
        $client = $this->resolveClient($token);
        abort_if($invoice->client_id !== $client->id, 403);

        if ($invoice->paid_at) {
            return back()->with('error', 'Esta factura ya fue pagada.');
        }

        $validated = $request->validate([
            'email'              => 'required|email',
            'billing_preference' => 'nullable|in:fiscal,publico_general,none',
        ]);
        if (!empty($validated['billing_preference'])) {
            $invoice->update(['billing_preference' => $validated['billing_preference']]);
        }

        try {
            $payment = (new MercadoPagoService())->createOxxoPayment($invoice, $validated['email']);
            return redirect()->route('portal.payment.status', [$token, $payment]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al generar referencia OXXO: ' . $e->getMessage());
        }
    }

    public function payWithSpei(Request $request, string $token, ClientInvoice $invoice)
    {
        $client = $this->resolveClient($token);
        abort_if($invoice->client_id !== $client->id, 403);

        if ($invoice->paid_at) {
            return back()->with('error', 'Esta factura ya fue pagada.');
        }

        $validated = $request->validate([
            'email'              => 'required|email',
            'billing_preference' => 'nullable|in:fiscal,publico_general,none',
        ]);
        if (!empty($validated['billing_preference'])) {
            $invoice->update(['billing_preference' => $validated['billing_preference']]);
        }

        try {
            $payment = (new MercadoPagoService())->createSpeiPayment($invoice, $validated['email']);
            return redirect()->route('portal.payment.status', [$token, $payment]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al generar referencia SPEI: ' . $e->getMessage());
        }
    }

    public function payWithTransfer(Request $request, string $token, ClientInvoice $invoice)
    {
        $client = $this->resolveClient($token);
        abort_if($invoice->client_id !== $client->id, 403);

        if ($invoice->paid_at) {
            return back()->with('error', 'Esta factura ya fue pagada.');
        }

        $validated = $request->validate([
            'email'              => 'required|email',
            'proof'              => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'billing_preference' => 'required|in:fiscal,publico_general,none',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('payment-proofs', 'public');
        }

        $payment = $invoice->payments()->create([
            'amount'            => $invoice->total,
            'currency'          => 'MXN',
            'status'            => 'pending',
            'payment_type'      => 'transfer',
            'payment_method_id' => 'bank_transfer',
            'proof_path'        => $proofPath,
            'payment_notes'     => 'Comprobante enviado por cliente vía portal',
            'paid_at'           => null,
        ]);

        $invoice->update(['billing_preference' => $validated['billing_preference']]);

        return redirect()->route('portal.payment.status', [$token, $payment]);
    }

    public function paymentStatus(string $token, Payment $payment)
    {
        $client = $this->resolveClient($token);
        abort_if($payment->invoice->client_id !== $client->id, 403);

        // Refrescar estado desde MP si sigue pendiente
        if ($payment->isPending() && $payment->mp_payment_id) {
            try {
                (new MercadoPagoService())->syncPaymentStatus($payment);
                $payment->refresh();
            } catch (\Throwable) {}
        }

        $invoice = $payment->invoice;

        return view('portal.payment-status', compact('client', 'payment', 'invoice'));
    }

    // ── Cotizaciones: aceptar / rechazar ─────────────────

    public function showQuote(string $token, Quote $quote)
    {
        $client = $this->resolveClient($token);
        abort_if(!$client->lead || $quote->lead_id !== $client->lead->id, 403);

        $quote->load('items.service');
        return view('portal.quote-show', compact('client', 'quote'));
    }

    public function acceptQuote(string $token, Quote $quote)
    {
        $client = $this->resolveClient($token);
        abort_if(!$client->lead || $quote->lead_id !== $client->lead->id, 403);

        if (!in_array($quote->status, ['enviada'])) {
            return back()->with('error', 'Esta cotización no puede ser aceptada.');
        }

        $quote->update(['status' => 'aceptada']);
        ActivityLog::log('quote_accepted', $quote, "Cotización {$quote->quote_number} aceptada por el cliente desde el portal");

        // Generar factura automáticamente a partir de la cotización
        $existingInvoice = ClientInvoice::where('client_id', $client->id)
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

        $invoice = ClientInvoice::create([
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

        ActivityLog::log('invoice_created', $invoice, "Factura generada automáticamente al aceptar cotización {$quote->quote_number}");

        // Si Mercado Pago está configurado, llevar directo al checkout
        if (Setting::get('mp_public_key')) {
            return redirect()->route('portal.checkout', [$token, $invoice])
                ->with('success', '¡Cotización aceptada! Se generó tu factura, procede al pago.');
        }

        return redirect()->route('portal.dashboard', $token)
            ->with('success', '¡Cotización aceptada! Se generó tu factura.');
    }

    public function rejectQuote(Request $request, string $token, Quote $quote)
    {
        $client = $this->resolveClient($token);
        abort_if(!$client->lead || $quote->lead_id !== $client->lead->id, 403);

        if (!in_array($quote->status, ['enviada'])) {
            return back()->with('error', 'Esta cotización no puede ser rechazada.');
        }

        $quote->update(['status' => 'rechazada']);
        ActivityLog::log('quote_rejected', $quote, "Cotización {$quote->quote_number} rechazada por el cliente desde el portal");

        return redirect()->route('portal.quote.show', [$token, $quote])
            ->with('success', 'Cotización rechazada. Lamentamos que no haya sido de su interés.');
    }

    // ── Datos fiscales ───────────────────────────────────

    public function editFiscalData(string $token)
    {
        $client = $this->resolveClient($token);
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
        $client = $this->resolveClient($token);

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
                (new FacturapiService())->syncCustomer($client);
            } catch (\Throwable) {}
        }

        ActivityLog::log('fiscal_updated', $client, "Datos fiscales actualizados desde el portal por {$client->legal_name}");

        return redirect()->route('portal.dashboard', $token)
            ->with('success', 'Datos fiscales actualizados correctamente.');
    }

    // ── Tickets de soporte ──────────────────────────────

    public function tickets(string $token)
    {
        $client = $this->resolveClient($token);
        $tickets = SupportTicket::where('client_id', $client->id)
            ->latest()
            ->paginate(10);

        return view('portal.tickets.index', compact('client', 'tickets'));
    }

    public function createTicket(string $token)
    {
        $client = $this->resolveClient($token);
        return view('portal.tickets.create', compact('client'));
    }

    public function storeTicket(Request $request, string $token)
    {
        $client = $this->resolveClient($token);

        $validated = $request->validate([
            'subject'     => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'priority'    => 'required|in:low,medium,high,urgent',
        ]);

        $ticket = SupportTicket::create([
            'client_id'   => $client->id,
            'subject'     => $validated['subject'],
            'description' => $validated['description'],
            'priority'    => $validated['priority'],
            'status'      => 'open',
        ]);

        // Notificar a admins
        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::notify($admin->id, 'new_ticket',
                "Nuevo ticket: #{$ticket->id}",
                "{$client->legal_name} creó el ticket '{$ticket->subject}'",
                route('admin.tickets.show', $ticket));
        }

        return redirect()->route('portal.tickets.show', [$token, $ticket])
            ->with('success', 'Ticket creado exitosamente.');
    }

    public function showTicket(string $token, SupportTicket $ticket)
    {
        $client = $this->resolveClient($token);
        abort_if($ticket->client_id !== $client->id, 403);

        $ticket->load(['replies' => fn ($q) => $q->where('is_internal', false)->with('user')]);

        return view('portal.tickets.show', compact('client', 'ticket'));
    }

    public function replyToTicket(Request $request, string $token, SupportTicket $ticket)
    {
        $client = $this->resolveClient($token);
        abort_if($ticket->client_id !== $client->id, 403);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        TicketReply::create([
            'support_ticket_id' => $ticket->id,
            'client_id'         => $client->id,
            'body'              => $validated['body'],
            'is_internal'       => false,
        ]);

        if (in_array($ticket->status, ['resolved', 'closed'])) {
            $ticket->update(['status' => 'open']);
        }

        return back()->with('success', 'Respuesta enviada.');
    }

    // ─── Dominio (Cosmotown) ────────────────────────────────────

    public function domain(string $token)
    {
        $client = Client::where('portal_token', $token)->firstOrFail();

        if (!$client->domain || $client->domain_type !== 'cosmotown') {
            return redirect()->route('portal.dashboard', $token);
        }

        $cosmotown = new CosmotownService();
        $domainInfo = null;
        $error = null;

        if ($cosmotown->isConfigured()) {
            try {
                $domainInfo = $cosmotown->domainInfo($client->domain);
            } catch (\Throwable $e) {
                $error = 'No se pudo obtener la información del dominio.';
            }
        }

        return view('portal.domain', compact('client', 'domainInfo', 'error'));
    }

    public function domainDns(string $token)
    {
        $client = Client::where('portal_token', $token)->firstOrFail();

        if (!$client->domain || $client->domain_type !== 'cosmotown') {
            return response()->json(['error' => 'Sin dominio Cosmotown asignado.'], 422);
        }

        $cosmotown = new CosmotownService();

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
        $client = Client::where('portal_token', $token)->firstOrFail();

        if (!$client->domain || $client->domain_type !== 'cosmotown') {
            return response()->json(['error' => 'Sin dominio Cosmotown asignado.'], 422);
        }

        $validated = $request->validate(['records' => 'required|array']);

        $cosmotown = new CosmotownService();

        if (!$cosmotown->isConfigured()) {
            return response()->json(['error' => 'Cosmotown no configurado.'], 422);
        }

        try {
            $cosmotown->saveDnsSettings($client->domain, $validated['records']);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function saveDomainNameservers(Request $request, string $token)
    {
        $client = Client::where('portal_token', $token)->firstOrFail();

        if (!$client->domain || $client->domain_type !== 'cosmotown') {
            return response()->json(['error' => 'Sin dominio Cosmotown asignado.'], 422);
        }

        $validated = $request->validate([
            'nameservers'   => 'required|array|min:1|max:4',
            'nameservers.*' => 'required|string|max:253',
        ]);

        $cosmotown = new CosmotownService();

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

    // ── DNS 20i (portal) ──────────────────────────────────────────

    public function dns(string $token)
    {
        $client = $this->resolveClient($token);

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
        $client = $this->resolveClient($token);

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

    public function destroyDns(Request $request, string $token, string $recordId)
    {
        $client = $this->resolveClient($token);

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
