<?php

namespace App\Console\Commands;

use App\Mail\PaymentReminder;
use App\Models\ActivityLog;
use App\Models\Order;
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

        // La fecha de emisión vive en el CFDI (fiscal_documents.stamped_at) o,
        // sin CFDI, en la creación de la orden. Se prefiltra por created_at
        // porque siempre es anterior o igual al timbrado.
        $invoices = Order::with(['client', 'fiscalDocument'])
            ->awaitingPayment()
            ->where('created_at', '<=', $cutoff)
            ->get()
            ->filter(fn (Order $order) => $order->issuedAt()->lte($cutoff));

        $sent = 0;
        foreach ($invoices as $order) {
            if (!$order->client?->email) {
                continue;
            }

            try {
                Mail::to($order->client->email)->send(new PaymentReminder($order));
                $sent++;
                ActivityLog::log('reminder_sent', $order, "Recordatorio de pago enviado para factura {$order->folio()}");
            } catch (\Throwable $e) {
                Log::error('Payment reminder email failed', [
                    'invoice_id' => $order->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $this->info("Se enviaron {$sent} recordatorios de pago.");
        return self::SUCCESS;
    }
}
