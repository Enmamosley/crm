<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MercadoPagoService;
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

        try {
            $service->syncPaymentStatus($payment);
            Cache::put($cacheKey, true, 3600);
            Log::info('MP webhook: payment synced', [
                'payment_id' => $payment->id,
                'status'     => $payment->fresh()->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('MP webhook: sync error', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
