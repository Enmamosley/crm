<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id', 'quote_id', 'series', 'folio_number',
        'payment_form', 'payment_method', 'use_cfdi',
        'billing_preference', 'subtotal', 'iva_amount', 'total',
        'discount_code', 'notes', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'   => 'decimal:2',
            'iva_amount' => 'decimal:2',
            'total'      => 'decimal:2',
            'paid_at'    => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * El CFDI vigente de la orden.
     *
     * Una orden puede acumular varios documentos: al cancelar ante el SAT la
     * fila se conserva con status 'cancelled' (es el registro fiscal) y una
     * reexpedición añade otra. Sin `latestOfMany()` la relación devolvía el
     * primero por orden de inserción, es decir el cancelado, así que el panel
     * mostraba el documento equivocado, isStamped() seguía diciendo "no" y el
     * botón de timbrar permitía emitir CFDIs nuevos sin límite.
     */
    public function fiscalDocument(): HasOne
    {
        return $this->hasOne(FiscalDocument::class)->latestOfMany();
    }

    /** Recibos Electrónicos de Pago (CFDI tipo P) emitidos contra esta factura. */
    public function paymentComplements(): HasMany
    {
        return $this->hasMany(PaymentComplement::class);
    }

    /** Todos los CFDI de la orden, incluidos los cancelados. Para auditoría. */
    public function fiscalDocuments(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }

    /**
     * Siguiente folio de la serie.
     *
     * El SAT exige que serie+folio sea único por emisor, no por cliente. Se
     * cuentan también las órdenes borradas: su folio ya se emitió y no puede
     * reutilizarse.
     */
    public static function allocateFolio(string $series): int
    {
        return (int) static::withTrashed()->where('series', $series)->max('folio_number') + 1;
    }

    /**
     * Crea la orden asignándole folio de su serie. Dos altas simultáneas leen
     * el mismo folio libre y una choca contra el índice único: se reintenta
     * con el siguiente, que es más barato que bloquear la tabla.
     */
    public static function createWithFolio(array $attributes, int $attempts = 5): self
    {
        $series = $attributes['series'] ?? 'F';
        unset($attributes['folio_number']);

        for ($attempt = 1; ; $attempt++) {
            try {
                return static::create($attributes + ['folio_number' => static::allocateFolio($series)]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }
            }
        }
    }

    // ─── Scopes ─────────────────────────────────────────────

    /**
     * Órdenes que esperan pago: sin pagar, no canceladas y ya emitidas —
     * enviadas al cliente (sent/pending) o con un CFDI vigente (las
     * recurrentes auto-timbradas se quedan en draft). Los draft sin CFDI
     * son checkouts abandonados y no deben recibir cobranza.
     */
    public function scopeAwaitingPayment(Builder $query): Builder
    {
        return $query->whereNull('paid_at')
            ->where('status', '!=', 'cancelled')
            ->where(fn (Builder $q) => $q->whereIn('status', ['sent', 'pending'])
                ->orWhereHas('fiscalDocument', fn (Builder $f) => $f->where('status', 'valid')));
    }

    // ─── Helpers ────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->paid_at !== null || $this->status === 'paid';
    }

    /**
     * Fecha de emisión para los plazos de cobranza: el timbrado del CFDI o,
     * si la orden no se factura, su creación. Devuelve una copia porque
     * quien la usa suele encadenar addDays(), que muta la instancia.
     */
    public function issuedAt(): CarbonInterface
    {
        return ($this->fiscalDocument?->stamped_at ?? $this->created_at)->copy();
    }

    /**
     * Marca la orden como pagada, una sola vez. La condición viaja dentro del
     * UPDATE para que dos procesos simultáneos (webhook, captura y sondeo del
     * portal llegan a la vez) no puedan finalizar la misma orden dos veces:
     * sólo el primero recibe true.
     *
     * El `status` lo decide quien cobra: las pasarelas usan 'sent' y el panel
     * 'paid'. isPaid() cubre ambos.
     */
    public function markPaid(?\DateTimeInterface $paidAt = null, string $status = 'sent'): bool
    {
        $paidAt ??= now();

        $updated = static::whereKey($this->getKey())
            ->whereNull('paid_at')
            ->update(['paid_at' => $paidAt, 'status' => $status, 'updated_at' => now()]);

        if ($updated === 0) {
            return false;
        }

        $this->forceFill(['paid_at' => $paidAt, 'status' => $status])->syncOriginal();

        return true;
    }

    /** Pago en parcialidades o diferido: obliga a emitir complemento de pago. */
    public function isPpd(): bool
    {
        return ($this->payment_method ?: 'PUE') === 'PPD';
    }

    /**
     * ¿Falta por emitir el REP de algún pago? Sólo aplica a facturas PPD ya
     * timbradas: el SAT da hasta el día 5 del mes siguiente al pago.
     */
    public function owesPaymentComplement(): bool
    {
        if (!$this->isPpd() || !$this->isStamped()) {
            return false;
        }

        return $this->paymentComplements()
            ->where('status', '!=', 'valid')
            ->exists();
    }

    /** Hay un CFDI activo (timbrado y no cancelado). */
    public function isStamped(): bool
    {
        return $this->fiscalDocument?->status === 'valid';
    }

    /** El CFDI puede cancelarse ante el SAT. */
    public function isCancellable(): bool
    {
        return $this->fiscalDocument?->status === 'valid';
    }

    /** La orden puede anularse internamente (sin SAT). */
    public function isVoidable(): bool
    {
        return $this->status !== 'cancelled' && !$this->isStamped();
    }

    /**
     * Conceptos fiscales de la orden, normalizados. Mandan, por este orden:
     * las líneas de la cotización, los ítems capturados a mano en el panel y
     * —cuando no hay ninguno, que es el caso de las compras públicas— el
     * servicio anotado en las notas ("Compra directa: <servicio>").
     *
     * Los dos PAC derivaban sus conceptos por su cuenta y no coincidían:
     * Finkok tenía el respaldo de las notas y Facturapi no, así que una compra
     * directa se enviaba a timbrar literalmente sin conceptos.
     *
     * @return list<array{description: string, quantity: float, unit_price: float,
     *                    product_key: string, unit_key: string, unit_name: string,
     *                    tax_object: string, exempt: bool}>
     */
    public function fiscalLines(): array
    {
        $quote = $this->quote?->loadMissing('items.service');

        if ($quote && $quote->items->isNotEmpty()) {
            return $quote->items
                ->map(fn ($item) => self::lineFromService(
                    $item->service, $item->description, (float) $item->quantity, (float) $item->unit_price
                ))
                ->all();
        }

        $this->loadMissing('items');

        if ($this->items->isNotEmpty()) {
            return $this->items->map(fn (InvoiceItem $item) => [
                'description' => $item->description,
                'quantity'    => (float) $item->quantity,
                'unit_price'  => (float) $item->unit_price,
                'product_key' => $item->sat_product_key ?: '80101501',
                'unit_key'    => $item->sat_unit_key ?: 'E48',
                'unit_name'   => $item->sat_unit_name ?: 'Servicio',
                'tax_object'  => $item->tax_object ?: '02',
                'exempt'      => !$item->causesIva(),
            ])->all();
        }

        $description = trim(str_replace(['Compra directa: ', 'Carrito: '], '', $this->notes ?? ''))
            ?: 'Servicios profesionales';

        return [self::lineFromService(
            Service::where('name', $description)->first(),
            $description,
            1.0,
            (float) $this->subtotal,
        )];
    }

    /** @return array<string, mixed> */
    private static function lineFromService(?Service $service, string $description, float $quantity, float $unitPrice): array
    {
        return [
            'description' => $description,
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            'product_key' => $service?->sat_product_key ?: '80101501',
            'unit_key'    => $service?->sat_unit_key ?: 'E48',
            'unit_name'   => $service?->sat_unit_name ?: 'Servicio',
            'tax_object'  => $service?->tax_object ?: '02',
            'exempt'      => $service ? !$service->causesIva() : false,
        ];
    }

    /**
     * Nunca timbrar un total distinto al cobrado, ni por centavos. Lo vigilaba
     * sólo Finkok; con Facturapi una orden con servicios exentos se timbraba
     * por menos de lo que se había cobrado y nadie se enteraba.
     */
    public function assertChargedTotalMatches(float $cfdiTotal): void
    {
        if (abs($cfdiTotal - (float) $this->total) <= 0.01) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'El total del CFDI (%.2f) no coincide con el cobrado en la orden %s (%.2f). '
            . 'Causas comunes: una compra con servicios exentos y gravados mezclados, '
            . 'o un descuento repartido entre ambos. Revisa los conceptos antes de timbrar.',
            $cfdiTotal,
            $this->folio(),
            (float) $this->total
        ));
    }

    public function folio(): string
    {
        return $this->series . ($this->folio_number ?? '');
    }
}
