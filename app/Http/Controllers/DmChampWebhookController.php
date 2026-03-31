<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Lead;
use App\Services\DmChampService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class DmChampWebhookController extends Controller
{
    public function __construct(private DmChampService $dmchamp)
    {
    }

    public function handle(Request $request): Response
    {
        // Verificar firma HMAC si hay secreto configurado
        $secret = config('services.dmchamp.webhook_secret');
        if ($secret) {
            $signature = $request->header('X-DmChamp-Signature') ?? '';
            $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

            if (! hash_equals($expected, $signature)) {
                Log::warning('DmChamp webhook: firma inválida', [
                    'ip' => $request->ip(),
                ]);
                return response('Unauthorized', 401);
            }
        }

        $event = $request->input('event');
        $data  = $request->input('data', []);

        Log::info("DmChamp webhook recibido: {$event}", ['data' => $data]);

        match ($event) {
            'new_contact'       => $this->handleNewContact($data),
            'contact_tagged'    => $this->handleContactTagged($data),
            'appointment_booked' => $this->handleAppointmentBooked($data),
            'new_message'       => $this->handleNewMessage($data),
            default             => null,
        };

        return response('OK', 200);
    }

    // ─────────────────────────────────────────────────────────────
    //  Handlers de eventos
    // ─────────────────────────────────────────────────────────────

    /** Nuevo contacto en DM Champ → crear Lead en CRM si no existe */
    private function handleNewContact(array $data): void
    {
        $phone = $data['contactPhone'] ?? null;
        $email = $data['contactEmail'] ?? null;

        if (! $phone && ! $email) {
            return;
        }

        // Buscar si ya existe un lead con ese teléfono o email
        $exists = Lead::where('phone', $phone)
            ->orWhere('email', $email)
            ->exists();

        if ($exists) {
            return;
        }

        Lead::create([
            'name'    => trim(($data['contactFirstName'] ?? '') . ' ' . ($data['contactLastName'] ?? '')) ?: 'Sin nombre',
            'phone'   => $phone,
            'email'   => $email,
            'source'  => 'dmchamp',
            'status'  => 'nuevo',
        ]);

        Log::info('DmChamp: Lead creado desde nuevo contacto', ['phone' => $phone]);
    }

    /** Contacto etiquetado en DM Champ → actualizar estado del Lead */
    private function handleContactTagged(array $data): void
    {
        $phone = $data['contactPhone'] ?? null;
        $tag   = $data['tag'] ?? null;

        if (! $phone || ! $tag) {
            return;
        }

        $lead = Lead::where('phone', $phone)->first();

        if (! $lead) {
            return;
        }

        // Mapear tags de DM Champ a estados del CRM
        $statusMap = [
            'calificado'   => 'contactado',
            'cotizado'     => 'cotizado',
            'cerrado'      => 'cerrado',
            'no-interesa'  => 'perdido',
            'perdido'      => 'perdido',
        ];

        if (isset($statusMap[$tag])) {
            $lead->update(['status' => $statusMap[$tag]]);
            Log::info("DmChamp: Lead #{$lead->id} actualizado a '{$statusMap[$tag]}' por tag '{$tag}'");
        }
    }

    /** Cita agendada en DM Champ → registrar nota en Lead/Cliente */
    private function handleAppointmentBooked(array $data): void
    {
        $phone = $data['contactPhone'] ?? null;
        if (! $phone) {
            return;
        }

        $date    = $data['appointmentDate'] ?? 'sin fecha';
        $lead    = Lead::where('phone', $phone)->first();
        $client  = Client::where('phone', $phone)->first();

        $note = "Cita agendada vía DM Champ para {$date}.";

        if ($lead) {
            $lead->notes()->create(['note' => $note, 'author' => 'DM Champ']);
        }

        if ($client) {
            // Solo si el modelo Client tiene relación notes / activity log
            \App\Models\ActivityLog::log('dmchamp_appointment', $client, $note);
        }

        Log::info("DmChamp: Cita agendada para {$phone} en {$date}");
    }

    /** Nuevo mensaje entrante → solo loguear (sin auto-responder desde CRM) */
    private function handleNewMessage(array $data): void
    {
        $phone   = $data['contactPhone'] ?? 'desconocido';
        $message = $data['messageBody'] ?? '';
        Log::info("DmChamp: Mensaje de {$phone}: {$message}");
    }
}
