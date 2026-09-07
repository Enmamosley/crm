<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Order;

class Quote extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'quote_number', 'lead_id', 'subtotal', 'iva_percentage',
        'iva_amount', 'total', 'status', 'valid_until', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'iva_percentage' => 'decimal:2',
            'iva_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'valid_until' => 'date',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function recalculate(): void
    {
        $subtotal = $this->items()->sum('total');
        $ivaAmount = round($subtotal * ($this->iva_percentage / 100), 2);

        $this->update([
            'subtotal' => $subtotal,
            'iva_amount' => $ivaAmount,
            'total' => $subtotal + $ivaAmount,
        ]);
    }

    public function isExpired(): bool
    {
        return $this->valid_until->isPast();
    }

    /**
     * Siguiente folio del año en curso.
     *
     * Se calcula sobre el folio más alto ya emitido, incluidos los borrados:
     * contar filas daba un número ya usado en cuanto se borraba una cotización
     * (el borrado es lógico, el índice único no), y a partir de ahí toda
     * creación fallaba por clave duplicada durante el resto del año.
     */
    public static function generateQuoteNumber(): string
    {
        $prefix = 'COT-' . now()->format('Y') . '-';

        // El folio va con ceros a la izquierda, así que el orden alfabético
        // coincide con el numérico.
        $last = static::withTrashed()
            ->where('quote_number', 'like', $prefix . '%')
            ->max('quote_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return sprintf('%s%04d', $prefix, $next);
    }

    /**
     * Crea la cotización asignándole folio. Dos altas simultáneas leen el mismo
     * folio libre y una choca contra el índice único: en ese caso se reintenta
     * con el siguiente, que es más simple y barato que bloquear la tabla.
     */
    public static function createWithNumber(array $attributes, int $attempts = 5): self
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return static::create($attributes + ['quote_number' => static::generateQuoteNumber()]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }
            }
        }
    }
}
