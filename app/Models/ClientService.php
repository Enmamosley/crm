<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Servicio contratado por un cliente sin necesidad de orden/factura
 * (ventas por WhatsApp, acuerdos directos, cortesías).
 */
class ClientService extends Model
{
    public const STATUSES = ['active', 'suspended', 'cancelled'];

    public const STATUS_LABELS = [
        'active'    => 'Activo',
        'suspended' => 'Suspendido',
        'cancelled' => 'Cancelado',
    ];

    public const SOURCES = [
        'manual'   => 'Manual',
        'whatsapp' => 'WhatsApp',
        'order'    => 'Orden',
    ];

    protected $fillable = [
        'client_id', 'service_id', 'price', 'status',
        'started_at', 'expires_at', 'source', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'price'      => 'decimal:2',
            'started_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Precio pactado, o el de catálogo si no se definió uno. */
    public function effectivePrice(): float
    {
        return (float) ($this->price ?? $this->service?->price ?? 0);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && (!$this->expires_at || $this->expires_at->isFuture() || $this->expires_at->isToday());
    }
}
