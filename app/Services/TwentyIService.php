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
        // {"result": 3635667}              ← escalar directo
        // {"result": {"result": [{"id": 12345}]}}
        // {"result": [12345]}
        // {"id": 12345}
        $result = $data['result'] ?? null;

        if (is_numeric($result)) {
            $id = $result;
        } elseif (is_array($result)) {
            $id = $result['result'][0]['id']
                ?? $result[0]['id']
                ?? $result[0]
                ?? null;
        } else {
            $id = $data['id'] ?? $data[0]['id'] ?? $data[0] ?? null;
        }

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
     * Obtiene el nombre de dominio asociado al paquete.
     */
    private function getDomainForPackage(Client $client): string
    {
        if ($client->domain) {
            return $client->domain;
        }

        $packageId = $client->twentyi_package_id;

        $response = $this->http()->get("/package/{$packageId}/names");
        $this->throwIfFailed($response, 'obtener nombres del paquete');

        $data = $response->json();
        $names = is_array($data) ? $data : ($data['result'] ?? []);

        if (empty($names)) {
            throw new \RuntimeException("No se encontraron dominios en el paquete #{$packageId}");
        }

        $first = $names[0];
        return is_string($first) ? $first : ($first['name'] ?? (string) $first);
    }

    /**
     * Lista los registros DNS del paquete del cliente.
     * Endpoint no documentado: GET /package/{packageId}/dns/{domainName}
     */
    public function listDnsRecords(Client $client): array
    {
        $packageId = $client->twentyi_package_id;
        if (!$packageId) {
            return [];
        }

        $domain = $this->getDomainForPackage($client);

        $response = $this->http()->get("/package/{$packageId}/dns/{$domain}");
        $this->throwIfFailed($response, 'listar registros DNS');

        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        \Illuminate\Support\Facades\Log::info("20i listDnsRecords raw", ['packageId' => $packageId, 'domain' => $domain, 'data' => $data]);

        // Parsear la respuesta DNS — puede venir en varios formatos
        $records = [];

        // Formato 1: { "records": { "A": [{...}], "CNAME": [{...}] } }
        // Formato 2: { "Montsenails.com": [{...}, ...] }
        // Formato 3: [{ type, host, ip, ... }, ...]
        $source = $data['records'] ?? $data;

        if (is_array($source)) {
            foreach ($source as $key => $val) {
                if (is_int($key) && is_array($val)) {
                    // Array plano de registros
                    $records[] = $val;
                } elseif (is_string($key) && is_array($val)) {
                    // Agrupado por tipo (A, CNAME...) o por dominio
                    foreach ($val as $rec) {
                        if (is_array($rec)) {
                            if (!isset($rec['type'])) {
                                $rec['type'] = strtoupper($key);
                            }
                            $records[] = $rec;
                        }
                    }
                }
            }
        }

        \Illuminate\Support\Facades\Log::info("20i listDnsRecords parsed", ['count' => count($records)]);

        return $records;
    }

    /**
     * Añade un registro DNS al paquete del cliente.
     * Endpoint no documentado: POST /package/{packageId}/dns/{domainName}
     */
    public function addDnsRecord(Client $client, string $type, string $host, string $value, int $ttl = 3600, int $priority = 10): void
    {
        $packageId = $client->twentyi_package_id;
        $domain    = $this->getDomainForPackage($client);
        $type      = strtoupper($type);

        // Construir el registro según el tipo — formato 20i
        $record = match ($type) {
            'A'     => ['host' => $host, 'ip' => $value],
            'AAAA'  => ['host' => $host, 'ipv6' => $value],
            'CNAME' => ['host' => $host, 'target' => $value],
            'MX'    => ['host' => $host, 'target' => $value, 'pri' => (string) $priority],
            'TXT'   => ['host' => $host, 'txt' => $value],
            'NS'    => ['host' => $host, 'target' => $value],
            'SRV'   => ['host' => $host, 'target' => $value, 'pri' => (string) $priority, 'weight' => '0', 'port' => '0'],
            default => ['host' => $host, 'ip' => $value],
        };

        $payload = [
            'new' => [$type => $record],
        ];

        \Illuminate\Support\Facades\Log::info("20i addDnsRecord", ['packageId' => $packageId, 'domain' => $domain, 'payload' => $payload]);

        $response = $this->http()->post("/package/{$packageId}/dns/{$domain}", $payload);
        $this->throwIfFailed($response, 'añadir registro DNS');
    }

    /**
     * Edita un registro DNS: elimina el antiguo y crea el nuevo.
     */
    public function updateDnsRecord(Client $client, int|string $recordId, string $type, string $host, string $value, int $ttl = 3600, int $priority = 10): void
    {
        $this->deleteDnsRecord($client, $recordId);
        $this->addDnsRecord($client, $type, $host, $value, $ttl, $priority);
    }

    /**
     * Elimina un registro DNS por su ID.
     * Endpoint no documentado: POST /package/{packageId}/dns/{domainName}
     */
    public function deleteDnsRecord(Client $client, int|string $recordId): void
    {
        $packageId = $client->twentyi_package_id;
        $domain    = $this->getDomainForPackage($client);

        $payload = ['delete' => [(string) $recordId]];

        $response = $this->http()->post("/package/{$packageId}/dns/{$domain}", $payload);
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
