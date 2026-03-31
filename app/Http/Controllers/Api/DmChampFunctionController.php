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
 *
 * DM Champ envía automáticamente un campo "system" con cada request:
 *   system.contact_id, system.contact_phone, system.contact_email,
 *   system.contact_name, system.campaign_id, system.is_test
 *
 * Autenticación: header X-DmChamp-Token
 */
class DmChampFunctionController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    //  1. Estado de cuenta — el bot la llama automáticamente
    //     POST /api/v1/dmchamp/cliente
    //     No requiere input del usuario (usa system.contact_phone)
    // ─────────────────────────────────────────────────────────────
    public function estadoCuenta(Request $request): JsonResponse
    {
        $phone = $this->extractPhone($request);

        if (!$phone) {
            return $this->error('No pude identificar tu número de teléfono. ¿Podrías proporcionarlo?');
        }

        $normalized = $this->normalizePhone($phone);

        $client = Client::where('phone', $normalized)
            ->orWhere('phone', $phone)
            ->first();

        if (!$client) {
            return response()->json([
                'encontrado' => false,
                'mensaje'    => 'No encontré una cuenta registrada con ese número. Si eres cliente nuevo, con gusto te atiendo.',
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
            'encontrado'          => true,
            'nombre'              => $client->name ?: $client->legal_name,
            'email'               => $client->email,
            'facturas_pendientes' => $pendientes->count(),
            'total_pendiente'     => '$' . number_format($totalPendiente, 2),
            'detalle_pendientes'  => $pendientes->map(fn($o) => [
                'folio' => $o->series . ($o->folio_number ?? ''),
                'total' => '$' . number_format($o->total, 2),
                'fecha' => $o->created_at->format('d/m/Y'),
            ])->values(),
            'ultimos_pagos' => $pagadas->map(fn($o) => [
                'folio'     => $o->series . ($o->folio_number ?? ''),
                'total'     => '$' . number_format($o->total, 2),
                'pagado_el' => $o->paid_at->format('d/m/Y'),
            ])->values(),
            'mensaje' => $pendientes->count() > 0
                ? "Tienes {$pendientes->count()} factura(s) pendiente(s) por \$" . number_format($totalPendiente, 2) . "."
                : 'Estás al corriente, no tienes facturas pendientes. 🎉',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  2. Registrar prospecto desde el chat
    //     POST /api/v1/dmchamp/lead
    //     Toma phone/email/name del system field automáticamente
    //     Input adicional: service, notes
    // ─────────────────────────────────────────────────────────────
    public function crearLead(Request $request): JsonResponse
    {
        $system = $request->input('system', []);

        // Datos automáticos de DM Champ + datos del input
        $phone   = $this->extractPhone($request);
        $email   = $request->input('email')
            ?: data_get($system, 'contact_email', '');
        $name    = trim($request->input('name') ?? '')
            ?: data_get($system, 'contact_name', '');
        $service = trim($request->input('service') ?? '');
        $notes   = trim($request->input('notes') ?? '');

        if (!$name && !$phone) {
            return $this->error('Necesito al menos el nombre o teléfono del prospecto.');
        }

        $normalized = $phone ? $this->normalizePhone($phone) : '';

        // Verificar si ya es cliente
        if ($normalized) {
            $existingClient = Client::where('phone', $normalized)
                ->orWhere('phone', $phone)
                ->first();

            if ($existingClient) {
                return response()->json([
                    'creado'  => false,
                    'mensaje' => "{$existingClient->name} ya es cliente registrado. Le daremos seguimiento.",
                ]);
            }

            $existing = Lead::where('phone', $normalized)
                ->orWhere('phone', $phone)
                ->first();

            if ($existing) {
                // Actualizar notas si hay nueva info
                if ($service || $notes) {
                    $append = $service ? "Interesado en: {$service}" : '';
                    if ($notes) {
                        $append .= ($append ? '. ' : '') . $notes;
                    }
                    $existing->update([
                        'project_description' => trim(($existing->project_description ?? '') . "\n" . $append),
                    ]);
                }

                return response()->json([
                    'creado'  => false,
                    'lead_id' => $existing->id,
                    'mensaje' => "Ya tenemos registrado a {$existing->name}. Hemos actualizado su información.",
                ]);
            }
        }

        $lead = Lead::create([
            'name'                => $name ?: 'Prospecto WhatsApp',
            'phone'               => $normalized ?: $phone,
            'email'               => $email ?: null,
            'project_description' => $service ? "Interesado en: {$service}" . ($notes ? ". {$notes}" : '') : ($notes ?: null),
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
    //     POST /api/v1/dmchamp/servicios
    //     Input: buscar (string, opcional)
    // ─────────────────────────────────────────────────────────────
    public function servicios(Request $request): JsonResponse
    {
        $buscar = trim($request->input('buscar') ?? '');

        $query = Service::where('active', true)
            ->where('public', true)
            ->with('category:id,name');

        if (!empty($buscar)) {
            $stopwords = ['que', 'los', 'las', 'del', 'una', 'uno', 'con', 'por', 'para', 'como',
                'son', 'hay', 'tiene', 'tienes', 'tiene', 'cuanto', 'cuesta', 'cuestan',
                'precio', 'precios', 'cual', 'cuales', 'sus', 'este', 'esta', 'esos',
                'esas', 'tipo', 'todos', 'todo', 'mas', 'muy', 'tambien', 'pero'];

            $palabras = preg_split('/\s+/', strtolower($buscar), -1, PREG_SPLIT_NO_EMPTY);
            $palabras = array_values(array_filter($palabras, fn($p) => strlen($p) > 2 && !in_array($p, $stopwords)));

            if (!empty($palabras)) {
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
            }
        }

        $services = $query->orderBy('price')->get();

        // Si buscó algo y no encontró, mostrar catálogo completo
        if ($services->isEmpty() && !empty($buscar)) {
            $todos = Service::where('active', true)
                ->where('public', true)
                ->with('category:id,name')
                ->orderBy('price')
                ->get();

            return response()->json([
                'encontrados' => 0,
                'mensaje'     => 'No encontré servicios con ese criterio. Aquí está nuestro catálogo completo:',
                'servicios'   => $todos->map(fn($s) => $this->formatService($s))->values(),
            ]);
        }

        return response()->json([
            'encontrados' => $services->count(),
            'servicios'   => $services->map(fn($s) => $this->formatService($s))->values(),
            'mensaje'     => "Encontré {$services->count()} servicio(s) disponible(s).",
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    /** Extrae teléfono: primero de system (automático), luego de input manual */
    private function extractPhone(Request $request): string
    {
        return $request->input('phone')
            ?: data_get($request->input('system', []), 'contact_phone', '');
    }

    private function formatService(Service $s): array
    {
        return [
            'nombre'         => $s->name,
            'descripcion'    => $s->description,
            'precio'         => '$' . number_format($s->price, 2) . ' MXN',
            'precio_con_iva' => '$' . number_format($s->priceWithIva(), 2) . ' MXN',
            'categoria'      => $s->category?->name,
            'mas_info'       => $s->info_url,
            'comprar'        => $s->slug ? $s->publicUrl() : null,
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
