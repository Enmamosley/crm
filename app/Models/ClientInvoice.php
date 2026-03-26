<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientInvoice extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'client_id', 'quote_id', 'series', 'folio_number',
        'payment_form', 'payment_method', 'use_cfdi',
        'status', 'facturapi_invoice_id', 'facturapi_data',
        'subtotal', 'iva_amount', 'total', 'notes',
        'billing_preference',
        'stamped_at', 'cancelled_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'facturapi_data' => 'array',
            'subtotal'       => 'decimal:2',
            'iva_amount'     => 'decimal:2',
            'total'          => 'decimal:2',
            'stamped_at'     => 'datetime',
            'cancelled_at'   => 'datetime',
            'paid_at'        => 'datetime',
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

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function isStamped(): bool
    {
        return $this->status === 'valid';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['valid', 'pending']);
    }

    public function isVoidable(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function folio(): string
    {
        return $this->series . ($this->folio_number ?? '');
    }
}
