<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\RecurringInvoiceSchedule;
use App\Services\InvoicingManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessRecurringInvoices extends Command
{
    protected $signature = 'invoices:process-recurring';
    protected $description = 'Generate invoices from active recurring schedules';

    public function handle(InvoicingManager $invoicing): int
    {
        $schedules = RecurringInvoiceSchedule::with(['client', 'quote', 'items'])
            ->where('active', true)
            ->where('next_issue_date', '<=', today())
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', today()))
            ->get();

        $created = 0;
        foreach ($schedules as $schedule) {
            try {
                $nextFolio = Order::where('client_id', $schedule->client_id)
                    ->where('series', $schedule->series)
                    ->max('folio_number');

                $order = Order::create([
                    'client_id'          => $schedule->client_id,
                    'quote_id'           => $schedule->quote_id,
                    'series'             => $schedule->series,
                    'folio_number'       => ($nextFolio ?? 0) + 1,
                    'payment_form'       => $schedule->payment_form,
                    'payment_method'     => $schedule->payment_method,
                    'use_cfdi'           => $schedule->use_cfdi,
                    'subtotal'           => $schedule->subtotal,
                    'iva_amount'         => $schedule->iva_amount,
                    'total'              => $schedule->total,
                    'billing_preference' => $schedule->billing_preference ?? 'fiscal',
                    'notes'              => "Generada automáticamente desde programación recurrente #{$schedule->id}",
                    'status'             => 'draft',
                ]);

                // Copy line items from the schedule
                foreach ($schedule->items as $item) {
                    $order->items()->create([
                        'description'     => $item->description,
                        'sat_product_key' => $item->sat_product_key,
                        'sat_unit_key'    => $item->sat_unit_key,
                        'sat_unit_name'   => $item->sat_unit_name,
                        'tax_object'      => $item->tax_object,
                        'iva_exempt'      => $item->iva_exempt,
                        'quantity'        => $item->quantity,
                        'unit_price'      => $item->unit_price,
                        'total'           => $item->total,
                    ]);
                }

                // El timbrado se enruta SIEMPRE por InvoicingManager: con
                // invoicing_provider=finkok este bloque antes no timbraba nada
                // (o timbraba con el PAC equivocado si quedaba una key vieja).
                if ($schedule->auto_stamp && $invoicing->isConfigured()) {
                    try {
                        $result = $invoicing->stampInvoice($order);
                        if (!($result['success'] ?? false)) {
                            Log::error('Auto-stamp recurring invoice rejected', [
                                'order_id'    => $order->id,
                                'schedule_id' => $schedule->id,
                                'provider'    => $invoicing->provider(),
                                'data'        => $result['data'] ?? null,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('Auto-stamp recurring invoice failed', [
                            'order_id'    => $order->id,
                            'schedule_id' => $schedule->id,
                            'provider'    => $invoicing->provider(),
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }

                $schedule->advanceNextDate();
                $created++;

                ActivityLog::log('recurring_invoice_created', $order, "Factura recurrente generada: {$order->folio()} para {$schedule->client->legal_name}");
            } catch (\Throwable $e) {
                Log::error('Recurring invoice processing failed', [
                    'schedule_id' => $schedule->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->info("Se generaron {$created} facturas recurrentes.");
        return self::SUCCESS;
    }
}
