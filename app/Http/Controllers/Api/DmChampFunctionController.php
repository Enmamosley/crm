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
        // Aceptar phone desde query string O body JSON
        $phone = $request->input('phone') 
            ?? $request->query('phone') 
            ?? '';

        if (!$phone) {
            return $this->error('Necesito tu número de teléfono para buscar tu cuenta.');
        }

        $phone = $this->normalizePhone($phone);

        $rawPhone = $request->input('phone');
        $client = Client::where(function ($q) use ($phone, $rawPhone) {
            $q->where('phone', $phone);
            if ($rawPhone !== $phone) {
                $q->orWhere('phone', $rawPhone);
            }
        })->first();

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
        // Aceptar parámetros desde body JSON O query string
        $name    = trim($request->input('name') ?? $request->query('name') ?? '');
        $phone   = $request->input('phone') ?? $request->query('phone') ?? '';
        $email   = $request->input('email') ?? $request->query('email') ?? '';
        $service = $request->input('service') ?? $request->query('service') ?? '';
        $notes   = $request->input('notes') ?? $request->query('notes') ?? '';

        if (!$name && !$phone) {
            return $this->error('Necesito al menos el nombre o teléfono del prospecto.');
        }

        if ($phone) {
            $phone = $this->normalizePhone($phone);
        }

        // Verificar si ya es cliente
        if ($phone) {
            $existingClient = Client::where('phone', $phone)->first();
            if ($existingClient) {
                return response()->json([
                    'creado'  => false,
                    'mensaje' => "{$existingClient->name} ya es cliente registrado. Le daremos seguimiento.",
                ]);
            }

            $existing = Lead::where('phone', $phone)->first();
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
            'phone'               => $phone,
            'email'               => $email ?: null,
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
    //     GET /api/v1/dmchamp/servicios?buscar=hosting
    //     POST /api/v1/dmchamp/servicios (con {"buscar":"hosting"})
    // ─────────────────────────────────────────────────────────────
    public function servicios(Request $request): JsonResponse
    {
        // Aceptar parámetro de búsqueda desde query string O body JSON
        $buscar = $request->input('buscar') 
            ?? $request->query('buscar') 
            ?? '';

        $query = Service::where('active', true)
            ->where('public', true)
            ->with('category:id,name');

        if (!empty($buscar)) {
            $buscar = trim($buscar);
            
            // Extraer palabras significativas (ignorar palabras muy cortas como "de", "que", "tienes")
            $palabras = preg_split('/\s+/', strtolower($buscar), -1, PREG_SPLIT_NO_EMPTY);
            $palabras = array_filter($palabras, fn($p) => strlen($p) > 2); // Solo palabras > 2 caracteres
            
            if (!empty($palabras)) {
                // Buscar por cualquiera de las palabras encontradas
                $query->where(function ($q) use ($palabras) {
                    foreach ($palabras as $palabra) {
                        $escaped = str_replace(['%', '_'], ['\%', '\_'], $palabra);
                        $q->orWhere('name', 'like', "%{$escaped}%")
                          ->orWhere('description', 'like', "%{$escaped}%")
                          ->orWhereHas('category', function ($catQ) use ($escaped) {
                              $catQ->where('name', 'like', "%{$escaped}%");
                          });
                    }
                });
            } else {
                // Si no hay palabras significativas, devolver todos
                // (ej: "de qué que" no encuentra nada, entonces devuelve todo)
            }
        }

        $services = $query->orderBy('price')->get(['id', 'name', 'description', 'price', 'service_category_id', 'info_url']);

        if ($services->isEmpty() && !empty($buscar)) {
            // Query fresca sin filtros de búsqueda para mostrar catálogo completo
            $todos = Service::where('active', true)
                ->where('public', true)
                ->with('category:id,name')
                ->orderBy('price')
                ->get();

            return response()->json([
                'encontrados' => 0,
                'mensaje'     => 'No encontré servicios con ese criterio. Aquí está el catálogo completo:',
                'servicios'   => $todos->map(fn($s) => $this->formatService($s))->values(),
            ]);
        }

        return response()->json([
            'encontrados' => $services->count(),
            'servicios'   => $services->map(fn($s) => $this->formatService($s))->values(),
            'mensaje' => "Encontré {$services->count()} servicio(s) disponible(s).",
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────
    private function formatService(Service $s): array
    {
        return [
            'nombre'        => $s->name,
            'descripcion'   => $s->description,
            'precio'        => '$' . number_format($s->price, 2) . ' MXN',
            'precio_con_iva'=> '$' . number_format($s->priceWithIva(), 2) . ' MXN',
            'categoria'     => $s->category?->name,
            'mas_info'      => $s->info_url,
            'comprar'       => $s->slug ? $s->publicUrl() : null,
        ];
    }

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
