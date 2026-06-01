<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value',
        'min_amount', 'max_uses', 'times_used',
        'valid_from', 'valid_until', 'active',
    ];

    protected function casts(): array
    {
        return [
            'value'      => 'decimal:2',
            'min_amount' => 'decimal:2',
            'active'     => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function isValid(float $subtotal = 0): bool
    {
        if (!$this->active) return false;
        if ($this->max_uses && $this->times_used >= $this->max_uses) return false;
        if ($this->valid_from && now()->lt($this->valid_from)) return false;
        if ($this->valid_until && now()->gt($this->valid_until)) return false;
        if ($this->min_amount && $subtotal < $this->min_amount) return false;
        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if (!$this->isValid($subtotal)) return 0;

        return $this->type === 'percentage'
            ? round($subtotal * ($this->value / 100), 2)
            : min($this->value, $subtotal);
    }

    /**
     * Consume un uso del cupón de forma atómica y sólo si no excede max_uses.
     * Debe llamarse únicamente cuando el pago queda CONFIRMADO (no al iniciar
     * un pago pendiente como OXXO/SPEI que podría no completarse nunca).
     */
    public static function consumeForCode(?string $code): void
    {
        if (!$code) {
            return;
        }

        static::where('code', $code)
            ->where(function ($q) {
                $q->whereNull('max_uses')->orWhereColumn('times_used', '<', 'max_uses');
            })
            ->increment('times_used');
    }
}
