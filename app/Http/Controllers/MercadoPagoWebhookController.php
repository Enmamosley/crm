<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use App\Services\CosmotownService;
use App\Services\MercadoPagoService;
use App\Services\TwentyIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $type   = $request->input('type');
        $dataId = $request->input('data.id');

        if ($type !== 'payment' || !$dataId) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Idempotencia: ignorar webhooks ya procesados (cache 1 hora)
        $cacheKey = 'mp_webhook_' . $dataId . '_' . $request->header('x-request-id', '');
        if (Cache::has($cacheKey)) {
            return response()->json(['status' => 'already_processed'], 200);
        }

        $service   = new MercadoPagoService();
        $signature = $request->header('x-signature', '');
        $requestId = $request->header('x-request-id', '');

        if (!$service->validateWebhookSignature($signature, $requestId, (string) $dataId)) {
            Log::warning('MP webhook: invalid signature', ['data_id' => $dataId]);
            return response()->json(['status' => 'invalid_signature'], 403);
        }

        $payment = Payment::where('mp_payment_id', (string) $dataId)->first();

        if (!$payment) {
            Log::info('MP webhook: payment not found locally', ['mp_id' => $dataId]);
            return response()->json(['status' => 'not_found'], 200);
        }

        $wasPending = $payment->status !== 'approved';

        try {
            $service->syncPaymentStatus($payment);
            Cache::put($cacheKey, true, 3600);
            Log::info('MP webhook: payment synced', [
                'payment_id' => $payment->id,
                'status'     => $payment->fresh()->status,
            ]);

            // Provisionar hosting/dominio cuando un pago pendiente se aprueba
            if ($wasPending && $payment->fresh()->status === 'approved') {
                $this->provisionAfterPayment($payment);
            }
        } catch (\Throwable $e) {
            Log::error('MP webhook: sync error', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Provisiona dominio y hosting tras confirmación de pago (OXXO/SPEI).
     */
    private function provisionAfterPayment(Payment $payment): void
    {
        $invoice = $payment->invoice;
        $client  = $invoice->client;

        if (!$client->domain) {
            return;
        }

        // Registrar dominio Cosmotown si aplica
        if ($client->domain_type === 'cosmotown') {
            try {
                $cosmotown = new CosmotownService();
                if ($cosmotown->isConfigured()) {
                    $cosmotown->register($client->domain);
                    ActivityLog::log('domain_registered', $client, "Dominio {$client->domain} registrado en Cosmotown (vía webhook)");
                }
            } catch (\Throwable $e) {
                Log::error('Webhook domain registration failed', ['domain' => $client->domain, 'error' => $e->getMessage()]);
            }
        }

        // Crear hosting 20i si no existe
        if ($client->twentyi_package_id) {
            return;
        }

        // Buscar el servicio desde las notas de la factura
        $serviceName = str_replace('Compra directa: ', '', $invoice->notes ?? '');
        $svc = Service::where('name', $serviceName)->first();

        if (!$svc || !$svc->twentyi_package_bundle_id) {
            return;
        }

        try {
            $packageId = (new TwentyIService())->createHostingPackage($client->domain, $svc->twentyi_package_bundle_id);
            $client->update(['twentyi_package_id' => $packageId]);
            ActivityLog::log('hosting_provisioned', $client, "Hosting 20i creado vía webhook: paquete #{$packageId} para {$client->domain}");
        } catch (\Throwable $e) {
            Log::error('Webhook hosting provisioning failed', ['error' => $e->getMessage()]);
        }
    }
}
