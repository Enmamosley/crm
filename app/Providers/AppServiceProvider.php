<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Quote;
use App\Models\User;
use App\Services\CfdiBuilderService;
use App\Services\CosmotownService;
use App\Services\DmChampService;
use App\Services\FacturapiService;
use App\Services\FinkokService;
use App\Services\InvoicingManager;
use App\Services\MercadoPagoService;
use App\Services\MetaConversionsService;
use App\Services\OrderFinalizationService;
use App\Services\PayPalService;
use App\Services\ProvisioningService;
use App\Services\TwentyIService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Servicios de integración. Van como `scoped` y no como `singleton` porque
     * leen su configuración de la tabla `settings` al construirse: scoped se
     * vacía en cada petición y en cada job de la cola, así que un cambio en
     * Ajustes se ve sin reiniciar el worker.
     */
    private const INTEGRATIONS = [
        CfdiBuilderService::class,
        CosmotownService::class,
        FacturapiService::class,
        FinkokService::class,
        InvoicingManager::class,
        MercadoPagoService::class,
        MetaConversionsService::class,
        OrderFinalizationService::class,
        PayPalService::class,
        ProvisioningService::class,
        TwentyIService::class,
    ];

    public function register(): void
    {
        $this->app->singleton(DmChampService::class);

        foreach (self::INTEGRATIONS as $service) {
            $this->app->scoped($service);
        }
    }

    public function boot(): void
    {
        // Los permisos del panel dejan de ser decorativos: cada uno se registra
        // como Gate para poder exigirlo con el middleware `can:` en las rutas.
        foreach (array_keys(Permission::AVAILABLE) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // Recursos con visibilidad graduable: la puerta de la ruta sólo pregunta
        // si puede ver algo; el controlador decide cuánto.
        foreach (['leads', 'tickets'] as $resource) {
            Gate::define("{$resource}.view", fn (User $user) => $user->canViewAny($resource));
        }

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

