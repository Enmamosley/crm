<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentComplement;
use App\Models\Setting;

/**
 * Enruta el timbrado/cancelación al proveedor configurado en Ajustes
 * (invoicing_provider: facturapi | finkok). La cancelación se enruta por el
 * proveedor que TIMBRÓ el documento (source), no por el ajuste actual —
 * cambiar de proveedor no debe romper cancelaciones de CFDIs viejos.
 */
class InvoicingManager
{
    public function __construct(
        private FinkokService $finkok,
        private FacturapiService $facturapi,
    ) {}

    public function provider(): string
    {
        return Setting::get('invoicing_provider', 'facturapi');
    }

    public function isConfigured(): bool
    {
        return $this->provider() === 'finkok'
            ? $this->finkok->isConfigured()
            : $this->facturapi->isConfigured();
    }

    /** @return array{success: bool, data: array} */
    public function stampInvoice(Order $order): array
    {
        if ($this->provider() === 'finkok') {
            return $this->finkok->stampInvoice($order);
        }

        return $this->facturapi->stampInvoice($order);
    }

    /** @return array{success: bool, data: array} */
    public function cancelInvoice(Order $order, string $motive): array
    {
        $source = $order->fiscalDocument?->source ?? 'facturapi';

        if ($source === 'finkok') {
            return $this->finkok->cancelInvoice($order, $motive);
        }

        return $this->facturapi->cancelInvoice($order, $motive);
    }

    /**
     * Emite el Recibo Electrónico de Pago que debe todo cobro contra una
     * factura PPD. Es idempotente: un pago genera un complemento y sólo uno
     * (payment_id es único), y un complemento ya timbrado no se reintenta.
     *
     * Se enruta por el proveedor que TIMBRÓ la factura, no por el ajuste
     * actual: el REP tiene que salir del mismo sitio que el CFDI que relaciona.
     */
    public function issuePaymentComplement(Order $order, Payment $payment): ?PaymentComplement
    {
        if (!$order->isPpd() || !$order->isStamped()) {
            return null;
        }

        $source = $order->fiscalDocument?->source ?? 'facturapi';

        $complement = PaymentComplement::firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'order_id'    => $order->id,
                'source'      => $source,
                'amount'      => $payment->amount,
                'installment' => $order->paymentComplements()->count() + 1,
                'status'      => 'pending',
            ],
        );

        if ($complement->isStamped()) {
            return $complement;
        }

        $complement->loadMissing('order', 'payment');

        if ($source === 'finkok') {
            $this->finkok->stampPaymentComplement($complement);
        } else {
            $this->facturapi->stampPaymentComplement($complement);
        }

        return $complement->fresh();
    }
}
