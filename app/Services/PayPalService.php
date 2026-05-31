<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private string $clientId;
    private string $secret;
    private string $mode;
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId = Setting::get('paypal_client_id', '');
        $this->secret   = Setting::get('paypal_secret', '');
        $this->mode     = Setting::get('paypal_mode', 'sandbox');
        $this->baseUrl  = $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->secret !== '';
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * Crea una orden de PayPal. Devuelve el id de la orden y la URL de aprobación.
     */
    public function createOrder(float $amount, string $description, string $externalReference, string $returnUrl, string $cancelUrl): array
    {
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $externalReference,
                'description'  => mb_substr($description, 0, 127),
                'amount' => [
                    'currency_code' => 'MXN',
                    'value'         => number_format($amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name'          => Setting::get('company_name', 'CRM'),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'PAY_NOW',
                'return_url'          => $returnUrl,
                'cancel_url'          => $cancelUrl,
            ],
        ];

        $response = $this->http()->post("{$this->baseUrl}/v2/checkout/orders", $payload);

        if (!$response->successful()) {
            Log::error('PayPal createOrder failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('No se pudo crear la orden en PayPal: ' . $response->body());
        }

        $data = $response->json();
        $approvalUrl = collect($data['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'id'           => $data['id'],
            'status'       => $data['status'] ?? 'CREATED',
            'approval_url' => $approvalUrl,
            'raw'          => $data,
        ];
    }

    /**
     * Captura la orden previamente aprobada por el comprador.
     */
    public function captureOrder(string $paypalOrderId): array
    {
        $response = $this->http()->post("{$this->baseUrl}/v2/checkout/orders/{$paypalOrderId}/capture");

        if (!$response->successful()) {
            Log::error('PayPal captureOrder failed', ['order' => $paypalOrderId, 'status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('No se pudo capturar el pago de PayPal: ' . $response->body());
        }

        return $response->json();
    }

    public function getOrder(string $paypalOrderId): ?array
    {
        $response = $this->http()->get("{$this->baseUrl}/v2/checkout/orders/{$paypalOrderId}");
        return $response->successful() ? $response->json() : null;
    }

    /**
     * Procesa el resultado de un capture y genera (o actualiza) Payment + Order.
     */
    public function processCapture(Order $order, array $capture): Payment
    {
        $purchaseUnit = $capture['purchase_units'][0] ?? [];
        $captureNode  = $purchaseUnit['payments']['captures'][0] ?? [];
        $captureId    = $captureNode['id'] ?? $capture['id'];
        $status       = strtoupper($captureNode['status'] ?? $capture['status'] ?? 'PENDING');
        $amount       = (float) ($captureNode['amount']['value'] ?? 0);
        $currency     = $captureNode['amount']['currency_code'] ?? 'MXN';
        $paidAt       = isset($captureNode['create_time']) ? \Carbon\Carbon::parse($captureNode['create_time']) : null;

        $internalStatus = match ($status) {
            'COMPLETED'                            => 'approved',
            'PENDING'                              => 'pending',
            'DECLINED', 'FAILED', 'VOIDED'         => 'rejected',
            'REFUNDED', 'PARTIALLY_REFUNDED'       => 'refunded',
            default                                => 'pending',
        };

        $payment = Payment::updateOrCreate(
            ['paypal_order_id' => $capture['id']],
            [
                'order_id'          => $order->id,
                'gateway'           => 'paypal',
                'amount'            => $amount,
                'currency'          => $currency,
                'status'            => $internalStatus,
                'status_detail'     => $captureNode['status_details']['reason'] ?? null,
                'payment_type'      => 'paypal',
                'payment_method_id' => 'paypal',
                'mp_data'           => $capture,
                'paid_at'           => $internalStatus === 'approved' ? ($paidAt ?? now()) : null,
            ]
        );

        if ($payment->wasRecentlyCreated || $payment->wasChanged('status')) {
            if ($internalStatus === 'approved' && !$order->paid_at) {
                $order->update([
                    'status'  => 'sent',
                    'paid_at' => $payment->paid_at,
                ]);
            }
        }

        return $payment;
    }

    /**
     * Valida la firma del webhook usando la API de PayPal.
     */
    public function verifyWebhookSignature(array $headers, string $body): bool
    {
        $webhookId = Setting::get('paypal_webhook_id', '');
        if ($webhookId === '') {
            return false;
        }

        $payload = [
            'auth_algo'         => $headers['paypal-auth-algo'][0] ?? ($headers['Paypal-Auth-Algo'][0] ?? ''),
            'cert_url'          => $headers['paypal-cert-url'][0]  ?? ($headers['Paypal-Cert-Url'][0]  ?? ''),
            'transmission_id'   => $headers['paypal-transmission-id'][0]   ?? ($headers['Paypal-Transmission-Id'][0]   ?? ''),
            'transmission_sig'  => $headers['paypal-transmission-sig'][0]  ?? ($headers['Paypal-Transmission-Sig'][0]  ?? ''),
            'transmission_time' => $headers['paypal-transmission-time'][0] ?? ($headers['Paypal-Transmission-Time'][0] ?? ''),
            'webhook_id'        => $webhookId,
            'webhook_event'     => json_decode($body, true),
        ];

        $response = $this->http()->post("{$this->baseUrl}/v1/notifications/verify-webhook-signature", $payload);

        if (!$response->successful()) {
            Log::warning('PayPal webhook verify failed', ['body' => $response->body()]);
            return false;
        }

        return ($response->json('verification_status') ?? '') === 'SUCCESS';
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->asJson();
    }

    private function getAccessToken(): string
    {
        $cacheKey = "paypal_access_token_{$this->mode}_" . md5($this->clientId);

        return Cache::remember($cacheKey, now()->addMinutes(8), function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->secret)
                ->post("{$this->baseUrl}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

            if (!$response->successful()) {
                throw new \RuntimeException('PayPal OAuth failed: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }
}
