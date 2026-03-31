<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Quote;
use App\Services\DmChampService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DmChampService::class);
    }

    public function boot(): void
    {
        // Rate limiters
        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // ── DM Champ: sincronización automática ──────────────────────
        $dmchamp = fn () => app(DmChampService::class);

        // Lead creado → sincronizar como contacto en DM Champ
        Lead::created(function (Lead $lead) use ($dmchamp) {
            $dmchamp()->syncLead($lead);
        });

        // Cliente creado → sincronizar como contacto en DM Champ
        Client::created(function (Client $client) use ($dmchamp) {
            $dmchamp()->syncClient($client);
        });

        // Factura creada → notificar al cliente por WhatsApp
        Order::created(function (Order $order) use ($dmchamp) {
            $order->loadMissing('client');
            $dmchamp()->notifyInvoiceCreated($order);
        });

        // Cotización enviada (status cambia a 'sent') → notificar al lead
        Quote::updated(function (Quote $quote) use ($dmchamp) {
            if ($quote->wasChanged('status') && $quote->status === 'sent') {
                $quote->loadMissing('lead');
                $dmchamp()->notifyQuoteSent($quote);
            }
        });
    }
}

