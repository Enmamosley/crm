<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['user_id', 'permission'];

    public const AVAILABLE = [
        'leads.view_all'     => 'Ver todos los leads',
        'leads.view_own'     => 'Ver solo leads asignados',
        'leads.manage'       => 'Crear/editar/eliminar leads',
        'clients.view'       => 'Ver clientes',
        'clients.manage'     => 'Crear/editar/eliminar clientes',
        'quotes.view'        => 'Ver cotizaciones',
        'quotes.manage'      => 'Crear/editar/eliminar cotizaciones',
        'invoices.view'      => 'Ver facturas',
        'invoices.manage'    => 'Crear/timbrar/cancelar facturas',
        'reports.view'       => 'Ver reportes',
        'tickets.view_all'   => 'Ver todos los tickets',
        'tickets.view_own'   => 'Ver solo tickets asignados',
        'tickets.manage'     => 'Gestionar tickets de soporte',
        'settings.manage'    => 'Gestionar configuración',
    ];

    /**
     * Permisos que trae cada rol de fábrica.
     *
     * Un usuario sin permisos propios hereda los de su rol; en cuanto se le
     * marca alguno en el panel, esa lista pasa a mandar por completo (así el
     * admin puede recortar, por ejemplo dejando a un comercial en
     * `leads.view_own`). Los administradores no pasan por aquí: pueden todo.
     */
    public const ROLE_DEFAULTS = [
        'sales' => [
            'leads.view_all', 'leads.manage',
            'quotes.view', 'quotes.manage',
            'clients.view',
            'tickets.view_all', 'tickets.manage',
        ],
        'accounting' => [
            'clients.view', 'clients.manage',
            'quotes.view',
            'invoices.view', 'invoices.manage',
            'reports.view',
            'tickets.view_all',
        ],
    ];

    /** @return list<string> */
    public static function defaultsForRole(?string $role): array
    {
        return self::ROLE_DEFAULTS[$role] ?? [];
    }
}
