<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Quote;
use Illuminate\Console\Command;

class ExpireQuotes extends Command
{
    protected $signature = 'quotes:expire';
    protected $description = 'Mark quotes past their valid_until date as expired';

    public function handle(): int
    {
        $expired = Quote::where('status', 'enviada')
            ->whereDate('valid_until', '<', today())
            ->get();

        $count = 0;
        foreach ($expired as $quote) {
            $quote->update(['status' => 'vencida']);
            ActivityLog::log('quote_expired', $quote, "Cotización {$quote->quote_number} venció automáticamente");
            $count++;
        }

        $this->info("Se marcaron {$count} cotizaciones como vencidas.");
        return self::SUCCESS;
    }
}
