<?php

namespace App\Models;

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
        'notes', 'status', 'paid_at',
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

    // ─── Helpers ────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
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
