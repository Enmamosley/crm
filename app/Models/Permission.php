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
}
