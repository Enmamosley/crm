<?php

namespace App\Http\Controllers;

use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPalService;
use App\Services\ProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $body  = $request->getContent();
        $event = json_decode($body, true) ?: [];

        $eventType = $event['event_type'] ?? '';
        $resource  = $event['resource'] ?? [];

        // Idempotencia
        $eventId = $event['id'] ?? null;
        if ($eventId) {
            $cacheKey = "paypal_webhook_{$eventId}";
            if (Cache::has($cacheKey)) {
                return response()->json(['status' => 'already_processed'], 200);
            }
        }

        $service = new PayPalService();

        // Verificación de firma OBLIGATORIA. Sin webhook_id no se puede validar
        // el origen del evento, así que rechazamos para evitar webhooks forjados
        // que marquen órdenes como pagadas.
        if (!\App\Models\Setting::get('paypal_webhook_id')) {
            Log::warning('PayPal webhook recibido pero paypal_webhook_id no está configurado — rechazado', ['event_id' => $eventId]);
            return response()->json(['status' => 'not_configured'], 403);
        }
        if (!$service->verifyWebhookSignature($request->headers->all(), $body)) {
            Log::warning('PayPal webhook: invalid signature', ['event_id' => $eventId]);
            return response()->json(['status' => 'invalid_signature'], 403);
        }

        // Sólo nos interesan capturas
        $relevant = in_array($eventType, [
            'CHECKOUT.ORDER.APPROVED',
            'PAYMENT.CAPTURE.COMPLETED',
            'PAYMENT.CAPTURE.DENIED',
            'PAYMENT.CAPTURE.REFUNDED',
        ], true);

        if (!$relevant) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Reconciliar contra la orden local
        $reference = $resource['supplementary_data']['related_ids']['order_id'] ?? null
            ?? ($resource['purchase_units'][0]['reference_id'] ?? null);

        // Buscar el Payment existente por capture id (paypal_order_id guarda el order id de PayPal)
        $paypalRefId = $resource['id'] ?? null;
        $payment = Payment::where('paypal_order_id', $paypalRefId)->first();

        if (!$payment && $reference) {
            $order = Order::find($reference);
            if ($order) {
                // Captura recibida sin Payment local — la procesamos
                try {
                    $service->processCapture($order, [
                        'id' => $paypalRefId,
                        'status' => 'COMPLETED',
                        'purchase_units' => [['payments' => ['captures' => [$resource]]]],
                    ]);
                    if ($order->paid_at) {
                        (new ProvisioningService())->provisionForOrder($order);
                        DiscountCode::consumeForCode($order->discount_code);
                    }
                } catch (\Throwable $e) {
                    Log::error('PayPal webhook: process capture failed', ['error' => $e->getMessage()]);
                }
            }
        } elseif ($payment) {
            $wasPending = $payment->status !== 'approved';
            try {
                $service->processCapture($payment->order, [
                    'id' => $payment->paypal_order_id,
                    'status' => $resource['status'] ?? 'COMPLETED',
                    'purchase_units' => [['payments' => ['captures' => [$resource]]]],
                ]);

                if ($wasPending && $payment->fresh()->status === 'approved' && $payment->order) {
                    (new ProvisioningService())->provisionForOrder($payment->order);
                    DiscountCode::consumeForCode($payment->order->discount_code);
                }
            } catch (\Throwable $e) {
                Log::error('PayPal webhook: update failed', ['error' => $e->getMessage()]);
            }
        }

        if ($eventId) {
            Cache::put("paypal_webhook_{$eventId}", true, 3600);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
