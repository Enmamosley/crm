<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Lead;
use App\Support\Phone;
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
        // Sin secreto no se puede autenticar a nadie, así que se cierra: antes
        // se saltaba la comprobación entera y el endpoint quedaba como una API
        // pública de escritura (crear leads, cambiar su estado, dejar notas).
        // Es el mismo criterio que DmChampTokenMiddleware.
        $secret = config('services.dmchamp.webhook_secret');

        if (empty($secret)) {
            Log::warning('DmChamp webhook: DMCHAMP_WEBHOOK_SECRET sin configurar — se rechaza');
            return response('Not configured', 403);
        }

        $signature = $request->header('X-DmChamp-Signature') ?? '';
        $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('DmChamp webhook: firma inválida', [
                'ip' => $request->ip(),
            ]);
            return response('Unauthorized', 401);
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
        $phone = Phone::normalize($data['contactPhone'] ?? null);
        $email = $data['contactEmail'] ?? null;

        if (! $phone && ! $email) {
            return;
        }

        // Sólo se compara contra los datos que llegaron. Un orWhere('email', null)
        // degenera en "email IS NULL", así que en cuanto existía un lead sin
        // correo —lo normal en WhatsApp— la consulta daba positivo siempre y
        // este canal descartaba en silencio todos los contactos nuevos.
        $exists = Lead::where(function ($query) use ($phone, $email) {
            if ($phone) {
                $query->orWhere('phone', $phone);
            }
            if ($email) {
                $query->orWhere('email', $email);
            }
        })->exists();

        if ($exists) {
            return;
        }

        $lead = Lead::create([
            'name'    => trim(($data['contactFirstName'] ?? '') . ' ' . ($data['contactLastName'] ?? '')) ?: 'Sin nombre',
            'phone'   => $phone ?: null,
            'email'   => $email,
            'source'  => 'dmchamp',
            'status'  => 'nuevo',
        ]);

        // Igual que los otros tres puntos de alta, para que el lead nazca con historial.
        $lead->statusHistory()->create([
            'old_status' => null,
            'new_status' => 'nuevo',
            'changed_by' => 'dmchamp',
        ]);

        Log::info('DmChamp: Lead creado desde nuevo contacto', ['phone' => $phone]);
    }

    /** Contacto etiquetado en DM Champ → actualizar estado del Lead */
    private function handleContactTagged(array $data): void
    {
        $phone = Phone::normalize($data['contactPhone'] ?? null);
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

        if (isset($statusMap[$tag]) && $lead->status !== $statusMap[$tag]) {
            // updateStatus() y no update(): es el único sitio que deja rastro
            // en el historial, y ésta es la vía automática de mayor volumen.
            $lead->updateStatus($statusMap[$tag], 'dmchamp');
            Log::info("DmChamp: Lead #{$lead->id} actualizado a '{$statusMap[$tag]}' por tag '{$tag}'");
        }
    }

    /** Cita agendada en DM Champ → registrar nota en Lead/Cliente */
    private function handleAppointmentBooked(array $data): void
    {
        $phone = Phone::normalize($data['contactPhone'] ?? null);
        if (! $phone) {
            return;
        }

        $date    = $data['appointmentDate'] ?? 'sin fecha';
        $lead    = Lead::where('phone', $phone)->first();
        $client  = Client::where('phone', $phone)->first();

        $note = "Cita agendada vía DM Champ para {$date}.";

        if ($lead) {
            $lead->notes()->create(['content' => $note, 'author' => 'DM Champ']);
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
