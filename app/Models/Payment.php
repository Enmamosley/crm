<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'mp_payment_id',
        'amount', 'currency', 'status', 'status_detail',
        'payment_type', 'payment_method_id', 'mp_data',
        'proof_path', 'payment_notes', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'  => 'decimal:2',
            'mp_data' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'in_process']);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Mapea el tipo de pago de MP al código de forma de pago SAT.
     */
    public function satPaymentForm(): string
    {
        return match ($this->payment_type) {
            'credit_card'   => '04',
            'debit_card'    => '28',
            'bank_transfer' => '03',
            'ticket'        => '01',
            default         => '99',
        };
    }

    /**
     * URL del ticket/voucher para pagos offline (OXXO, SPEI).
     */
    public function ticketUrl(): ?string
    {
        return $this->mp_data['transaction_details']['external_resource_url'] ?? null;
    }
}
