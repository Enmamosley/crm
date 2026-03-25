<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TwentyIService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.20i.com';

    public function __construct()
    {
        $this->apiKey = Setting::get('twentyi_api_key', '');
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        // 20i espera el token como base64 del API key.
        // Si se guardó la clave combinada (general+oauth), extraemos solo la parte general.
        $key = str_contains($this->apiKey, '+')
            ? explode('+', $this->apiKey)[0]
            : $this->apiKey;

        return Http::withToken(base64_encode($key))
            ->baseUrl($this->baseUrl)
            ->acceptJson();
    }

    /**
     * Devuelve el dominio de correo asociado al paquete del cliente.
     */
    public function getDomain(Client $client): ?string
    {
        $packageId = $client->twentyi_package_id;
        return $packageId ? $this->getEmailId($packageId) : null;
    }

    /**
     * Lista los buzones de correo del paquete del cliente.
     * Devuelve un array de mailboxes, cada uno con: id, local, quotaMB, usageMB, enabled, etc.
     */
    public function listMailboxes(Client $client): array
    {
        $packageId = $client->twentyi_package_id;
        if (!$packageId) {
            return [];
        }

        // Primero obtenemos el emailId (dominio) del paquete
        $emailId = $this->getEmailId($packageId);
        if (!$emailId) {
            return [];
        }

        $response = $this->http()->get("/package/{$packageId}/email/{$emailId}/mailbox");
        $this->throwIfFailed($response, 'listar buzones');

        $data = $response->json();
        // La respuesta tiene forma: { name: "domain.com", mailbox: [ {...}, ... ] }
        return $data['mailbox'] ?? [];
    }

    /**
     * Crea un nuevo buzón de correo.
     */
    public function createMailbox(Client $client, string $local, string $password, int $quotaMB = 10240): array
    {
        $packageId = $client->twentyi_package_id;
        $emailId   = $this->getEmailId($packageId);

        if (!$emailId) {
            throw new \RuntimeException("No se pudo obtener el dominio del paquete 20i ({$packageId}). Verifica que el Package ID sea correcto.");
        }

        $payload = [
            'new' => [
                'mailbox' => [
                    'local'    => $local,
                    'password' => $password,
                    'receive'  => true,
                    'send'     => true,
                    'quotaMB'  => $quotaMB,
                ]
            ]
        ];

        $response = $this->http()->post("/package/{$packageId}/email/{$emailId}", $payload);
        $this->throwIfFailed($response, 'crear buzón');

        $data = $response->json();
        return $data['result']['result'][0] ?? [];
    }

    /**
     * Elimina un buzón de correo por su ID (ej: "mXXXXX").
     */
    public function deleteMailbox(Client $client, string $mailboxId): void
    {
        $packageId = $client->twentyi_package_id;
        $emailId   = $this->getEmailId($packageId);

        $payload = [
            'delete' => [$mailboxId],
        ];

        $response = $this->http()->post("/package/{$packageId}/email/{$emailId}", $payload);
        $this->throwIfFailed($response, 'eliminar buzón');
    }

    /**
     * Actualiza la contraseña de un buzón.
     */
    public function updateMailboxPassword(Client $client, string $mailboxId, string $newPassword): void
    {
        $packageId = $client->twentyi_package_id;
        $emailId   = $this->getEmailId($packageId);

        $payload = [
            'existing' => [
                $mailboxId => [
                    'password' => $newPassword,
                ]
            ]
        ];

        $response = $this->http()->post("/package/{$packageId}/email/{$emailId}", $payload);
        $this->throwIfFailed($response, 'cambiar contraseña');
    }

    /**
     * Obtiene la URL de webmail SSO para un buzón.
     * Devuelve la URL (string).
     */
    public function getWebmailUrl(Client $client, string $mailboxId): string
    {
        $packageId = $client->twentyi_package_id;
        $emailId   = $this->getEmailId($packageId);

        $response = $this->http()->post("/package/{$packageId}/email/{$emailId}/webmail", [
            'id' => $mailboxId,
        ]);
        $this->throwIfFailed($response, 'obtener URL webmail');

        $data = $response->json();
        return $data['result']['SsoLink'] ?? '';
    }

    // ─── Hosting ─────────────────────────────────────────────────────────────

    /**
     * Obtiene el ID del reseller asociado a la API key.
     * Llama a GET /reseller → la respuesta es un objeto cuyas claves son los IDs de reseller.
     */
    public function getResellerId(): string
    {
        $response = $this->http()->get('/reseller');
        $this->throwIfFailed($response, 'obtener reseller');

        $data = $response->json();

        // La respuesta es un array indexado: [{"id": 20408, ...}]
        if (isset($data[0]['id'])) {
            return (string) $data[0]['id'];
        }
        // Objeto directo: {"id": 20408}
        if (isset($data['id'])) {
            return (string) $data['id'];
        }
        // Objeto cuyas claves son IDs numéricos: {"20408": {...}}
        $first = array_key_first($data);
        if (is_numeric($first)) {
            return (string) $first;
        }

        throw new \RuntimeException('No se pudo obtener el ID de reseller de 20i. Respuesta inesperada: ' . json_encode($data));
    }

    /**
     * Crea un paquete de hosting en 20i para el dominio dado.
     * Devuelve el Package ID del paquete creado (string numérico).
     *
     * Requiere la configuración:
     *   - twentyi_api_key
     *   - twentyi_package_bundle_id  (ID del tipo de paquete a crear)
     */
    public function createHostingPackage(string $domain, ?string $bundleId = null): string
    {
        $bundleId = $bundleId ?? Setting::get('twentyi_package_bundle_id');
        if (!$bundleId) {
            throw new \RuntimeException('No se ha configurado el twentyi_package_bundle_id en Ajustes.');
        }

        // Primero obtenemos el reseller ID
        $resellerId = $this->getResellerId();

        $payload = [
            'type'           => (string) $bundleId,
            'domain_name'    => $domain,
            'label'          => $domain,
            'documentRoots'  => [$domain => 'public_html'],
        ];

        \Illuminate\Support\Facades\Log::info('20i createHostingPackage', [
            'domain'     => $domain,
            'reseller'   => $resellerId,
            'bundle'     => $bundleId,
            'payload'    => $payload,
        ]);

        $response = $this->http()->post("/reseller/{$resellerId}/addWeb", $payload);
        $this->throwIfFailed($response, 'crear paquete de hosting');

        $data = $response->json();

        \Illuminate\Support\Facades\Log::info('20i createHostingPackage response', ['data' => $data]);

        // La respuesta puede tener distintas formas:
        // {"result": {"result": [{"id": 12345}]}}
        // {"result": [12345]}
        // {"id": 12345}
        // [{"id": 12345}]
        $id = $data['result']['result'][0]['id']
            ?? $data['result'][0]['id']
            ?? $data['result'][0]
            ?? $data['id']
            ?? $data[0]['id']
            ?? $data[0]
            ?? null;

        if (!$id) {
            throw new \RuntimeException('Paquete creado pero no se pudo extraer el Package ID. Respuesta: ' . json_encode($data));
        }

        return (string) $id;
    }

    /**
     * Lista los tipos de paquetes disponibles para el reseller.
     * Útil para saber qué bundle_id configurar en Ajustes.
     */
    public function listPackageBundleTypes(): array
    {
        $resellerId = $this->getResellerId();

        // Intentar endpoint de allowances/productos del reseller
        // /reseller/{id}/packageTypes devuelve los tipos de paquetes disponibles
        $response = $this->http()->get("/reseller/{$resellerId}/packageTypes");
        if ($response->ok()) {
            $data = $response->json() ?? [];
            \Illuminate\Support\Facades\Log::info('20i packageTypes raw', ['data' => $data]);
            if (!empty($data)) {
                return $data;
            }
        }

        return [];
    }

    // ─── DNS ─────────────────────────────────────────────────────────────────

    /**
     * Lista los registros DNS del paquete del cliente.
     * Devuelve un array plano de registros, cada uno con: id, host, type, ip/content, ttl, priority.
     * La respuesta de 20i es { "dominio.com": [ { id, host, type, ip, ttl, ... }, ... ] }
     */
    public function listDnsRecords(Client $client): array
    {
        $packageId = $client->twentyi_package_id;
        if (!$packageId) {
            return [];
        }

        $response = $this->http()->get("/package/{$packageId}/dns");
        $this->throwIfFailed($response, 'listar registros DNS');

        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        // La respuesta tiene estructura: { "dominio.com": [ records... ] }
        $records = is_array(array_key_first($data) !== null ? $data[array_key_first($data)] : [])
            ? array_values($data[array_key_first($data)])
            : [];

        \Illuminate\Support\Facades\Log::info("20i listDnsRecords package {$packageId}", ['count' => count($records)]);

        return $records;
    }

    /**
     * Añade un registro DNS al paquete del cliente.
     * Soporta tipos: A, AAAA, CNAME, MX, TXT, NS, SRV.
     * Para MX incluir $priority; para A/AAAA el $value es la IP; para CNAME/TXT el $value es el destino.
     */
    public function addDnsRecord(Client $client, string $type, string $host, string $value, int $ttl = 3600, int $priority = 10): void
    {
        $packageId = $client->twentyi_package_id;

        $record = ['type' => strtoupper($type), 'host' => $host, 'ip' => $value, 'ttl' => $ttl];

        if (in_array(strtoupper($type), ['MX', 'SRV'])) {
            $record['priority'] = $priority;
        }

        $payload = ['new' => [$record]];

        $response = $this->http()->post("/package/{$packageId}/dns", $payload);
        $this->throwIfFailed($response, 'añadir registro DNS');
    }

    /**
     * Elimina un registro DNS por su ID.
     */
    public function deleteDnsRecord(Client $client, int|string $recordId): void
    {
        $packageId = $client->twentyi_package_id;

        $payload = ['delete' => [$recordId]];

        $response = $this->http()->post("/package/{$packageId}/dns", $payload);
        $this->throwIfFailed($response, 'eliminar registro DNS');
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /**
     * Obtiene el emailId (dominio de correo) del paquete.
     * Llama a /package/{id}/email cuya respuesta tiene forma {"dominio.com": [...]}
     * — la primera clave del objeto es el dominio.
     */
    private function getEmailId(string $packageId): ?string
    {
        $response = $this->http()->get("/package/{$packageId}/email");
        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::error("20i getEmailId failed for package {$packageId}", [
                'status' => $response->status(),
                'body'   => $response->json() ?? $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        $name = is_array($data) ? array_key_first($data) : null;

        \Illuminate\Support\Facades\Log::info("20i getEmailId package {$packageId}", ['name' => $name]);

        return $name ?: null;
    }

    private function throwIfFailed(Response $response, string $action): void
    {
        if ($response->failed()) {
            $body = $response->json();
            \Illuminate\Support\Facades\Log::error("20i API error [{$action}]", [
                'status' => $response->status(),
                'body'   => $body ?? $response->body(),
            ]);
            if (is_array($body)) {
                $msg = $body['message'] ?? $body['error'] ?? json_encode($body);
                if (is_array($msg)) {
                    $msg = json_encode($msg);
                }
            } else {
                $msg = $response->body();
            }
            throw new \RuntimeException("20i - error al {$action}: {$msg}");
        }
    }
}
