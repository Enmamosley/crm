<?php

namespace App\Console\Commands;

use App\Mail\PaymentReminder;
use App\Models\ActivityLog;
use App\Models\ClientInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminders extends Command
{
    protected $signature = 'invoices:send-reminders {--days=7 : Days after which to send reminder}';
    protected $description = 'Send payment reminder emails for unpaid invoices';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $invoices = ClientInvoice::with('client')
            ->whereNull('paid_at')
            ->where('status', 'valid')
            ->where('stamped_at', '<=', $cutoff)
            ->get();

        $sent = 0;
        foreach ($invoices as $invoice) {
            if (!$invoice->client->email) {
                continue;
            }

            try {
                Mail::to($invoice->client->email)->send(new PaymentReminder($invoice));
                $sent++;
                ActivityLog::log('reminder_sent', $invoice, "Recordatorio de pago enviado para factura {$invoice->folio()}");
            } catch (\Throwable $e) {
                Log::error('Payment reminder email failed', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $this->info("Se enviaron {$sent} recordatorios de pago.");
        return self::SUCCESS;
    }
}
