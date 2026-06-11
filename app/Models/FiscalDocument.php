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
        'source', 'uuid', 'pdf_path', 'xml_path',
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

    /** CFDI timbrado fuera del CRM (archivos XML/PDF cargados manualmente). */
    public function isExternal(): bool
    {
        return $this->source === 'external';
    }

    public function isCancellable(): bool
    {
        return $this->status === 'valid';
    }
}
