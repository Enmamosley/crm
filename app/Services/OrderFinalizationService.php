<?php

namespace App\Services;

use App\Mail\PaymentConfirmed;
use App\Models\ActivityLog;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Todo lo que ocurre cuando una orden pasa a pagada, en un solo sitio y sea
 * cual sea el flujo (tarjeta, PayPal, OXXO/SPEI por webhook, transferencia o
 * pago manual del panel): timbrado del CFDI, comprobante por correo,
 * aprovisionamiento, consumo del cupón y evento de compra a Meta.
 *
 * Antes esto estaba partido en dos: el timbrado y el correo vivían aquí, pero
 * el aprovisionamiento, el cupón y Meta los llamaba cada controlador que
 * confirmaba el pago — cinco copias que había que mantener sincronizadas. Los
 * pagos del portal no aprovisionaban nada y los pagos manuales del panel ni
 * timbraban ni enviaban comprobante.
 *
 * Es idempotente: la transición a pagada la resuelve Order::markPaid(), así
 * que llamarlo dos veces sobre la misma orden no duplica nada.
 */
class OrderFinalizationService
{
    public function __construct(
        private InvoicingManager $invoicing,
        private ProvisioningService $provisioning,
        private MetaConversionsService $meta,
    ) {}

    /** @return bool true si esta llamada fue la que finalizó la orden. */
    public function finalize(Payment $payment, ?Request $request = null, string $status = 'sent'): bool
    {
        $order = $payment->order;

        if (!$order || !$order->markPaid($payment->paid_at ?? now(), $status)) {
            return false;
        }

        $this->stamp($order, $payment);
        $this->sendConfirmation($order, $payment);
        $this->provision($order);
        $this->consumeDiscount($order);
        $this->sendMetaPurchase($order, $request);

        ActivityLog::log('payment_approved', $payment, "Pago #{$payment->id} aprobado por \${$payment->amount}");

        return true;
    }

    /**
     * Auto-timbra el CFDI si procede. "Sin factura" (none) NO se timbra: esas
     * ventas van al recibo de pago y, en su caso, a la factura global mensual.
     */
    private function stamp(Order $order, Payment $payment): void
    {
        if (($order->billing_preference ?? 'none') === 'none'
            || $order->isStamped()
            || !$this->invoicing->isConfigured()) {
            return;
        }

        try {
            // '99' es "por definir": no debe pisar la forma de pago que el
            // admin eligió al registrar un pago manual.
            $satForm = $payment->satPaymentForm();
            if ($satForm !== '99') {
                $order->update(['payment_form' => $satForm]);
            }

            $this->invoicing->stampInvoice($order);
            ActivityLog::log('auto_stamped', $order, "Factura {$order->folio()} timbrada automáticamente tras el pago");
        } catch (\Throwable $e) {
            Log::error('Auto-stamp failed after payment', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /** Correo de confirmación, que también sirve de comprobante para el cliente. */
    private function sendConfirmation(Order $order, Payment $payment): void
    {
        if (!$order->client?->email) {
            return;
        }

        try {
            Mail::to($order->client->email)->send(new PaymentConfirmed($payment));
        } catch (\Throwable $e) {
            Log::error('Payment confirmation email failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
        }
    }

    /** Dominio en Cosmotown y hosting en 20i. El servicio ya es idempotente. */
    private function provision(Order $order): void
    {
        try {
            $this->provisioning->provisionForOrder($order);
        } catch (\Throwable $e) {
            Log::error('Provisioning failed after payment', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    private function consumeDiscount(Order $order): void
    {
        try {
            DiscountCode::consumeForCode($order->discount_code);
        } catch (\Throwable $e) {
            Log::error('Discount consumption failed after payment', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Evento Purchase de la Conversions API. El Request sólo llega desde los
     * flujos síncronos: aporta IP, user agent y cookies del navegador. En
     * webhooks y pagos del panel no hay navegador del comprador que mirar.
     */
    private function sendMetaPurchase(Order $order, ?Request $request): void
    {
        try {
            $this->meta->sendPurchase($order, $request);
        } catch (\Throwable $e) {
            Log::error('Meta purchase event failed after payment', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
