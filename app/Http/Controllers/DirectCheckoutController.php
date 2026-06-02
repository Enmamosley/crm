<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Setting;
use App\Services\CosmotownService;
use App\Services\MercadoPagoService;
use App\Services\PayPalService;
use App\Services\ProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DirectCheckoutController extends Controller
{
    /**
     * Catálogo público de servicios marcados como "public".
     */
    public function catalog()
    {
        $services = Service::public()->with('category')->get()->groupBy(fn ($s) => $s->category?->name ?? 'General');
        $companyName = Setting::get('company_name', 'CRM');
        $cartCount = \App\Models\CartItem::where('session_id', session()->getId())->sum('quantity');

        return view('buy.catalog', compact('services', 'companyName', 'cartCount'));
    }

    /**
     * Página de checkout para un servicio específico.
     */
    public function show(string $slug)
    {
        $service = Service::where('slug', $slug)->where('public', true)->where('active', true)->firstOrFail();
        $mpPublicKey = Setting::get('mp_public_key', '');
        $companyName = Setting::get('company_name', 'CRM');

        $paypal = new PayPalService();
        $paypalClientId = $paypal->isConfigured() ? $paypal->clientId() : '';
        $paypalMode     = $paypal->mode();

        $bankData = [
            'bank_name'        => Setting::get('bank_name'),
            'bank_beneficiary' => Setting::get('bank_beneficiary'),
            'bank_account'     => Setting::get('bank_account'),
            'bank_clabe'       => Setting::get('bank_clabe'),
            'bank_reference'   => Setting::get('bank_reference'),
        ];
        $hasBankData = !empty($bankData['bank_clabe']) || !empty($bankData['bank_account']);

        return view('buy.checkout', compact('service', 'mpPublicKey', 'paypalClientId', 'paypalMode', 'companyName', 'bankData', 'hasBankData'));
    }

    /**
     * AJAX público: verifica disponibilidad de dominio vía Cosmotown.
     * GET /buy/domain/check?domain=miempresa.com
     */
    public function checkDomain(Request $request)
    {
        $domain = trim($request->query('domain', ''));

        if (!$domain || !preg_match('/^[a-z0-9][a-z0-9\-]{1,61}[a-z0-9]\.[a-z]{2,}$/i', $domain)) {
            return response()->json(['error' => 'Dominio inválido.'], 422);
        }

        $service = new CosmotownService();

        if (!$service->isConfigured()) {
            // Sin Cosmotown configurado, solo confirmamos que el dominio tiene formato válido
            return response()->json([
                'available' => null,
                'domain'    => $domain,
                'message'   => 'Verificación no disponible. Puedes continuar con tu propio dominio.',
            ]);
        }

        try {
            $result = $service->checkAvailability($domain);
            unset($result['price']);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo verificar el dominio.'], 500);
        }
    }

    /**
     * Procesa el pago con tarjeta y crea cliente + factura automáticamente.
     */
    public function payWithCard(Request $request, string $slug)
    {
        $service = Service::where('slug', $slug)->where('public', true)->where('active', true)->firstOrFail();

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'email'              => 'required|email|max:255',
            'phone'              => 'nullable|string|max:20',
            'token'              => 'required|string',
            'payment_method_id'  => 'required|string',
            'installments'       => 'nullable|integer|min:1|max:24',
            'issuer_id'          => 'nullable|string',
            'domain'             => 'nullable|string|max:253',
            'domain_type'        => 'nullable|in:cosmotown,own',
            'billing_preference' => 'nullable|in:fiscal,publico_general,none',
            'tax_id'             => 'nullable|string|max:13',
            'fiscal_name'        => 'nullable|string|max:255',
            'address_zip'        => 'nullable|string|max:5',
            'tax_system'         => 'nullable|string|max:3',
            'cfdi_use'           => 'nullable|string|max:4',
        ]);

        $client = $this->resolveOrCreateClient($validated);

        // Crear factura borrador
        $subtotal = (float) $service->price;
        $ivaRate = (float) Setting::get('iva_percentage', 16) / 100;
        $iva = round($subtotal * $ivaRate, 2);
        $total = round($subtotal + $iva, 2);

        try {
            return DB::transaction(function () use ($client, $service, $slug, $validated, $subtotal, $iva, $total) {
                $invoice = $this->findOrCreateInvoice($client, $service, [
                    'client_id'          => $client->id,
                    'series'             => 'V',
                    'payment_form'       => '04',
                    'payment_method'     => 'PUE',
                    'use_cfdi'           => $client->cfdi_use ?? 'S01',
                    'billing_preference' => $validated['billing_preference'] ?? 'none',
                    'status'             => 'draft',
                    'subtotal'           => $subtotal,
                    'iva_amount'         => $iva,
                    'total'              => $total,
                    'notes'              => 'Compra directa: ' . $service->name,
                ]);

                $payment = (new MercadoPagoService())->createCardPayment(
                    $invoice,
                    $validated['token'],
                    $validated['payment_method_id'],
                    $validated['email'],
                    (int) ($validated['installments'] ?? 1),
                    $validated['issuer_id'] ?? null,
                );

                ActivityLog::log('direct_purchase', $invoice, "Compra directa de '{$service->name}' por {$validated['email']}");

                if ($payment->status === 'approved') {
                    (new ProvisioningService())->provisionForOrder($invoice);
                    (new \App\Services\MetaConversionsService())->sendPurchase($invoice, request());
                    $this->flashMetaPurchase($invoice, $service);
                }

                return response()->json([
                    'success'       => $payment->status === 'approved',
                    'status'        => $payment->status,
                    'status_detail' => $payment->status_detail,
                    'redirect'      => route('buy.success', [
                        'slug' => $slug,
                        'payment' => $payment->id,
                        'token' => $client->portal_token,
                    ]),
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'No se pudo procesar el pago. Intenta de nuevo.'], 422);
        }
    }

    /**
     * Procesa pago OXXO.
     */
    public function payWithOxxo(Request $request, string $slug)
    {
        $service = Service::where('slug', $slug)->where('public', true)->where('active', true)->firstOrFail();

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'email'              => 'required|email|max:255',
            'phone'              => 'nullable|string|max:20',
            'domain'             => 'nullable|string|max:253',
            'domain_type'        => 'nullable|in:cosmotown,own',
            'billing_preference' => 'nullable|in:fiscal,publico_general,none',
            'tax_id'             => 'nullable|string|max:13',
            'fiscal_name'        => 'nullable|string|max:255',
            'address_zip'        => 'nullable|string|max:5',
            'tax_system'         => 'nullable|string|max:3',
            'cfdi_use'           => 'nullable|string|max:4',
        ]);

        $client = $this->resolveOrCreateClient($validated);

        $subtotal = (float) $service->price;
        $ivaRate = (float) Setting::get('iva_percentage', 16) / 100;
        $iva = round($subtotal * $ivaRate, 2);
        $total = round($subtotal + $iva, 2);

        try {
            return DB::transaction(function () use ($client, $service, $slug, $validated, $subtotal, $iva, $total) {
                $invoice = $this->findOrCreateInvoice($client, $service, [
                    'client_id'          => $client->id,
                    'series'             => 'V',
                    'payment_form'       => '01',
                    'payment_method'     => 'PUE',
                    'use_cfdi'           => $client->cfdi_use ?? 'S01',
                    'billing_preference' => $validated['billing_preference'] ?? 'none',
                    'status'             => 'draft',
                    'subtotal'           => $subtotal,
                    'iva_amount'         => $iva,
                    'total'              => $total,
                    'notes'              => 'Compra directa: ' . $service->name,
                ]);

                $payment = (new MercadoPagoService())->createOxxoPayment($invoice, $validated['email']);
                ActivityLog::log('direct_purchase', $invoice, "Compra directa OXXO de '{$service->name}' por {$validated['email']}");
                // El aprovisionamiento ocurre tras CONFIRMAR el pago (webhook MP), no antes.

                return redirect()->route('buy.success', [
                    'slug' => $slug,
                    'payment' => $payment->id,
                    'token' => $client->portal_token,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('DirectCheckout OXXO payment failed', ['slug' => $slug, 'error' => $e->getMessage()]);
            return back()->with('error', 'No se pudo procesar el pago. Intenta de nuevo.')->withInput();
        }
    }

    /**
     * Procesa pago SPEI.
     */
    public function payWithSpei(Request $request, string $slug)
    {
        $service = Service::where('slug', $slug)->where('public', true)->where('active', true)->firstOrFail();

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'email'              => 'required|email|max:255',
            'phone'              => 'nullable|string|max:20',
            'domain'             => 'nullable|string|max:253',
            'domain_type'        => 'nullable|in:cosmotown,own',
            'billing_preference' => 'nullable|in:fiscal,publico_general,none',
            'tax_id'             => 'nullable|string|max:13',
            'fiscal_name'        => 'nullable|string|max:255',
            'address_zip'        => 'nullable|string|max:5',
            'tax_system'         => 'nullable|string|max:3',
            'cfdi_use'           => 'nullable|string|max:4',
        ]);

        $client = $this->resolveOrCreateClient($validated);

        $subtotal = (float) $service->price;
        $ivaRate = (float) Setting::get('iva_percentage', 16) / 100;
        $iva = round($subtotal * $ivaRate, 2);
        $total = round($subtotal + $iva, 2);

        try {
            return DB::transaction(function () use ($client, $service, $slug, $validated, $subtotal, $iva, $total) {
                $invoice = $this->findOrCreateInvoice($client, $service, [
                    'client_id'          => $client->id,
                    'series'             => 'V',
                    'payment_form'       => '03',
                    'payment_method'     => 'PUE',
                    'use_cfdi'           => $client->cfdi_use ?? 'S01',
                    'billing_preference' => $validated['billing_preference'] ?? 'none',
                    'status'             => 'draft',
                    'subtotal'           => $subtotal,
                    'iva_amount'         => $iva,
                    'total'              => $total,
                    'notes'              => 'Compra directa: ' . $service->name,
                ]);

                $payment = (new MercadoPagoService())->createSpeiPayment($invoice, $validated['email']);
                ActivityLog::log('direct_purchase', $invoice, "Compra directa SPEI de '{$service->name}' por {$validated['email']}");
                // El aprovisionamiento ocurre tras CONFIRMAR el pago (webhook MP), no antes.

                return redirect()->route('buy.success', [
                    'slug' => $slug,
                    'payment' => $payment->id,
                    'token' => $client->portal_token,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('DirectCheckout SPEI payment failed', ['slug' => $slug, 'error' => $e->getMessage()]);
            return back()->with('error', 'No se pudo procesar el pago. Intenta de nuevo.')->withInput();
        }
    }

    /**
     * Procesa pago por transferencia bancaria manual.
     */
    public function payWithTransfer(Request $request, string $slug)
    {
        $service = Service::where('slug', $slug)->where('public', true)->where('active', true)->firstOrFail();

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'email'              => 'required|email|max:255',
            'phone'              => 'nullable|string|max:20',
            'domain'             => 'nullable|string|max:253',
            'domain_type'        => 'nullable|in:cosmotown,own',
            'proof'              => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'billing_preference' => 'nullable|in:fiscal,publico_general,none',
            'tax_id'             => 'nullable|string|max:13',
            'fiscal_name'        => 'nullable|string|max:255',
            'address_zip'        => 'nullable|string|max:5',
            'tax_system'         => 'nullable|string|max:3',
            'cfdi_use'           => 'nullable|string|max:4',
        ]);

        $client   = $this->resolveOrCreateClient($validated);
        $subtotal = (float) $service->price;
        $ivaRate  = (float) Setting::get('iva_percentage', 16) / 100;
        $iva      = round($subtotal * $ivaRate, 2);
        $total    = round($subtotal + $iva, 2);

        try {
            return DB::transaction(function () use ($client, $service, $slug, $validated, $subtotal, $iva, $total, $request) {
                $invoice = $this->findOrCreateInvoice($client, $service, [
                    'client_id'          => $client->id,
                    'series'             => 'V',
                    'payment_form'       => '03',
                    'payment_method'     => 'PUE',
                    'use_cfdi'           => $client->cfdi_use ?? 'S01',
                    'billing_preference' => $validated['billing_preference'] ?? 'none',
                    'status'             => 'draft',
                    'subtotal'           => $subtotal,
                    'iva_amount'         => $iva,
                    'total'              => $total,
                    'notes'              => 'Compra directa: ' . $service->name,
                ]);

                $proofPath = null;
                if ($request->hasFile('proof')) {
                    $proofPath = $request->file('proof')->store('payment-proofs', 'local');
                }

                $payment = $invoice->payments()->create([
                    'amount'            => $total,
                    'currency'          => 'MXN',
                    'status'            => 'pending',
                    'payment_type'      => 'transfer',
                    'payment_method_id' => 'transfer',
                    'proof_path'        => $proofPath,
                    'payment_notes'     => 'Transferencia bancaria pendiente de confirmación.',
                ]);

                ActivityLog::log('direct_purchase', $invoice, "Compra directa transferencia de '{$service->name}' por {$validated['email']}");
                // El aprovisionamiento ocurre al CONFIRMAR la transferencia (panel), no antes.

                return redirect()->route('buy.success', [
                    'slug'    => $slug,
                    'payment' => $payment->id,
                    'token'   => $client->portal_token,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('DirectCheckout transfer payment failed', ['slug' => $slug, 'error' => $e->getMessage()]);
            return back()->with('error', 'No se pudo registrar el pago. Intenta de nuevo.')->withInput();
        }
    }

    /**
     * Crea una orden de PayPal antes de mostrar el botón aprobar.
     * Devuelve { paypalOrderId, localOrderId } al frontend.
     */
    public function createPaypalOrder(Request $request, string $slug)
    {
        $service = Service::where('slug', $slug)->where('public', true)->where('active', true)->firstOrFail();

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'email'              => 'required|email|max:255',
            'phone'              => 'nullable|string|max:20',
            'domain'             => 'nullable|string|max:253',
            'domain_type'        => 'nullable|in:cosmotown,own',
            'billing_preference' => 'nullable|in:fiscal,publico_general,none',
            'tax_id'             => 'nullable|string|max:13',
            'fiscal_name'        => 'nullable|string|max:255',
            'address_zip'        => 'nullable|string|max:5',
            'tax_system'         => 'nullable|string|max:3',
            'cfdi_use'           => 'nullable|string|max:4',
        ]);

        $paypal = new PayPalService();
        if (!$paypal->isConfigured()) {
            return response()->json(['error' => 'PayPal no está configurado.'], 422);
        }

        $client = $this->resolveOrCreateClient($validated);

        $subtotal = (float) $service->price;
        $ivaRate  = (float) Setting::get('iva_percentage', 16) / 100;
        $iva      = round($subtotal * $ivaRate, 2);
        $total    = round($subtotal + $iva, 2);

        try {
            return DB::transaction(function () use ($client, $service, $slug, $subtotal, $iva, $total, $paypal, $validated) {
                $order = $this->findOrCreateInvoice($client, $service, [
                    'client_id'          => $client->id,
                    'series'             => 'V',
                    'payment_form'       => '05', // SAT 05 — monedero electrónico (PayPal)
                    'payment_method'     => 'PUE',
                    'use_cfdi'           => $client->cfdi_use ?? 'S01',
                    'billing_preference' => $validated['billing_preference'] ?? 'none',
                    'status'             => 'draft',
                    'subtotal'           => $subtotal,
                    'iva_amount'         => $iva,
                    'total'              => $total,
                    'notes'              => 'Compra directa: ' . $service->name,
                ]);

                $pp = $paypal->createOrder(
                    $total,
                    $service->name,
                    (string) $order->id,
                    route('buy.show', $slug),
                    route('buy.show', $slug),
                );

                return response()->json([
                    'paypalOrderId' => $pp['id'],
                    'localOrderId'  => $order->id,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('PayPal createOrder failed', ['slug' => $slug, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'No se pudo iniciar el pago con PayPal.'], 422);
        }
    }

    /**
     * Captura la orden tras aprobación del usuario en el popup de PayPal.
     */
    public function capturePaypalOrder(Request $request, string $slug)
    {
        $service = Service::where('slug', $slug)->where('public', true)->where('active', true)->firstOrFail();

        $validated = $request->validate([
            'paypalOrderId' => 'required|string',
            'localOrderId'  => 'required|integer|exists:orders,id',
        ]);

        $order = Order::findOrFail($validated['localOrderId']);
        $paypal = new PayPalService();

        try {
            $capture = $paypal->captureOrder($validated['paypalOrderId']);
            $payment = $paypal->processCapture($order, $capture);

            ActivityLog::log('direct_purchase', $order, "Compra directa PayPal de '{$service->name}' por {$order->client->email}");

            if ($payment->isApproved()) {
                (new ProvisioningService())->provisionForOrder($order);
                (new \App\Services\MetaConversionsService())->sendPurchase($order, $request);
                $this->flashMetaPurchase($order, $service);
            }

            return response()->json([
                'success'  => $payment->isApproved(),
                'status'   => $payment->status,
                'redirect' => route('buy.success', [
                    'slug'    => $slug,
                    'payment' => $payment->id,
                    'token'   => $order->client->portal_token,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('PayPal capture failed', ['slug' => $slug, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'No se pudo capturar el pago.'], 422);
        }
    }

    /**
     * Página de éxito/estado del pago con acceso al portal.
     */
    public function success(Request $request, string $slug)
    {
        $service = Service::where('slug', $slug)->firstOrFail();
        $payment = Payment::findOrFail($request->query('payment'));
        $token   = $request->query('token');
        $client  = Client::where('portal_token', $token)->firstOrFail();
        $companyName = Setting::get('company_name', 'CRM');

        abort_if(!$payment->order || $payment->order->client_id !== $client->id, 403);

        return view('buy.success', compact('service', 'payment', 'client', 'companyName'));
    }

    private function resolveOrCreateClient(array $data): Client
    {
        $client = Client::where('email', $data['email'])->first();

        $billingPref = $data['billing_preference'] ?? 'none';
        $fiscalData = [];
        if ($billingPref === 'fiscal' && !empty($data['tax_id'])) {
            $fiscalData = [
                'tax_id'      => strtoupper($data['tax_id']),
                'legal_name'  => $data['fiscal_name'] ?? $data['name'],
                'address_zip' => $data['address_zip'] ?? null,
                'tax_system'  => $data['tax_system'] ?? '616',
                'cfdi_use'    => $data['cfdi_use'] ?? 'G03',
            ];
        }

        if (!$client) {
            $client = Client::create(array_merge([
                'legal_name'    => $data['name'],
                'email'         => $data['email'],
                'phone'         => $data['phone'] ?? null,
                'lead_id'       => null,
                'tax_system'    => '616',
                'cfdi_use'      => 'S01',
                'portal_active' => true,
            ], $fiscalData));
        } elseif (!empty($fiscalData) && empty($client->tax_id)) {
            $client->update($fiscalData);
        }

        if (!empty($data['domain'])) {
            $client->update([
                'domain'      => $data['domain'],
                'domain_type' => $data['domain_type'] ?? 'own',
            ]);
        }
        return $client;
    }

    /**
     * Reutiliza factura draft sin pagos para evitar duplicados si el usuario reenvía el formulario.
     */
    private function findOrCreateInvoice(Client $client, Service $service, array $invoiceData): Order
    {
        $existing = Order::where('client_id', $client->id)
            ->where('status', 'draft')
            ->where('total', $invoiceData['total'])
            ->where('notes', 'Compra directa: ' . $service->name)
            ->whereDoesntHave('payments')
            ->first();

        if ($existing) {
            $existing->update($invoiceData);
            return $existing;
        }

        return Order::create($invoiceData);
    }

    /** Deja el evento Purchase listo para el Pixel del navegador en la página de éxito. */
    private function flashMetaPurchase(Order $order, Service $service): void
    {
        session()->flash('meta_purchase', [
            'value'        => round((float) $order->total, 2),
            'currency'     => 'MXN',
            'content_type' => 'product',
            'content_ids'  => [$service->id],
            'content_name' => $service->name,
            'num_items'    => 1,
            'event_id'     => \App\Services\MetaConversionsService::purchaseEventId($order),
        ]);
    }
}
