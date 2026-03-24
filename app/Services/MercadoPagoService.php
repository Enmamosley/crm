<?php

namespace App\Services;

use App\Mail\PaymentConfirmed;
use App\Models\ActivityLog;
use App\Models\ClientInvoice;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MercadoPagoService
{
    private string $accessToken;
    private string $baseUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        $this->accessToken = Setting::get('mp_access_token', '');
    }

    // ── Crear pagos ──────────────────────────────────────

    /**
     * Pago con tarjeta (token generado por MercadoPago.js).
     */
    public function createCardPayment(
        ClientInvoice $invoice,
        string $token,
        string $paymentMethodId,
        string $email,
        int $installments = 1,
        ?string $issuerId = null,
    ): Payment {
        $payload = [
            'transaction_amount' => (float) $invoice->total,
            'token'              => $token,
            'description'        => "Factura {$invoice->folio()} – {$invoice->client->legal_name}",
            'installments'       => $installments,
            'payment_method_id'  => $paymentMethodId,
            'payer'              => ['email' => $email],
            'external_reference' => "invoice_{$invoice->id}",
        ];

        if ($issuerId) {
            $payload['issuer_id'] = $issuerId;
        }

        return $this->processPayment($invoice, $payload);
    }

    /**
     * Pago en OXXO (genera ticket con código de barras).
     */
    public function createOxxoPayment(ClientInvoice $invoice, string $email): Payment
    {
        return $this->processPayment($invoice, [
            'transaction_amount' => (float) $invoice->total,
            'description'        => "Factura {$invoice->folio()} – {$invoice->client->legal_name}",
            'payment_method_id'  => 'oxxo',
            'payer'              => ['email' => $email],
            'external_reference' => "invoice_{$invoice->id}",
        ]);
    }

    /**
     * Pago por transferencia SPEI.
     */
    public function createSpeiPayment(ClientInvoice $invoice, string $email): Payment
    {
        return $this->processPayment($invoice, [
            'transaction_amount' => (float) $invoice->total,
            'description'        => "Factura {$invoice->folio()} – {$invoice->client->legal_name}",
            'payment_method_id'  => 'bank_transfer',
            'payer'              => ['email' => $email],
            'external_reference' => "invoice_{$invoice->id}",
        ]);
    }

    // ── Consultar / sincronizar ──────────────────────────

    /**
     * Consulta el estado de un pago en la API de MP.
     */
    public function getPayment(string $mpPaymentId): ?array
    {
        $response = $this->http()->get("{$this->baseUrl}/v1/payments/{$mpPaymentId}");

        if ($response->failed()) {
            Log::error('MP getPayment failed', [
                'mp_payment_id' => $mpPaymentId,
                'status'        => $response->status(),
            ]);
            return null;
        }

        return $response->json();
    }

    /**
     * Sincroniza el estado del pago desde MP y actualiza factura si procede.
     */
    public function syncPaymentStatus(Payment $payment): Payment
    {
        if (!$payment->mp_payment_id) {
            return $payment;
        }

        $data = $this->getPayment($payment->mp_payment_id);
        if (!$data) {
            return $payment;
        }

        $newStatus = $data['status'] ?? $payment->status;
        $paidAt    = $payment->paid_at;

        if ($newStatus === 'approved' && !$payment->paid_at) {
            $paidAt = isset($data['date_approved'])
                ? \Carbon\Carbon::parse($data['date_approved'])
                : now();
        }

        $payment->update([
            'status'        => $newStatus,
            'status_detail' => $data['status_detail'] ?? $payment->status_detail,
            'payment_type'  => $data['payment_type_id'] ?? $payment->payment_type,
            'mp_data'       => $data,
            'paid_at'       => $paidAt,
        ]);

        // Marcar factura como pagada
        if ($newStatus === 'approved' && !$payment->invoice->paid_at) {
            $invoice = $payment->invoice;
            $invoice->update(['paid_at' => $paidAt]);

            // Auto-timbrar si no está timbrada y el payment_form coincide
            if (!$invoice->isStamped() && Setting::get('facturapi_api_key')) {
                try {
                    $invoice->update(['payment_form' => $payment->satPaymentForm()]);
                    (new FacturapiService())->stampInvoice($invoice);
                    ActivityLog::log('auto_stamped', $invoice, "Factura {$invoice->folio()} timbrada automáticamente tras pago MP");
                } catch (\Throwable $e) {
                    Log::error('Auto-stamp failed after payment', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
                }
            }

            // Enviar email de confirmación
            if ($invoice->client->email) {
                try {
                    Mail::to($invoice->client->email)->send(new PaymentConfirmed($payment));
                } catch (\Throwable $e) {
                    Log::error('Payment confirmation email failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
                }
            }

            ActivityLog::log('payment_approved', $payment, "Pago #{$payment->id} aprobado por \${$payment->amount} para factura {$invoice->folio()}");
        }

        return $payment;
    }

    // ── Validar webhook ──────────────────────────────────

    /**
     * Valida la firma HMAC del webhook de MP.
     */
    public function validateWebhookSignature(string $xSignature, string $xRequestId, string $dataId): bool
    {
        $secret = Setting::get('mp_webhook_secret', '');
        if (!$secret) {
            Log::warning('MP webhook received but no webhook secret configured — rejecting');
            return false;
        }

        $parts = [];
        foreach (explode(',', $xSignature) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }

        $ts = $parts['ts'] ?? '';
        $v1 = $parts['v1'] ?? '';

        if (!$ts || !$v1) {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $v1);
    }

    // ── Internos ─────────────────────────────────────────

    private function processPayment(ClientInvoice $invoice, array $payload): Payment
    {
        $idempotencyKey = "inv_{$invoice->id}_" . now()->timestamp;

        $response = $this->http()
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->post("{$this->baseUrl}/v1/payments", $payload);

        $data = $response->json();

        if ($response->failed()) {
            Log::error('MP payment creation failed', [
                'invoice_id' => $invoice->id,
                'status'     => $response->status(),
                'body'       => $data,
            ]);

            $msg = $data['message'] ?? ($data['cause'][0]['description'] ?? 'Error al procesar el pago en Mercado Pago');
            throw new \RuntimeException($msg);
        }

        $isApproved = ($data['status'] ?? '') === 'approved';

        $payment = Payment::create([
            'client_invoice_id' => $invoice->id,
            'mp_payment_id'     => (string) ($data['id'] ?? ''),
            'amount'            => $data['transaction_amount'] ?? $invoice->total,
            'currency'          => $data['currency_id'] ?? 'MXN',
            'status'            => $data['status'] ?? 'pending',
            'status_detail'     => $data['status_detail'] ?? null,
            'payment_type'      => $data['payment_type_id'] ?? null,
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'mp_data'           => $data,
            'paid_at'           => $isApproved ? now() : null,
        ]);

        if ($isApproved) {
            $invoice->update(['paid_at' => now()]);
        }

        return $payment;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->contentType('application/json');
    }
}
