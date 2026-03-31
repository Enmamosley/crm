<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints para DM Champ Custom Functions.
 * Autenticación: token estático vía header X-DmChamp-Token o query ?token=
 * Configurar DMCHAMP_FUNCTION_TOKEN en .env
 */
class DmChampFunctionController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    //  1. Consultar estado de cuenta del cliente
    //     GET /api/v1/dmchamp/cliente?phone=+521234567890
    // ─────────────────────────────────────────────────────────────
    public function estadoCuenta(Request $request): JsonResponse
    {
        $phone = $this->normalizePhone($request->input('phone', ''));

        if (! $phone) {
            return $this->error('Necesito tu número de teléfono para buscar tu cuenta.');
        }

        // Buscar por cliente o lead
        $client = Client::where('phone', $phone)
            ->orWhere('phone', $request->input('phone'))
            ->first();

        if (! $client) {
            return response()->json([
                'encontrado'  => false,
                'mensaje'     => 'No encontré una cuenta registrada con ese número. Si eres cliente nuevo, con gusto te atiendo.',
            ]);
        }

        $pendientes = Order::where('client_id', $client->id)
            ->whereNull('paid_at')
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->get(['id', 'series', 'folio_number', 'total', 'status', 'created_at']);

        $pagadas = Order::where('client_id', $client->id)
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->limit(3)
            ->get(['id', 'series', 'folio_number', 'total', 'paid_at']);

        $totalPendiente = $pendientes->sum('total');

        return response()->json([
            'encontrado'        => true,
            'nombre'            => $client->name ?: $client->legal_name,
            'email'             => $client->email,
            'facturas_pendientes' => $pendientes->count(),
            'total_pendiente'   => '$' . number_format($totalPendiente, 2),
            'detalle_pendientes' => $pendientes->map(fn($o) => [
                'folio'  => $o->series . ($o->folio_number ?? ''),
                'total'  => '$' . number_format($o->total, 2),
                'fecha'  => $o->created_at->format('d/m/Y'),
            ])->values(),
            'ultimos_pagos'     => $pagadas->map(fn($o) => [
                'folio'     => $o->series . ($o->folio_number ?? ''),
                'total'     => '$' . number_format($o->total, 2),
                'pagado_el' => $o->paid_at->format('d/m/Y'),
            ])->values(),
            'mensaje' => $pendientes->count() > 0
                ? "Tienes {$pendientes->count()} factura(s) pendiente(s) por un total de \${$totalPendiente}."
                : 'Estás al corriente, no tienes facturas pendientes.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  2. Crear un lead desde el chat de DM Champ
    //     POST /api/v1/dmchamp/lead
    // ─────────────────────────────────────────────────────────────
    public function crearLead(Request $request): JsonResponse
    {
        $name    = trim($request->input('name', ''));
        $phone   = $this->normalizePhone($request->input('phone', ''));
        $email   = $request->input('email');
        $service = $request->input('service');
        $notes   = $request->input('notes');

        if (! $name && ! $phone) {
            return $this->error('Necesito al menos el nombre o teléfono del prospecto.');
        }

        // Evitar duplicados por teléfono
        if ($phone) {
            $existing = Lead::where('phone', $phone)
                ->orWhere('phone', $request->input('phone'))
                ->first();

            if ($existing) {
                return response()->json([
                    'creado'  => false,
                    'lead_id' => $existing->id,
                    'mensaje' => "Ya tenemos registrado a {$existing->name} con ese número. Le daremos seguimiento.",
                ]);
            }
        }

        $lead = Lead::create([
            'name'                => $name ?: 'Prospecto WhatsApp',
            'phone'               => $phone ?: $request->input('phone'),
            'email'               => $email,
            'project_description' => $service ? "Interesado en: {$service}" . ($notes ? ". {$notes}" : '') : $notes,
            'source'              => 'dmchamp',
            'status'              => 'nuevo',
        ]);

        $lead->statusHistory()->create([
            'old_status' => null,
            'new_status' => 'nuevo',
            'changed_by' => 'dmchamp',
        ]);

        return response()->json([
            'creado'  => true,
            'lead_id' => $lead->id,
            'mensaje' => "Perfecto, {$lead->name}. Hemos registrado tu interés y un asesor te contactará a la brevedad.",
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────
    //  3. Consultar servicios y precios
    //     GET /api/v1/dmchamp/servicios?categoria=hosting
    // ─────────────────────────────────────────────────────────────
    public function servicios(Request $request): JsonResponse
    {
        $query = Service::where('active', true)
            ->where('public', true)
            ->with('category:id,name');

        if ($request->filled('categoria')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->input('categoria') . '%');
            });
        }

        if ($request->filled('buscar')) {
            $term = $request->input('buscar');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $services = $query->orderBy('price')->get(['id', 'name', 'description', 'price', 'service_category_id', 'info_url']);

        if ($services->isEmpty()) {
            return response()->json([
                'encontrados' => 0,
                'mensaje'     => 'No encontré servicios con ese criterio. Puedo mostrarte todo el catálogo si lo deseas.',
                'servicios'   => [],
            ]);
        }

        return response()->json([
            'encontrados' => $services->count(),
            'servicios'   => $services->map(fn($s) => [
                'nombre'      => $s->name,
                'descripcion' => $s->description,
                'precio'      => '$' . number_format($s->price, 2) . ' MXN',
                'categoria'   => $s->category?->name,
                'mas_info'    => $s->info_url,
            ])->values(),
            'mensaje' => "Encontré {$services->count()} servicio(s) disponible(s).",
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────
    private function error(string $message): JsonResponse
    {
        return response()->json(['error' => true, 'mensaje' => $message], 422);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10) {
            $digits = '52' . $digits;
        }
        return $digits ? '+' . ltrim($digits, '+') : '';
    }
}
