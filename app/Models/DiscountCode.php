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

    public function incrementUses(): void
    {
        $this->increment('times_used');
    }
}
