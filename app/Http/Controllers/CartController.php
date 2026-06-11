<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CartItem;
use App\Models\Client;
use App\Models\Order;
use App\Models\DiscountCode;
use App\Models\Service;
use App\Models\Setting;
use App\Services\MercadoPagoService;
use App\Services\PayPalService;
use App\Services\ProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public function index()
    {
        $sessionId = session()->getId();
        $items = CartItem::where('session_id', $sessionId)->with('service.category')->get();
        $companyName = Setting::get('company_name', 'CRM');
        $mpPublicKey = Setting::get('mp_public_key', '');

        $subtotal = $items->sum(fn ($item) => $item->service->price * $item->quantity);
        $ivaRate = (float) Setting::get('iva_percentage', 16) / 100;
        $discount = 0;
        $discountCode = session('discount_code');
        if ($discountCode) {
            $code = DiscountCode::where('code', $discountCode)->first();
            if ($code && $code->isValid($subtotal)) {
                $discount = $code->calculateDiscount($subtotal);
            } else {
                session()->forget('discount_code');
                $discountCode = null;
            }
        }

        $adjustedSubtotal = max(0, $subtotal - $discount);
        $iva = round($adjustedSubtotal * $ivaRate, 2);
        $total = round($adjustedSubtotal + $iva, 2);

        return view('buy.cart', compact(
            'items', 'subtotal', 'iva', 'discount', 'discountCode', 'total', 'companyName', 'mpPublicKey'
        ));
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'quantity'    => 'nullable|integer|min:1|max:10',
        ]);

        $service = Service::where('id', $validated['service_id'])->where('public', true)->where('active', true)->firstOrFail();
        $sessionId = session()->getId();

        $item = CartItem::where('session_id', $sessionId)
            ->where('service_id', $service->id)
            ->first();

        if ($item) {
            $item->increment('quantity', $validated['quantity'] ?? 1);
        } else {
            CartItem::create([
                'session_id' => $sessionId,
                'service_id' => $service->id,
                'quantity'   => $validated['quantity'] ?? 1,
            ]);
        }

        return redirect()->route('buy.cart')->with('success', "'{$service->name}' agregado al carrito.");
    }

    public function remove(CartItem $cartItem)
    {
        abort_if($cartItem->session_id !== session()->getId(), 403);
        $cartItem->delete();

        return back()->with('success', 'Producto eliminado del carrito.');
    }

    public function applyDiscount(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $code = DiscountCode::where('code', strtoupper($validated['code']))->first();

        $subtotal = CartItem::where('session_id', session()->getId())
            ->with('service')->get()
            ->sum(fn ($item) => $item->service->price * $item->quantity);

        if (!$code || !$code->isValid($subtotal)) {
            return back()->with('error', 'Código de descuento inválido, expirado o no aplica al monto de tu carrito.');
        }

        session(['discount_code' => $code->code]);

        return back()->with('success', "Cupón '{$code->code}' aplicado.");
    }

    public function removeDiscount()
    {
        session()->forget('discount_code');
        return back()->with('success', 'Cupón removido.');
    }

    public function count()
    {
        $count = CartItem::where('session_id', session()->getId())->sum('quantity');
        return response()->json(['count' => $count]);
    }

    public function checkout()
    {
        $cartData = $this->resolveCart();
        if (!$cartData) {
            return redirect()->route('buy.cart')->with('error', 'Tu carrito está vacío.');
        }

        $companyName    = Setting::get('company_name', 'CRM');
        $mpPublicKey    = Setting::get('mp_public_key', '');
        $paypal         = new PayPalService();
        $paypalClientId = $paypal->isConfigured() ? $paypal->clientId() : '';
        $paypalMode     = $paypal->mode();
        $requiresDomain = $cartData['items']->contains(fn ($i) => $i->service->requires_domain);

        return view('buy.cart-checkout', [
            'items'          => $cartData['items'],
            'subtotal'       => $cartData['subtotal'],
            'discount'       => $cartData['discount'],
            'discountCode'   => $cartData['discountCode'],
            'iva'            => $cartData['iva'],
            'total'          => $cartData['total'],
            'companyName'    => $companyName,
            'mpPublicKey'    => $mpPublicKey,
            'paypalClientId' => $paypalClientId,
            'paypalMode'     => $paypalMode,
            'requiresDomain' => $requiresDomain,
        ]);
    }

    public function updateQuantity(Request $request, CartItem $cartItem)
    {
        abort_if($cartItem->session_id !== session()->getId(), 403);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:10',
        ]);

        $cartItem->update(['quantity' => $validated['quantity']]);

        return back()->with('success', 'Cantidad actualizada.');
    }

    public function payWithCard(Request $request)
    {
        $cartData = $this->resolveCart();
        if (!$cartData) {
            return response()->json(['success' => false, 'error' => 'Carrito vacío.'], 422);
        }

        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'email'             => 'required|email|max:255',
            'phone'             => 'nullable|string|max:20',
            'token'             => 'required|string',
            'payment_method_id' => 'required|string',
            'installments'      => 'nullable|integer|min:1|max:24',
            'issuer_id'         => 'nullable|string',
            'domain'            => 'nullable|string|max:253',
            'domain_type'       => 'nullable|in:cosmotown,own,later',
            'reg_street'        => 'nullable|string|max:255',
            'reg_city'          => 'nullable|string|max:100',
            'reg_state'         => 'nullable|string|max:100',
            'reg_zip'           => 'nullable|string|max:10',
            'reg_country'       => 'nullable|string|max:3',
        ]);

        $client = $this->resolveOrCreateClient($validated);

        try {
            return DB::transaction(function () use ($client, $validated, $cartData) {
                $invoice = $this->createInvoiceFromCart($client, $cartData, '04');

                $payment = (new MercadoPagoService())->createCardPayment(
                    $invoice, $validated['token'], $validated['payment_method_id'],
                    $validated['email'], (int) ($validated['installments'] ?? 1),
                    $validated['issuer_id'] ?? null,
                );

                $this->clearCart($cartData);
                ActivityLog::log('cart_purchase', $invoice, "Compra por carrito (tarjeta) por {$validated['email']}");

                if ($payment->status === 'approved') {
                    DiscountCode::consumeForCode($invoice->discount_code);
                    (new ProvisioningService())->provisionForOrder($invoice);
                    (new \App\Services\MetaConversionsService())->sendPurchase($invoice, request());
                }

                return response()->json([
                    'success'       => $payment->status === 'approved',
                    'status'        => $payment->status,
                    'status_detail' => $payment->status_detail,
                    'redirect'      => route('portal.dashboard', $client->portal_token),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Cart card payment failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'No se pudo procesar el pago. Intenta de nuevo.'], 422);
        }
    }

    public function payWithOxxo(Request $request)
    {
        $cartData = $this->resolveCart();
        if (!$cartData) {
            return back()->with('error', 'Carrito vacío.');
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|max:255',
            'phone'       => 'nullable|string|max:20',
            'domain'      => 'nullable|string|max:253',
            'domain_type' => 'nullable|in:cosmotown,own,later',
            'reg_street'  => 'nullable|string|max:255',
            'reg_city'    => 'nullable|string|max:100',
            'reg_state'   => 'nullable|string|max:100',
            'reg_zip'     => 'nullable|string|max:10',
            'reg_country' => 'nullable|string|max:3',
        ]);

        $client = $this->resolveOrCreateClient($validated);

        try {
            return DB::transaction(function () use ($client, $validated, $cartData) {
                $invoice = $this->createInvoiceFromCart($client, $cartData, '01');
                $payment = (new MercadoPagoService())->createOxxoPayment($invoice, $validated['email']);
                $this->clearCart($cartData);
                ActivityLog::log('cart_purchase', $invoice, "Compra por carrito (OXXO) por {$validated['email']}");
                // El aprovisionamiento ocurre tras CONFIRMAR el pago (webhook MP), no antes.
                return redirect()->route('portal.dashboard', $client->portal_token);
            });
        } catch (\Throwable $e) {
            Log::error('Cart OXXO payment failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'No se pudo procesar el pago. Intenta de nuevo.')->withInput();
        }
    }

    public function payWithSpei(Request $request)
    {
        $cartData = $this->resolveCart();
        if (!$cartData) {
            return back()->with('error', 'Carrito vacío.');
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|max:255',
            'phone'       => 'nullable|string|max:20',
            'domain'      => 'nullable|string|max:253',
            'domain_type' => 'nullable|in:cosmotown,own,later',
            'reg_street'  => 'nullable|string|max:255',
            'reg_city'    => 'nullable|string|max:100',
            'reg_state'   => 'nullable|string|max:100',
            'reg_zip'     => 'nullable|string|max:10',
            'reg_country' => 'nullable|string|max:3',
        ]);

        $client = $this->resolveOrCreateClient($validated);

        try {
            return DB::transaction(function () use ($client, $validated, $cartData) {
                $invoice = $this->createInvoiceFromCart($client, $cartData, '03');
                $payment = (new MercadoPagoService())->createSpeiPayment($invoice, $validated['email']);
                $this->clearCart($cartData);
                ActivityLog::log('cart_purchase', $invoice, "Compra por carrito (SPEI) por {$validated['email']}");
                // El aprovisionamiento ocurre tras CONFIRMAR el pago (webhook MP), no antes.
                return redirect()->route('portal.dashboard', $client->portal_token);
            });
        } catch (\Throwable $e) {
            Log::error('Cart SPEI payment failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'No se pudo procesar el pago. Intenta de nuevo.')->withInput();
        }
    }

    public function createPaypalOrder(Request $request)
    {
        $cartData = $this->resolveCart();
        if (!$cartData) {
            return response()->json(['error' => 'Carrito vacío.'], 422);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|max:255',
            'phone'       => 'nullable|string|max:20',
            'domain'      => 'nullable|string|max:253',
            'domain_type' => 'nullable|in:cosmotown,own,later',
            'reg_street'  => 'nullable|string|max:255',
            'reg_city'    => 'nullable|string|max:100',
            'reg_state'   => 'nullable|string|max:100',
            'reg_zip'     => 'nullable|string|max:10',
            'reg_country' => 'nullable|string|max:3',
        ]);

        $paypal = new PayPalService();
        if (!$paypal->isConfigured()) {
            return response()->json(['error' => 'PayPal no está configurado.'], 422);
        }

        $client = $this->resolveOrCreateClient($validated);

        try {
            return DB::transaction(function () use ($client, $cartData, $paypal) {
                $order = $this->createInvoiceFromCart($client, $cartData, '05');
                $description = $cartData['items']->map(fn ($i) => $i->service->name)->implode(', ');

                $pp = $paypal->createOrder(
                    (float) $cartData['total'],
                    $description ?: 'Compra carrito',
                    (string) $order->id,
                    route('buy.cart'),
                    route('buy.cart'),
                );

                return response()->json([
                    'paypalOrderId' => $pp['id'],
                    'localOrderId'  => $order->id,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Cart PayPal createOrder failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'No se pudo iniciar el pago con PayPal.'], 422);
        }
    }

    public function capturePaypalOrder(Request $request)
    {
        $validated = $request->validate([
            'paypalOrderId' => 'required|string',
            'localOrderId'  => 'required|integer|exists:orders,id',
        ]);

        $order  = Order::findOrFail($validated['localOrderId']);
        $paypal = new PayPalService();

        try {
            $capture = $paypal->captureOrder($validated['paypalOrderId']);
            $payment = $paypal->processCapture($order, $capture);

            ActivityLog::log('cart_purchase', $order, "Compra carrito (PayPal) por {$order->client->email}");

            if ($payment->isApproved()) {
                // Limpia carrito de la sesión actual
                CartItem::where('session_id', session()->getId())->delete();
                session()->forget(['discount_code', 'discount_amount']);
                DiscountCode::consumeForCode($order->discount_code);

                (new ProvisioningService())->provisionForOrder($order);
                (new \App\Services\MetaConversionsService())->sendPurchase($order, $request);
            }

            return response()->json([
                'success'  => $payment->isApproved(),
                'status'   => $payment->status,
                'redirect' => route('portal.dashboard', $order->client->portal_token),
            ]);
        } catch (\Throwable $e) {
            Log::error('Cart PayPal capture failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'No se pudo capturar el pago.'], 422);
        }
    }

    private function resolveCart(): ?array
    {
        $sessionId = session()->getId();
        $items = CartItem::where('session_id', $sessionId)->with('service')->get();
        if ($items->isEmpty()) {
            return null;
        }

        $subtotal = $items->sum(fn ($item) => $item->service->price * $item->quantity);
        $ivaRate = (float) Setting::get('iva_percentage', 16) / 100;

        $discount = 0;
        $discountCode = session('discount_code');
        if ($discountCode) {
            $code = DiscountCode::where('code', $discountCode)->first();
            if ($code && $code->isValid($subtotal)) {
                $discount = $code->calculateDiscount($subtotal);
            }
        }

        $adjustedSubtotal = max(0, $subtotal - $discount);
        $iva = round($adjustedSubtotal * $ivaRate, 2);
        $total = round($adjustedSubtotal + $iva, 2);

        $notes = $items->map(fn ($i) => "{$i->quantity}x {$i->service->name}")->implode(', ');

        return compact('items', 'subtotal', 'discount', 'discountCode', 'adjustedSubtotal', 'iva', 'total', 'notes');
    }

    private function resolveOrCreateClient(array $data): Client
    {
        $client = Client::where('email', $data['email'])->first();
        if (!$client) {
            $client = Client::create([
                'legal_name'    => $data['name'],
                'email'         => $data['email'],
                'phone'         => $data['phone'] ?? null,
                'tax_system'    => '616',
                'cfdi_use'      => 'S01',
                'portal_active' => true,
            ]);
        }
        // 'later' = el cliente decidirá el dominio después del pago — no se guarda nada aún.
        if (!empty($data['domain']) && ($data['domain_type'] ?? '') !== 'later') {
            $client->update([
                'domain'      => $data['domain'],
                'domain_type' => $data['domain_type'] ?? 'own',
            ]);
        }

        // Datos de contacto para el registro del dominio (WHOIS) → perfil del cliente
        $regMap = [
            'reg_street'  => 'address_street',
            'reg_city'    => 'address_city',
            'reg_state'   => 'address_state',
            'reg_zip'     => 'address_zip',
            'reg_country' => 'address_country',
        ];
        $updates = [];
        foreach ($regMap as $input => $column) {
            if (!empty($data[$input])) {
                $updates[$column] = $input === 'reg_country' ? strtoupper($data[$input]) : $data[$input];
            }
        }
        // El CP fiscal (usado para timbrar CFDI) manda: el CP del WHOIS nunca lo pisa.
        if (isset($updates['address_zip']) && !empty($client->address_zip)) {
            unset($updates['address_zip']);
        }
        if ($updates) {
            $client->update($updates);
        }

        return $client;
    }

    private function createInvoiceFromCart(Client $client, array $cartData, string $paymentForm): Order
    {
        return Order::create([
            'client_id'      => $client->id,
            'series'         => 'V',
            'payment_form'   => $paymentForm,
            'payment_method' => 'PUE',
            'use_cfdi'       => $client->cfdi_use ?? 'S01',
            'status'         => 'draft',
            'subtotal'       => $cartData['adjustedSubtotal'],
            'iva_amount'     => $cartData['iva'],
            'total'          => $cartData['total'],
            'discount_code'  => $cartData['discountCode'],
            'notes'          => 'Carrito: ' . $cartData['notes'],
        ]);
    }

    private function clearCart(array $cartData): void
    {
        $cartData['items']->each->delete();
        session()->forget('discount_code');
    }
}
