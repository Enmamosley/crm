<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Quote;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DmChampService
{
    private string $baseUrl = 'https://api.dmchamp.com/v1';
    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.dmchamp.api_key');
    }

    /** Verifica si la integración está configurada */
    public function isEnabled(): bool
    {
        return ! empty($this->apiKey);
    }

    // ─────────────────────────────────────────────────────────────
    //  CONTACTOS
    // ─────────────────────────────────────────────────────────────

    /** Sincroniza un Lead como contacto en DM Champ */
    public function syncLead(Lead $lead): ?array
    {
        if (! $this->isEnabled() || empty($lead->phone)) {
            return null;
        }

        return $this->upsertContact([
            'firstName' => $lead->name,
            'phone'     => $this->normalizePhone($lead->phone),
            'email'     => $lead->email ?? null,
            'tags'      => ['lead', $lead->status],
        ]);
    }

    /** Sincroniza un Cliente como contacto en DM Champ */
    public function syncClient(Client $client): ?array
    {
        if (! $this->isEnabled() || empty($client->phone)) {
            return null;
        }

        $name = $client->name ?: $client->legal_name;

        return $this->upsertContact([
            'firstName' => $name,
            'phone'     => $this->normalizePhone($client->phone),
            'email'     => $client->email ?? null,
            'tags'      => ['cliente'],
        ]);
    }

    /** Crea o actualiza un contacto (por teléfono) */
    public function upsertContact(array $data): ?array
    {
        $response = $this->post('contacts', $data);

        if ($response && isset($response['id'])) {
            return $response;
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    //  MENSAJES
    // ─────────────────────────────────────────────────────────────

    /** Notifica al cliente que su factura fue creada */
    public function notifyInvoiceCreated(Order $order): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $client = $order->client;
        if (empty($client?->phone)) {
            return false;
        }

        $clientName = $client->name ?: $client->legal_name;
        $message    = "Hola {$clientName}, tu factura #{$order->folio()} por "
            . '$' . number_format($order->total, 2) . ' ha sido generada.'
            . ' Puedes consultarla en tu portal de clientes.';

        return $this->sendMessage($client->phone, $message);
    }

    /** Notifica al cliente que su cotización fue enviada */
    public function notifyQuoteSent(Quote $quote): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $lead = $quote->lead;
        if (empty($lead?->phone)) {
            return false;
        }

        $message = "Hola {$lead->name}, te hemos enviado una cotización por "
            . '$' . number_format($quote->total, 2) . '. '
            . 'Puedes revisarla en el enlace que te enviamos por correo.';

        return $this->sendMessage($lead->phone, $message);
    }

    /** Envía recordatorio de pago para una factura vencida */
    public function sendPaymentReminder(Order $order): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $client = $order->client;
        if (empty($client?->phone)) {
            return false;
        }

        $clientName = $client->name ?: $client->legal_name;
        $message    = "Hola {$clientName}, te recordamos que tu factura #{$order->folio()} "
            . 'por $' . number_format($order->total, 2) . ' sigue pendiente de pago. '
            . 'Si ya realizaste el pago, por favor ignora este mensaje.';

        return $this->sendMessage($client->phone, $message);
    }

    /** Envía un mensaje de texto libre a un número */
    public function sendMessage(string $phone, string $body, ?string $campaignId = null): bool
    {
        $payload = [
            'customData' => [
                'fromId'        => 'crm-' . md5($phone),
                'customChannel' => 'crm',
                'body'          => $body,
                'firstName'     => '',
            ],
        ];

        if ($campaignId) {
            $payload['customData']['campaignId'] = $campaignId;
        }

        $payload['customData']['fromId'] = $this->normalizePhone($phone);

        $response = $this->post('send_custom_channel_message', $payload);

        return $response !== null;
    }

    // ─────────────────────────────────────────────────────────────
    //  HTTP helpers
    // ─────────────────────────────────────────────────────────────

    private function post(string $endpoint, array $data): ?array
    {
        try {
            $response = Http::timeout(10)
                ->post("{$this->baseUrl}/{$endpoint}?apiKey={$this->apiKey}", $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("DmChamp API error [{$endpoint}]", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error("DmChamp API exception [{$endpoint}]: " . $e->getMessage());
        }

        return null;
    }

    /** Normaliza el teléfono: asegura que empiece con + y código de país */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        // Si tiene 10 dígitos, asumir México (+52)
        if (strlen($phone) === 10) {
            $phone = '52' . $phone;
        }

        return '+' . ltrim($phone, '+');
    }
}
