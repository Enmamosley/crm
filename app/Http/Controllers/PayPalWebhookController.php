<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CosmotownService;
use App\Services\PayPalService;
use App\Services\TwentyIService;
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

        // Verificación de firma — sólo si webhook_id está configurado
        if (\App\Models\Setting::get('paypal_webhook_id')) {
            if (!$service->verifyWebhookSignature($request->headers->all(), $body)) {
                Log::warning('PayPal webhook: invalid signature', ['event_id' => $eventId]);
                return response()->json(['status' => 'invalid_signature'], 403);
            }
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

                if ($wasPending && $payment->fresh()->status === 'approved') {
                    $this->provisionAfterPayment($payment->fresh());
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

    private function provisionAfterPayment(Payment $payment): void
    {
        $order  = $payment->order;
        $client = $order?->client;

        if (!$client || !$client->domain) {
            return;
        }

        if ($client->domain_type === 'cosmotown') {
            try {
                $cosmotown = new CosmotownService();
                if ($cosmotown->isConfigured()) {
                    $cosmotown->register($client->domain);
                    ActivityLog::log('domain_registered', $client, "Dominio {$client->domain} registrado vía PayPal webhook");
                }
            } catch (\Throwable $e) {
                Log::error('PayPal webhook domain registration failed', ['error' => $e->getMessage()]);
            }
        }

        if (!$client->twentyi_package_id) {
            try {
                // Heurística simple: tomar el primer item de la orden si tiene paquete bundle
                $item = $order->items->first();
                $svc  = $item ? \App\Models\Service::where('name', $item->description)->first() : null;
                if ($svc && $svc->twentyi_package_bundle_id) {
                    $pkgId = (new TwentyIService())->createHostingPackage($client->domain, $svc->twentyi_package_bundle_id);
                    $client->update(['twentyi_package_id' => $pkgId]);
                    ActivityLog::log('hosting_provisioned', $client, "Hosting 20i vía PayPal webhook: paquete #{$pkgId}");
                }
            } catch (\Throwable $e) {
                Log::error('PayPal webhook hosting provisioning failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
