<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    public function fiscalDocument(): HasOne
    {
        return $this->hasOne(FiscalDocument::class);
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

    public function folio(): string
    {
        return $this->series . ($this->folio_number ?? '');
    }
}
