<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Support\Facades\Log;

/**
 * Punto único de aprovisionamiento tras un pago CONFIRMADO.
 * Idempotente: no registra el dominio ni crea el hosting dos veces.
 * Debe llamarse sólo cuando el pago está aprobado (síncrono para tarjeta/PayPal,
 * vía webhook para OXXO/SPEI, al confirmar para transferencias).
 */
class ProvisioningService
{
    public function provisionForOrder(Order $order): void
    {
        $client = $order->client;
        if (!$client || !$client->domain) {
            return;
        }

        // 1. Registro de dominio en Cosmotown (idempotente vía cosmotown_registered)
        if ($client->domain_type === 'cosmotown' && !$client->cosmotown_registered) {
            try {
                $cosmotown = new CosmotownService();
                if ($cosmotown->isConfigured()) {
                    $cosmotown->register($client->domain);
                    $client->update(['cosmotown_registered' => true]);
                    ActivityLog::log('domain_registered', $client, "Dominio {$client->domain} registrado en Cosmotown");
                }
            } catch (\Throwable $e) {
                Log::error('Provisioning: registro de dominio falló', [
                    'domain' => $client->domain, 'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. Hosting 20i (idempotente vía twentyi_package_id)
        if (!$client->twentyi_package_id) {
            $service = $this->resolveHostingService($order);
            if ($service && $service->twentyi_package_bundle_id) {
                try {
                    $packageId = (new TwentyIService())->createHostingPackage($client->domain, $service->twentyi_package_bundle_id);
                    $client->update(['twentyi_package_id' => $packageId]);
                    ActivityLog::log('hosting_provisioned', $client, "Hosting 20i creado: paquete #{$packageId} para {$client->domain}");
                } catch (\Throwable $e) {
                    Log::error('Provisioning: hosting 20i falló', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * Resuelve el servicio de hosting a partir de la orden. Las compras públicas
     * no guardan items de orden, así que el servicio se codifica en las notas
     * ("Compra directa: <servicio>" o "Carrito: 1x A, 2x B").
     */
    private function resolveHostingService(Order $order): ?Service
    {
        $notes = $order->notes ?? '';

        if (str_starts_with($notes, 'Compra directa: ')) {
            return Service::where('name', str_replace('Compra directa: ', '', $notes))->first();
        }

        if (str_starts_with($notes, 'Carrito: ')) {
            $names = collect(explode(',', str_replace('Carrito: ', '', $notes)))
                ->map(fn ($n) => trim(preg_replace('/^\d+x\s*/', '', $n)))
                ->filter()
                ->all();

            return Service::whereIn('name', $names)
                ->whereNotNull('twentyi_package_bundle_id')
                ->first();
        }

        return null;
    }
}
