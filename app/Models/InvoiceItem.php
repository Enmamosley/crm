<?php

namespace App\Models;

use App\Models\Concerns\CausesIva;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use CausesIva;

    protected $fillable = [
        'order_id',
        'description',
        'sat_product_key',
        'sat_unit_key',
        'sat_unit_name',
        'tax_object',
        'iva_exempt',
        'quantity',
        'unit_price',
        'discount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'iva_exempt' => 'boolean',
            'unit_price' => 'decimal:2',
            'discount'   => 'decimal:2',
            'total'      => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
