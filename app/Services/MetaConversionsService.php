<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Conversions API de Meta (server-side). Dispara el evento Purchase en el
 * momento exacto en que el pago se CONFIRMA (tarjeta, PayPal, OXXO/SPEI vía
 * webhook, transferencia/pago manual). Se deduplica con el Pixel del navegador
 * usando el mismo event_id ("order_<id>").
 */
class MetaConversionsService
{
    private const GRAPH_VERSION = 'v21.0';

    private string $pixelId;
    private string $token;

    public function __construct()
    {
        $this->pixelId = (string) Setting::get('meta_pixel_id', '');
        $this->token   = (string) Setting::get('meta_capi_token', '');
    }

    public function isConfigured(): bool
    {
        return $this->pixelId !== '' && $this->token !== '';
    }

    /** event_id determinista para deduplicar con el Pixel del navegador. */
    public static function purchaseEventId(Order $order): string
    {
        return 'order_' . $order->id;
    }

    /**
     * Envía el evento Purchase a Meta. Silencioso ante fallos (nunca rompe el
     * flujo de pago). $request es opcional: si viene, agrega IP/User-Agent/_fbp/_fbc
     * para mejorar el match.
     */
    public function sendPurchase(Order $order, ?Request $request = null): void
    {
        if (!$this->isConfigured() || !$order) {
            return;
        }

        try {
            $client = $order->client;

            $userData = array_filter([
                'em'                => $client?->email ? [hash('sha256', strtolower(trim($client->email)))] : null,
                'ph'                => $client?->phone ? [hash('sha256', preg_replace('/\D/', '', $client->phone))] : null,
                'client_ip_address' => $request?->ip(),
                'client_user_agent' => $request?->userAgent(),
                'fbp'               => $request?->cookie('_fbp'),
                'fbc'               => $request?->cookie('_fbc'),
            ]);

            $event = [
                'event_name'       => 'Purchase',
                'event_time'       => $order->paid_at ? $order->paid_at->timestamp : time(),
                'event_id'         => self::purchaseEventId($order),
                'action_source'    => 'website',
                'event_source_url' => $request?->fullUrl() ?? (Setting::get('app_url', config('app.url')) . '/buy'),
                'user_data'        => $userData,
                'custom_data'      => array_filter([
                    'currency'     => 'MXN',
                    'value'        => round((float) $order->total, 2),
                    'content_type' => 'product',
                    'order_id'     => (string) $order->id,
                ], fn ($v) => $v !== null && $v !== ''),
            ];

            $payload = ['data' => [$event]];

            if ($testCode = Setting::get('meta_test_event_code', '')) {
                $payload['test_event_code'] = $testCode;
            }

            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->post(
                    "https://graph.facebook.com/" . self::GRAPH_VERSION . "/{$this->pixelId}/events?access_token={$this->token}",
                    $payload
                );

            if (!$response->successful()) {
                Log::warning('Meta CAPI Purchase no exitoso', [
                    'order'  => $order->id,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Meta CAPI Purchase falló', ['order' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
