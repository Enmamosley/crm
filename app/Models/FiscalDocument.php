<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id', 'facturapi_invoice_id', 'facturapi_data',
        'status', 'cancellation_motive', 'stamped_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'facturapi_data' => 'array',
            'stamped_at'     => 'datetime',
            'cancelled_at'   => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function isCancellable(): bool
    {
        return $this->status === 'valid';
    }
}
