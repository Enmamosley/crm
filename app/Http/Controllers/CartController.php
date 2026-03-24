<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CartItem;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\DiscountCode;
use App\Models\Service;
use App\Models\Setting;
use App\Services\MercadoPagoService;
use App\Services\TwentyIService;
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

        if (!$code || !$code->isValid()) {
            return back()->with('error', 'Código de descuento inválido o expirado.');
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
            'domain_type'       => 'nullable|in:cosmotown,own',
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
                    foreach ($cartData['items'] as $item) {
                        $this->provisionHosting($client, $item->service, $validated['domain'] ?? null);
                    }
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
            'domain_type' => 'nullable|in:cosmotown,own',
        ]);

        $client = $this->resolveOrCreateClient($validated);

        try {
            return DB::transaction(function () use ($client, $validated, $cartData) {
                $invoice = $this->createInvoiceFromCart($client, $cartData, '01');
                $payment = (new MercadoPagoService())->createOxxoPayment($invoice, $validated['email']);
                $this->clearCart($cartData);
                ActivityLog::log('cart_purchase', $invoice, "Compra por carrito (OXXO) por {$validated['email']}");
                foreach ($cartData['items'] as $item) {
                    $this->provisionHosting($client, $item->service, $validated['domain'] ?? null);
                }
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
            'domain_type' => 'nullable|in:cosmotown,own',
        ]);

        $client = $this->resolveOrCreateClient($validated);

        try {
            return DB::transaction(function () use ($client, $validated, $cartData) {
                $invoice = $this->createInvoiceFromCart($client, $cartData, '03');
                $payment = (new MercadoPagoService())->createSpeiPayment($invoice, $validated['email']);
                $this->clearCart($cartData);
                ActivityLog::log('cart_purchase', $invoice, "Compra por carrito (SPEI) por {$validated['email']}");
                foreach ($cartData['items'] as $item) {
                    $this->provisionHosting($client, $item->service, $validated['domain'] ?? null);
                }
                return redirect()->route('portal.dashboard', $client->portal_token);
            });
        } catch (\Throwable $e) {
            Log::error('Cart SPEI payment failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'No se pudo procesar el pago. Intenta de nuevo.')->withInput();
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
        if (!empty($data['domain'])) {
            $client->update([
                'domain'      => $data['domain'],
                'domain_type' => $data['domain_type'] ?? 'own',
            ]);
        }
        return $client;
    }

    private function provisionHosting(Client $client, Service $service, ?string $domain = null): void
    {
        if (!$service->twentyi_package_bundle_id) {
            return;
        }
        $domain = $domain ?: $client->domain;
        if (!$domain) {
            return;
        }
        try {
            $packageId = (new TwentyIService())->createHostingPackage($domain, $service->twentyi_package_bundle_id);
            $client->update(['twentyi_package_id' => $packageId]);
            ActivityLog::log('hosting_provisioned', $client, "Hosting 20i creado: paquete #{$packageId} para {$domain}");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('provisionHosting failed: ' . $e->getMessage());
        }
    }

    private function createInvoiceFromCart(Client $client, array $cartData, string $paymentForm): ClientInvoice
    {
        return ClientInvoice::create([
            'client_id'      => $client->id,
            'series'         => 'V',
            'payment_form'   => $paymentForm,
            'payment_method' => 'PUE',
            'use_cfdi'       => $client->cfdi_use ?? 'S01',
            'status'         => 'draft',
            'subtotal'       => $cartData['adjustedSubtotal'],
            'iva_amount'     => $cartData['iva'],
            'total'          => $cartData['total'],
            'notes'          => 'Carrito: ' . $cartData['notes'],
        ]);
    }

    private function clearCart(array $cartData): void
    {
        $cartData['items']->each->delete();

        if ($cartData['discountCode']) {
            $code = DiscountCode::where('code', $cartData['discountCode'])->first();
            $code?->incrementUses();
        }
        session()->forget('discount_code');
    }
}
