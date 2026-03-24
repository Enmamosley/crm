<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'client_invoice_id',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }
}
