<?php

namespace App\Console\Commands;

use App\Mail\PaymentReminder;
use App\Models\ActivityLog;
use App\Models\Order;
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
        $unpaid = Order::whereNull('paid_at')
            ->where('status', 'valid')
            ->where('stamped_at', '<=', now()->subDays(3))
            ->with('client')
            ->get();

        $processed = 0;

        foreach ($unpaid as $order) {
            $attempts = DunningAttempt::where('order_id', $order->id)->count();

            if ($attempts >= count(self::SCHEDULE)) {
                continue; // Max attempts reached
            }

            $nextAttemptDays = self::SCHEDULE[$attempts] ?? null;
            if (!$nextAttemptDays) continue;

            $dueDate = $order->stamped_at->addDays($nextAttemptDays);
            if (now()->lt($dueDate)) continue;

            // Check if this attempt was already created
            $existing = DunningAttempt::where('order_id', $order->id)
                ->where('attempt_number', $attempts + 1)
                ->exists();
            if ($existing) continue;

            $attempt = DunningAttempt::create([
                'order_id' => $order->id,
                'attempt_number'    => $attempts + 1,
                'status'            => 'pending',
                'scheduled_at'      => now(),
            ]);

            // Send reminder email
            if ($order->client && $order->client->email) {
                try {
                    Mail::to($order->client->email)->send(new PaymentReminder($order));
                    $attempt->update(['status' => 'sent', 'sent_at' => now()]);
                    $processed++;

                    ActivityLog::log('dunning_sent', $order,
                        "Recordatorio #{$attempt->attempt_number} enviado para factura {$order->folio()}");

                    // Notify admins
                    $admins = User::where('role', 'admin')->pluck('id');
                    foreach ($admins as $adminId) {
                        Notification::notify($adminId, 'dunning_sent',
                            "Cobro #{$attempt->attempt_number}: {$order->folio()}",
                            "Se envió recordatorio de cobro a {$order->client->legal_name}",
                            route('admin.invoices.show', $order));
                    }
                } catch (\Throwable $e) {
                    $attempt->update(['status' => 'failed', 'notes' => $e->getMessage()]);
                    Log::error('Dunning email failed', [
                        'invoice_id' => $order->id,
                        'attempt'    => $attempt->attempt_number,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Dunning: {$processed} recordatorios enviados.");
    }
}
