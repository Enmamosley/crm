<?php

namespace App\Support;

/**
 * Alcance de los tokens de API.
 *
 * El token del agente se emitía con `createToken('openclaw-agent')` a secas.
 * Sanctum entiende esa ausencia de lista como el comodín `*`, así que el token
 * podía todo: el API entero y, sin volver a tocarlo, cualquier endpoint que se
 * añadiera después. Ahora cada token lleva escrito lo que puede hacer y las
 * rutas lo exigen con el middleware `abilities`.
 */
final class ApiAbilities
{
    public const AVAILABLE = [
        'leads:read'    => 'Consultar y buscar prospectos',
        'leads:write'   => 'Crear prospectos, cambiar su estado y dejarles notas',
        'quotes:read'   => 'Descargar el PDF de una cotización',
        'quotes:write'  => 'Crear cotizaciones y cambiar su estado',
        'agent:read'    => 'Consultar si el agente está pausado',
        'settings:read' => 'Leer los datos públicos del negocio',
    ];

    /**
     * Lo que necesita el agente OpenClaw: el API documentado de hoy, ni uno
     * más. Un endpoint nuevo exige emitir el token otra vez, que es justo lo
     * que el comodín se saltaba.
     *
     * @var list<string>
     */
    public const OPENCLAW = [
        'leads:read', 'leads:write',
        'quotes:read', 'quotes:write',
        'agent:read', 'settings:read',
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::AVAILABLE);
    }

    /**
     * Separa las que no existen, para no emitir un token con un permiso mal
     * escrito que luego no concede nada.
     *
     * @param  list<string>  $abilities
     * @return list<string>
     */
    public static function unknown(array $abilities): array
    {
        return array_values(array_diff($abilities, self::names()));
    }
}
