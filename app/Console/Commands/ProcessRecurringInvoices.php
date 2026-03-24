<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\ClientInvoice;
use App\Models\RecurringInvoiceSchedule;
use App\Services\FacturapiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessRecurringInvoices extends Command
{
    protected $signature = 'invoices:process-recurring';
    protected $description = 'Generate invoices from active recurring schedules';

    public function handle(): int
    {
        $schedules = RecurringInvoiceSchedule::with(['client', 'quote'])
            ->where('active', true)
            ->where('next_issue_date', '<=', today())
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', today()))
            ->get();

        $created = 0;
        foreach ($schedules as $schedule) {
            try {
                $nextFolio = ClientInvoice::where('client_id', $schedule->client_id)
                    ->where('series', $schedule->series)
                    ->max('folio_number');

                $invoice = ClientInvoice::create([
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

                if ($schedule->auto_stamp && \App\Models\Setting::get('facturapi_api_key')) {
                    try {
                        (new FacturapiService())->stampInvoice($invoice);
                    } catch (\Throwable $e) {
                        Log::error('Auto-stamp recurring invoice failed', [
                            'invoice_id'  => $invoice->id,
                            'schedule_id' => $schedule->id,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }

                $schedule->advanceNextDate();
                $created++;

                ActivityLog::log('recurring_invoice_created', $invoice, "Factura recurrente generada: {$invoice->folio()} para {$schedule->client->legal_name}");
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
