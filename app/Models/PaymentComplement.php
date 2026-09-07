<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recibo Electrónico de Pago (CFDI tipo P) de un pago contra una factura PPD.
 */
class PaymentComplement extends Model
{
    protected $fillable = [
        'order_id', 'payment_id', 'source', 'provider_id', 'uuid',
        'status', 'amount', 'installment', 'data', 'error', 'xml_path', 'stamped_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:2',
            'data'       => 'array',
            'stamped_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isStamped(): bool
    {
        return $this->status === 'valid';
    }

    /**
     * Saldo insoluto de la factura antes de este pago, que es lo que el SAT
     * llama ImpSaldoAnt en el documento relacionado.
     */
    public function previousBalance(): float
    {
        $settled = (float) $this->order->paymentComplements()
            ->where('status', 'valid')
            ->where('id', '!=', $this->id)
            ->where('installment', '<', $this->installment)
            ->sum('amount');

        return round((float) $this->order->total - $settled, 2);
    }

    /**
     * Deja constancia del rechazo en lugar de perderlo: el panel muestra los
     * complementos pendientes y fallidos de cada factura PPD.
     *
     * @param  array<string, mixed>|null  $data
     * @return array{success: bool, data: array<string, mixed>}
     */
    public function markFailed(string $error, ?array $data = null): array
    {
        $this->update(['status' => 'failed', 'error' => $error, 'data' => $data]);

        \Illuminate\Support\Facades\Log::error('Complemento de pago no emitido', [
            'order_id'   => $this->order_id,
            'payment_id' => $this->payment_id,
            'error'      => $error,
        ]);

        return ['success' => false, 'data' => ['message' => $error]];
    }
}
