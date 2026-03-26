<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringInvoiceItem extends Model
{
    protected $fillable = [
        'recurring_invoice_schedule_id',
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

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoiceSchedule::class, 'recurring_invoice_schedule_id');
    }
}
