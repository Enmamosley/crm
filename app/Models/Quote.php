<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

    public static function generateQuoteNumber(): string
    {
        $year = now()->format('Y');
        $last = static::whereYear('created_at', $year)->count();
        return sprintf('COT-%s-%04d', $year, $last + 1);
    }
}
