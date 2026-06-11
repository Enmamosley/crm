<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Servicios contratados por un cliente sin pasar por orden/factura
 * (ventas por WhatsApp, acuerdos directos).
 */
class ClientServiceController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'price'      => 'nullable|numeric|min:0',
            'status'     => ['nullable', Rule::in(ClientService::STATUSES)],
            'started_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:started_at',
            'source'     => ['nullable', Rule::in(array_keys(ClientService::SOURCES))],
            'notes'      => 'nullable|string|max:1000',
        ]);

        $cs = $client->clientServices()->create([
            'service_id' => $validated['service_id'],
            'price'      => $validated['price'] ?? null,
            'status'     => $validated['status'] ?? 'active',
            'started_at' => $validated['started_at'] ?? now()->toDateString(),
            'expires_at' => $validated['expires_at'] ?? null,
            'source'     => $validated['source'] ?? 'manual',
            'notes'      => $validated['notes'] ?? null,
        ]);

        ActivityLog::log('client_service_added', $client,
            "Servicio '{$cs->service->name}' asignado a {$client->legal_name} (origen: " . (ClientService::SOURCES[$cs->source] ?? $cs->source) . ")");

        return back()->with('success', 'Servicio asignado al cliente.');
    }

    public function update(Request $request, ClientService $clientService)
    {
        $validated = $request->validate([
            'status'     => ['required', Rule::in(ClientService::STATUSES)],
            'price'      => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $clientService->update($validated);

        ActivityLog::log('client_service_updated', $clientService->client,
            "Servicio '{$clientService->service->name}' actualizado a estado '" . (ClientService::STATUS_LABELS[$clientService->status] ?? $clientService->status) . "'");

        return back()->with('success', 'Servicio actualizado.');
    }

    public function destroy(ClientService $clientService)
    {
        $name   = $clientService->service->name;
        $client = $clientService->client;
        $clientService->delete();

        ActivityLog::log('client_service_removed', $client, "Servicio '{$name}' retirado de {$client->legal_name}");

        return back()->with('success', 'Servicio retirado del cliente.');
    }
}
