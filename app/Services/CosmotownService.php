<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CosmotownService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey  = Setting::get('cosmotown_api_key', '');
        $this->baseUrl = rtrim(Setting::get('cosmotown_base_url', 'https://sandbox.cosmotown.com'), '/');

        Log::debug('CosmotownService:init', [
            'baseUrl'    => $this->baseUrl,
            'apiKey_len' => strlen($this->apiKey),
            'apiKey_preview' => $this->apiKey ? substr($this->apiKey, 0, 6) . '...' : '(vacío)',
        ]);
    }

    private function request(string $method, string $endpoint, array $payload = []): \Illuminate\Http\Client\Response
    {
        $url = "{$this->baseUrl}{$endpoint}";

        // Redactar datos personales (contactos WHOIS) antes de loguear
        $logPayload = $payload;
        if (isset($logPayload['contacts'])) {
            $logPayload['contacts'] = '[REDACTED ' . count((array) $payload['contacts']) . ' roles]';
        }

        Log::debug("Cosmotown:{$method}:{$endpoint}", [
            'url'     => $url,
            'payload' => $logPayload,
            'header'  => 'X-API-TOKEN: ' . (substr($this->apiKey, 0, 6) ?: '(vacío)') . '...',
        ]);

        $http = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]);

        $response = match(strtoupper($method)) {
            'GET'  => $http->timeout(10)->get($url, $payload),
            'POST' => $http->timeout(30)->post($url, $payload),
            default => throw new \InvalidArgumentException("Método HTTP no soportado: {$method}"),
        };

        Log::debug("Cosmotown:response:{$endpoint}", [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return $response;
    }

    /**
     * Check if a domain is available for registration.
     * Uses POST /v1/reseller/searchdomains (Cosmotown Reseller API V1.2)
     *
     * @return array{available: bool, domain: string, price: float|null, message: string|null}
     */
    public function checkAvailability(string $domain): array
    {
        $domain = strtolower(trim($domain));

        $response = $this->request('POST', '/v1/reseller/searchdomains', ['domains' => [$domain]]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown API error {$response->status()}: {$response->body()}");
        }

        $data = $response->json();
        $result = $data['domains'][0] ?? [];

        return [
            'available' => ($result['status'] ?? '') === 'available',
            'domain'    => $result['domain'] ?? $domain,
            'price'     => isset($result['price']) ? (float) $result['price'] : null,
            'message'   => $result['message'] ?? null,
            'tld'       => $result['tld'] ?? null,
            'sld'       => $result['sld'] ?? null,
            'raw'       => $result,
        ];
    }

    /**
     * Register a domain using the Cosmotown account fund.
     * Uses POST /v1/reseller/registerdomains (Cosmotown Reseller API V1.2)
     *
     * @return array{domain: string, status: string, message: string}
     */
    public function register(string $domain, int $years = 1): array
    {
        $domain = strtolower(trim($domain));

        $response = $this->request('POST', '/v1/reseller/registerdomains', [
            'items' => [['name' => $domain, 'years' => $years]],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown registration error {$response->status()}: {$response->body()}");
        }

        $results = $response->json() ?? [];

        return $results[0] ?? [];
    }

    /**
     * Guarda los datos de contacto (WHOIS) de un dominio ya registrado.
     * Uses POST /v1/reseller/savedomaincontactinformation — el registro y los
     * contactos son operaciones SEPARADAS en la API de Cosmotown; esto se llama
     * después de register(). El mismo contacto se aplica a los 4 roles.
     *
     * Es "mejor esfuerzo": el llamador debe envolverlo en try/catch — un fallo
     * aquí no debe romper el registro ni la provisión del hosting.
     */
    public function saveDomainContacts(string $domain, array $contact): array
    {
        $domain = strtolower(trim($domain));

        $response = $this->request('POST', '/v1/reseller/savedomaincontactinformation', [
            'domain'   => $domain,
            'contacts' => [
                'registrant'     => $contact,
                'administrative' => $contact,
                'technical'      => $contact,
                'billing'        => $contact,
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown contacts error {$response->status()}: {$response->body()}");
        }

        return $response->json() ?? [];
    }

    /**
     * Construye el contacto de registro (WHOIS) para un cliente: usa los datos
     * del cliente y completa lo que falte con los datos de la empresa (Ajustes).
     * Campos según la API de Cosmotown: firstName, lastName, company, email,
     * phone, address1, city, state, zip, country.
     */
    public static function contactFromClient(\App\Models\Client $client): array
    {
        $fullName = trim($client->name ?: $client->legal_name ?: Setting::get('company_name', ''));
        $parts     = preg_split('/\s+/', $fullName, 2) ?: [''];
        $firstName = $parts[0] ?? '';
        $lastName  = $parts[1] ?? $firstName;

        $street = trim(implode(' ', array_filter([$client->address_street, $client->address_exterior])));

        return array_filter([
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'company'   => $client->legal_name ?: Setting::get('company_name', ''),
            'email'     => $client->email ?: Setting::get('company_email', ''),
            'phone'     => $client->phone ?: Setting::get('company_phone', ''),
            'address1'  => $street ?: Setting::get('company_address', ''),
            'city'      => $client->address_city,
            'state'     => $client->address_state,
            'zip'       => $client->address_zip,
            'country'   => strtoupper($client->address_country ?: 'MX'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Get list of domains in the reseller account.
     * Uses GET /v1/reseller/listdomains
     */
    public function listDomains(int $limit = 100, int $offset = 0): array
    {
        $response = $this->request('GET', '/v1/reseller/listdomains', [
            'limit'  => $limit,
            'offset' => $offset,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown API error {$response->status()}: {$response->body()}");
        }

        return $response->json() ?? [];
    }

    /**
     * Get detailed info for a domain.
     * Uses GET /v1/reseller/domaininfo
     */
    public function domainInfo(string $domain): array
    {
        $response = $this->request('GET', '/v1/reseller/domaininfo', [
            'domain' => strtolower(trim($domain)),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown API error {$response->status()}: {$response->body()}");
        }

        return $response->json() ?? [];
    }

    /**
     * Save nameservers for a domain.
     * Uses POST /v1/reseller/savedomainnameservers
     */
    public function saveNameservers(string $domain, array $nameservers): void
    {
        $response = $this->request('POST', '/v1/reseller/savedomainnameservers', [
            'domain'      => strtolower(trim($domain)),
            'nameservers' => $nameservers,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown API error {$response->status()}: {$response->body()}");
        }
    }

    /**
     * Get DNS settings for a domain.
     * Uses GET /v1/reseller/getdomaindnssettings
     */
    public function getDnsSettings(string $domain): array
    {
        $response = $this->request('GET', '/v1/reseller/getdomaindnssettings', [
            'domain' => strtolower(trim($domain)),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown API error {$response->status()}: {$response->body()}");
        }

        return $response->json() ?? [];
    }

    /**
     * Save DNS settings for a domain.
     * Uses POST /v1/reseller/savedomaindnssettings
     */
    public function saveDnsSettings(string $domain, array $records): void
    {
        $response = $this->request('POST', '/v1/reseller/savedomaindnssettings', [
            'domain'  => strtolower(trim($domain)),
            'records' => $records,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown API error {$response->status()}: {$response->body()}");
        }
    }

    /**
     * Renew domains.
     * Uses POST /v1/reseller/renewdomains
     */
    public function renew(string $domain, int $years = 1): array
    {
        $response = $this->request('POST', '/v1/reseller/renewdomains', [
            'items' => [['name' => strtolower(trim($domain)), 'years' => $years]],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown API error {$response->status()}: {$response->body()}");
        }

        return $response->json() ?? [];
    }

    /**
     * Check registration status of domains.
     * Uses POST /v1/reseller/domainstatus
     */
    public function domainStatus(array $domains): array
    {
        $response = $this->request('POST', '/v1/reseller/domainstatus', [
            'domains' => array_map('strtolower', $domains),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown API error {$response->status()}: {$response->body()}");
        }

        return $response->json() ?? [];
    }

    /**
     * Ping the API to verify connectivity and get your IP.
     * Uses GET /v1/reseller/ping
     */
    public function ping(): array
    {
        $response = $this->request('GET', '/v1/reseller/ping');

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown API error {$response->status()}: {$response->body()}");
        }

        return $response->json() ?? [];
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}
