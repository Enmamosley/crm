<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Reparto del IVA línea por línea.
 *
 * Cada punto de venta calculaba el impuesto como `subtotal * tasa` sobre el
 * pedido entero, sin mirar si alguna línea estaba exenta o fuera del objeto
 * del impuesto. El CFDI sí lo miraba: un servicio exento se cobraba con IVA y
 * se timbraba sin él. Con Facturapi salía una factura por menos de lo cobrado
 * y con Finkok no salía ninguna, porque CfdiBuilderService aborta cuando el
 * total del CFDI no cuadra con el de la orden.
 *
 * El descuento se reparte a prorrata entre la parte gravada y la exenta: un
 * descuento global no puede convertir en gravado lo que no lo es, ni al revés.
 */
final class TaxBreakdown
{
    private function __construct(
        /** Suma de las líneas, antes del descuento. */
        public readonly float $subtotal,
        public readonly float $discount,
        /** Lo que se factura antes de impuestos: subtotal menos descuento. */
        public readonly float $net,
        public readonly float $iva,
        public readonly float $total,
    ) {}

    /**
     * @param  iterable<array{amount: float|int|string, taxed?: bool}>  $lines
     * @param  float|null  $rate  Tasa en tanto por uno; por defecto, la de Ajustes.
     */
    public static function forLines(iterable $lines, float $discount = 0.0, ?float $rate = null): self
    {
        $rate ??= self::rate();

        $subtotal = 0.0;
        $taxable  = 0.0;

        foreach ($lines as $line) {
            $amount    = (float) $line['amount'];
            $subtotal += $amount;

            if ($line['taxed'] ?? true) {
                $taxable += $amount;
            }
        }

        $discount = min(max($discount, 0.0), $subtotal);

        // Prorrateo: el descuento rebaja la base gravada en la proporción en
        // que ésta pesa dentro del subtotal.
        $taxableBase = $subtotal > 0.0
            ? $taxable - $discount * ($taxable / $subtotal)
            : 0.0;

        $net = round($subtotal - $discount, 2);
        $iva = round($taxableBase * $rate, 2);

        return new self(
            subtotal: round($subtotal, 2),
            discount: round($discount, 2),
            net: $net,
            iva: $iva,
            total: round($net + $iva, 2),
        );
    }

    /** Atajo para una sola línea (compra directa de un servicio). */
    public static function forLine(float $amount, bool $taxed, ?float $rate = null): self
    {
        return self::forLines([['amount' => $amount, 'taxed' => $taxed]], 0.0, $rate);
    }

    /** Tasa configurada en Ajustes, en tanto por uno. */
    public static function rate(): float
    {
        return (float) Setting::get('iva_percentage', 16) / 100;
    }
}
