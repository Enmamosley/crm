<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
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
        'total',
    ];

    protected function casts(): array
    {
        return ['iva_exempt' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
