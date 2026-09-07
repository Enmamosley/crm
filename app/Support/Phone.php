<?php

namespace App\Support;

/**
 * Normalización de teléfonos a E.164.
 *
 * Los números entran por cuatro puertas (panel, API del agente, funciones de
 * DM Champ y webhook de DM Champ) en formatos distintos: "55 1234 5678",
 * "5512345678", "525512345678", "+525512345678". Las búsquedas son por
 * igualdad exacta, así que sin un formato único el mismo contacto se duplica
 * y las búsquedas por teléfono no lo encuentran.
 */
class Phone
{
    /** Devuelve el teléfono en formato +52XXXXXXXXXX, o '' si no hay dígitos. */
    public static function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return '';
        }

        // Diez dígitos: número nacional mexicano sin lada de país.
        if (strlen($digits) === 10) {
            $digits = '52' . $digits;
        }

        return '+' . $digits;
    }
}
