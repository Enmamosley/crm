<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'lead_id', 'name',
        'billing_type', 'legal_name', 'tax_id', 'tax_system', 'cfdi_use',
        'email', 'phone',
        'address_zip', 'address_street', 'address_exterior', 'address_interior',
        'address_neighborhood', 'address_city', 'address_municipality',
        'address_state', 'address_country',
        'facturapi_customer_id', 'twentyi_package_id', 'domain', 'domain_type', 'cosmotown_registered', 'portal_token', 'portal_active', 'notes',
    ];

    protected function casts(): array
    {
        return ['portal_active' => 'boolean', 'cosmotown_registered' => 'boolean'];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Client $client) {
            if (empty($client->portal_token)) {
                $client->portal_token = Str::uuid()->toString();
            }
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Servicios contratados (con o sin factura — p.ej. ventas por WhatsApp). */
    public function clientServices(): HasMany
    {
        return $this->hasMany(ClientService::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * ¿El cliente pagó un servicio que requiere dominio pero eligió
     * "decidir después"? (sin dominio, sin hosting creado, y con un
     * servicio u orden pagada que lo requiere).
     */
    public function awaitsDomain(): bool
    {
        if ($this->domain || $this->twentyi_package_id) {
            return false;
        }

        // Servicios asignados manualmente que requieren dominio
        if ($this->clientServices->contains(fn ($cs) => $cs->status === 'active' && $cs->service?->requires_domain)) {
            return true;
        }

        // Órdenes pagadas de compra directa/carrito cuyo servicio requiere dominio.
        // Match EXACTO contra el formato de las notas (no substring: "Hosting" no
        // debe coincidir con "Hosting Mantenimiento").
        $names = Service::where('requires_domain', true)->pluck('name');
        if ($names->isEmpty()) {
            return false;
        }

        foreach ($this->invoices as $order) {
            if (!$order->paid_at || $order->status === 'cancelled' || empty($order->notes)) {
                continue;
            }

            $notes = $order->notes;

            if (str_starts_with($notes, 'Compra directa: ')) {
                if ($names->contains(str_replace('Compra directa: ', '', $notes))) {
                    return true;
                }
            } elseif (str_starts_with($notes, 'Carrito: ')) {
                $items = collect(explode(',', str_replace('Carrito: ', '', $notes)))
                    ->map(fn ($n) => trim(preg_replace('/^\d+x\s*/', '', trim($n))));
                if ($items->intersect($names)->isNotEmpty()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ¿El cliente tiene contratado un servicio que habilita la gestión de
     * correos en el portal? Se determina por el flag `email_service` del
     * servicio (configurable por paquete en el panel), no por nombre.
     */
    public function hasEmailService(): bool
    {
        $emailServices = Service::where('email_service', true)->get(['id', 'name']);
        if ($emailServices->isEmpty()) {
            return false;
        }

        $emailServiceIds   = $emailServices->pluck('id');
        $emailServiceNames = $emailServices->pluck('name');

        // Camino 0: servicio asignado directamente al cliente (p.ej. venta por WhatsApp)
        if ($this->clientServices()
            ->where('status', 'active')
            ->whereIn('service_id', $emailServiceIds)
            ->exists()) {
            return true;
        }

        // Camino 1: orden pagada con cotización que incluye ítems de correo
        $viaQuote = $this->invoices()
            ->whereNotNull('paid_at')
            ->whereHas('quote.items', fn ($q) => $q->whereIn('service_id', $emailServiceIds))
            ->exists();

        if ($viaQuote) {
            return true;
        }

        // Camino 2: compra directa o carrito — match exacto contra las notas
        $paidNotes = $this->invoices()
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('notes')
            ->pluck('notes');

        foreach ($paidNotes as $notes) {
            if (str_starts_with($notes, 'Compra directa: ')) {
                if ($emailServiceNames->contains(str_replace('Compra directa: ', '', $notes))) {
                    return true;
                }
            } elseif (str_starts_with($notes, 'Carrito: ')) {
                $items = collect(explode(',', str_replace('Carrito: ', '', $notes)))
                    ->map(fn ($n) => trim(preg_replace('/^\d+x\s*/', '', trim($n))));
                if ($items->intersect($emailServiceNames)->isNotEmpty()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function portalUrl(): string
    {
        return url('/portal/' . $this->portal_token);
    }

    /** Construye el objeto address para FacturAPI */
    public function facturApiAddress(): array
    {
        return array_filter([
            'zip'          => $this->address_zip,
            'street'       => $this->address_street,
            'exterior'     => $this->address_exterior,
            'interior'     => $this->address_interior,
            'neighborhood' => $this->address_neighborhood,
            'city'         => $this->address_city,
            'municipality' => $this->address_municipality,
            'state'        => $this->address_state,
            'country'      => $this->address_country,
        ]);
    }
}
