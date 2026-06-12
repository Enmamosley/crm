<?php

namespace App\Services;

use App\Mail\PaymentConfirmed;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Acciones tras CONFIRMARSE un pago, comunes a TODOS los flujos
 * (tarjeta, PayPal, OXXO/SPEI, transferencia/manual): auto-timbrado del CFDI y
 * correo de confirmación (que también sirve de comprobante de pago al cliente).
 *
 * Antes, esto vivía sólo dentro de MercadoPagoService::syncPaymentStatus, así que
 * los pagos con tarjeta síncronos y PayPal NO enviaban correo ni timbraban.
 *
 * Debe llamarse UNA sola vez: justo cuando la orden pasa a pagada.
 */
class OrderFinalizationService
{
    public function finalize(Payment $payment): void
    {
        $order = $payment->order;
        if (!$order) {
            return;
        }

        // Auto-timbrar el CFDI si no está timbrado y hay proveedor configurado.
        // "Sin factura" (none) NO se timbra automáticamente: esas ventas van al
        // recibo de pago y, en su caso, a la factura global mensual del contador.
        $invoicing = new InvoicingManager();
        if (($order->billing_preference ?? 'none') !== 'none'
            && !$order->isStamped()
            && $invoicing->isConfigured()) {
            try {
                $order->update(['payment_form' => $payment->satPaymentForm()]);
                $invoicing->stampInvoice($order);
                ActivityLog::log('auto_stamped', $order, "Factura {$order->folio()} timbrada automáticamente tras el pago");
            } catch (\Throwable $e) {
                Log::error('Auto-stamp failed after payment', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        // Correo de confirmación (comprobante de pago para el cliente).
        if ($order->client && $order->client->email) {
            try {
                Mail::to($order->client->email)->send(new PaymentConfirmed($payment));
            } catch (\Throwable $e) {
                Log::error('Payment confirmation email failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        ActivityLog::log('payment_approved', $payment, "Pago #{$payment->id} aprobado por \${$payment->amount}");
    }
}
