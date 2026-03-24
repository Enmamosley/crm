<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringInvoiceSchedule extends Model
{
    protected $fillable = [
        'client_id', 'quote_id', 'series', 'payment_form', 'payment_method',
        'use_cfdi', 'subtotal', 'iva_amount', 'total',
        'frequency', 'day_of_month', 'next_issue_date', 'end_date',
        'auto_stamp', 'active', 'notes', 'billing_preference',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'        => 'decimal:2',
            'iva_amount'      => 'decimal:2',
            'total'           => 'decimal:2',
            'next_issue_date' => 'date',
            'end_date'        => 'date',
            'auto_stamp'      => 'boolean',
            'active'          => 'boolean',
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

    public function isDue(): bool
    {
        return $this->active && $this->next_issue_date->lte(today())
            && ($this->end_date === null || $this->end_date->gte(today()));
    }

    public function advanceNextDate(): void
    {
        $next = match ($this->frequency) {
            'monthly'   => $this->next_issue_date->addMonth(),
            'quarterly' => $this->next_issue_date->addMonths(3),
            'yearly'    => $this->next_issue_date->addYear(),
            default     => $this->next_issue_date->addMonth(),
        };

        $this->update(['next_issue_date' => $next]);
    }
}
