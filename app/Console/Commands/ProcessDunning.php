<?php

namespace App\Console\Commands;

use App\Mail\PaymentReminder;
use App\Models\ActivityLog;
use App\Models\ClientInvoice;
use App\Models\DunningAttempt;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessDunning extends Command
{
    protected $signature = 'dunning:process';
    protected $description = 'Procesa reintentos de cobro para facturas vencidas no pagadas';

    // Días después de emisión para cada intento
    private const SCHEDULE = [3, 7, 14, 30];

    public function handle(): void
    {
        $unpaid = ClientInvoice::whereNull('paid_at')
            ->where('status', 'valid')
            ->where('stamped_at', '<=', now()->subDays(3))
            ->with('client')
            ->get();

        $processed = 0;

        foreach ($unpaid as $invoice) {
            $attempts = DunningAttempt::where('client_invoice_id', $invoice->id)->count();

            if ($attempts >= count(self::SCHEDULE)) {
                continue; // Max attempts reached
            }

            $nextAttemptDays = self::SCHEDULE[$attempts] ?? null;
            if (!$nextAttemptDays) continue;

            $dueDate = $invoice->stamped_at->addDays($nextAttemptDays);
            if (now()->lt($dueDate)) continue;

            // Check if this attempt was already created
            $existing = DunningAttempt::where('client_invoice_id', $invoice->id)
                ->where('attempt_number', $attempts + 1)
                ->exists();
            if ($existing) continue;

            $attempt = DunningAttempt::create([
                'client_invoice_id' => $invoice->id,
                'attempt_number'    => $attempts + 1,
                'status'            => 'pending',
                'scheduled_at'      => now(),
            ]);

            // Send reminder email
            if ($invoice->client && $invoice->client->email) {
                try {
                    Mail::to($invoice->client->email)->send(new PaymentReminder($invoice));
                    $attempt->update(['status' => 'sent', 'sent_at' => now()]);
                    $processed++;

                    ActivityLog::log('dunning_sent', $invoice,
                        "Recordatorio #{$attempt->attempt_number} enviado para factura {$invoice->folio()}");

                    // Notify admins
                    $admins = User::where('role', 'admin')->pluck('id');
                    foreach ($admins as $adminId) {
                        Notification::notify($adminId, 'dunning_sent',
                            "Cobro #{$attempt->attempt_number}: {$invoice->folio()}",
                            "Se envió recordatorio de cobro a {$invoice->client->legal_name}",
                            route('admin.invoices.show', $invoice));
                    }
                } catch (\Throwable $e) {
                    $attempt->update(['status' => 'failed', 'notes' => $e->getMessage()]);
                    Log::error('Dunning email failed', [
                        'invoice_id' => $invoice->id,
                        'attempt'    => $attempt->attempt_number,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Dunning: {$processed} recordatorios enviados.");
    }
}
