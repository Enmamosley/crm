<?php

namespace App\Http\Controllers\Portal;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\MercadoPagoService;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Cobro de una orden desde el portal: tarjeta, OXXO, SPEI, PayPal y transferencia.
 */

class PaymentController extends PortalController
{
    public function __construct(
        private MercadoPagoService $mercadoPago,
        private PayPalService $paypal,
    ) {}

    public function checkout(string $token, Order $order)
    {
        $client = $this->client();
        $this->authorizeOwnership($client, $order);
        abort_if($order->status === 'cancelled', 404, 'Factura cancelada.');

        if ($order->paid_at) {
            return redirect()->route('portal.dashboard', $token)
                ->with('success', 'Esta factura ya fue pagada.');
        }

        $mpPublicKey = Setting::get('mp_public_key', '');
        $paypal = $this->paypal;
        $paypalClientId = $paypal->isConfigured() ? $paypal->clientId() : '';
        $paypalMode     = $paypal->mode();

        $bankData = [
            'name'        => Setting::get('bank_name', ''),
            'beneficiary' => Setting::get('bank_beneficiary', ''),
            'account'     => Setting::get('bank_account', ''),
            'clabe'       => Setting::get('bank_clabe', ''),
            'reference'   => Setting::get('bank_reference', ''),
        ];
        $hasBankData = !empty($bankData['clabe']) || !empty($bankData['account']);

        $invoice = $order;

        return view('portal.checkout', compact('client', 'invoice', 'mpPublicKey', 'paypalClientId', 'paypalMode', 'bankData', 'hasBankData'));
    }

    public function payWithCard(Request $request, string $token, Order $order)
    {
        $client = $this->client();
        $this->authorizeOwnership($client, $order);

        if ($order->paid_at) {
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
            $order->update(['billing_preference' => $validated['billing_preference']]);
        }

        try {
            $payment = $this->mercadoPago->createCardPayment(
                $order,
                $validated['token'],
                $validated['payment_method_id'],
                $validated['email'],
                (int) ($validated['installments'] ?? 1),
                $validated['issuer_id'] ?? null,
                $request,
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

    public function payWithOxxo(Request $request, string $token, Order $order)
    {
        $client = $this->client();
        $this->authorizeOwnership($client, $order);

        if ($order->paid_at) {
            return back()->with('error', 'Esta factura ya fue pagada.');
        }

        $validated = $request->validate([
            'email'              => 'required|email',
            'billing_preference' => 'nullable|in:fiscal,publico_general,none',
        ]);
        if (!empty($validated['billing_preference'])) {
            $order->update(['billing_preference' => $validated['billing_preference']]);
        }

        try {
            $payment = $this->mercadoPago->createOxxoPayment($order, $validated['email']);
            return redirect()->route('portal.payment.status', [$token, $payment]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Portal OXXO falló', ['order' => $order->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'No se pudo generar la referencia OXXO. Intenta de nuevo.');
        }
    }

    public function payWithSpei(Request $request, string $token, Order $order)
    {
        $client = $this->client();
        $this->authorizeOwnership($client, $order);

        if ($order->paid_at) {
            return back()->with('error', 'Esta factura ya fue pagada.');
        }

        $validated = $request->validate([
            'email'              => 'required|email',
            'billing_preference' => 'nullable|in:fiscal,publico_general,none',
        ]);
        if (!empty($validated['billing_preference'])) {
            $order->update(['billing_preference' => $validated['billing_preference']]);
        }

        try {
            $payment = $this->mercadoPago->createSpeiPayment($order, $validated['email']);
            return redirect()->route('portal.payment.status', [$token, $payment]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Portal SPEI falló', ['order' => $order->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'No se pudo generar la referencia SPEI. Intenta de nuevo.');
        }
    }

    public function createPaypalOrder(Request $request, string $token, Order $order)
    {
        $client = $this->client();
        $this->authorizeOwnership($client, $order);
        if ($order->paid_at) {
            return response()->json(['error' => 'Esta factura ya fue pagada.'], 422);
        }

        $paypal = $this->paypal;
        if (!$paypal->isConfigured()) {
            return response()->json(['error' => 'PayPal no está configurado.'], 422);
        }

        try {
            $pp = $paypal->createOrder(
                (float) $order->total,
                'Factura ' . ($order->folio() ?: "#{$order->id}"),
                (string) $order->id,
                route('portal.checkout', [$token, $order]),
                route('portal.checkout', [$token, $order]),
            );
            return response()->json([
                'paypalOrderId' => $pp['id'],
                'localOrderId'  => $order->id,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Portal PayPal createOrder falló', ['order' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'No se pudo iniciar el pago con PayPal.'], 422);
        }
    }

    public function capturePaypalOrder(Request $request, string $token, Order $order)
    {
        $client = $this->client();
        $this->authorizeOwnership($client, $order);

        $validated = $request->validate([
            'paypalOrderId' => 'required|string',
        ]);

        $paypal = $this->paypal;
        try {
            $capture = $paypal->captureOrder($validated['paypalOrderId']);
            $payment = $paypal->processCapture($order, $capture, $request);

            return response()->json([
                'success'  => $payment->isApproved(),
                'status'   => $payment->status,
                'redirect' => route('portal.payment.status', [$token, $payment]),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Portal PayPal capture falló', ['order' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'No se pudo capturar el pago.'], 422);
        }
    }

    public function payWithTransfer(Request $request, string $token, Order $order)
    {
        $client = $this->client();
        $this->authorizeOwnership($client, $order);

        if ($order->paid_at) {
            return back()->with('error', 'Esta factura ya fue pagada.');
        }

        $validated = $request->validate([
            'email'              => 'required|email',
            'proof'              => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'billing_preference' => 'required|in:fiscal,publico_general,none',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('payment-proofs', 'local');
        }

        $payment = $order->payments()->create([
            'amount'            => $order->total,
            'currency'          => 'MXN',
            'status'            => 'pending',
            'payment_type'      => 'transfer',
            'payment_method_id' => 'bank_transfer',
            'proof_path'        => $proofPath,
            'payment_notes'     => 'Comprobante enviado por cliente vía portal',
            'paid_at'           => null,
        ]);

        $order->update(['billing_preference' => $validated['billing_preference']]);

        return redirect()->route('portal.payment.status', [$token, $payment]);
    }

    public function paymentStatus(Request $request, string $token, Payment $payment)
    {
        $client = $this->client();
        $this->authorizeOwnership($client, $payment->order);

        // Refrescar estado desde MP si sigue pendiente
        if ($payment->isPending() && $payment->mp_payment_id) {
            try {
                $this->mercadoPago->syncPaymentStatus($payment, $request);
                $payment->refresh();
            } catch (\Throwable) {}
        }

        $invoice = $payment->order;

        return view('portal.payment-status', compact('client', 'payment', 'invoice'));
    }
}
