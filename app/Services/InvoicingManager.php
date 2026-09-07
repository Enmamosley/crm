<?php

namespace App\Services;

use App\Models\Order;
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
}
