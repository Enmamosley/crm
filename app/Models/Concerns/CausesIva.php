<?php

namespace App\Models\Concerns;

/**
 * Regla fiscal compartida por todo lo que se vende y se factura: servicios,
 * ítems de una orden e ítems de una programación recurrente. Las tres tablas
 * llevan las mismas dos columnas y la misma regla, así que vive en un solo
 * sitio para que no se separen.
 */
trait CausesIva
{
    /**
     * ¿Causa IVA esta línea? No lo causa ni lo exento (`iva_exempt`) ni lo que
     * está fuera del objeto del impuesto (`tax_object` = '01').
     */
    public function causesIva(): bool
    {
        return !$this->iva_exempt && ($this->tax_object ?: '02') !== '01';
    }
}
