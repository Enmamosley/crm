<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class CosmotownService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey  = Setting::get('cosmotown_api_key', '');
        $this->baseUrl = rtrim(Setting::get('cosmotown_base_url', 'https://sandbox.cosmotown.com'), '/');
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

        $response = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->timeout(10)
            ->post("{$this->baseUrl}/v1/reseller/searchdomains", [
                'domains' => [$domain],
            ]);

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

        $response = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->timeout(30)
            ->post("{$this->baseUrl}/v1/reseller/registerdomains", [
                'items' => [
                    ['name' => $domain, 'years' => $years],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown registration error {$response->status()}: {$response->body()}");
        }

        $results = $response->json() ?? [];

        return $results[0] ?? [];
    }

    /**
     * Get list of domains in the reseller account.
     * Uses GET /v1/reseller/listdomains
     */
    public function listDomains(int $limit = 100, int $offset = 0): array
    {
        $response = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->timeout(10)
            ->get("{$this->baseUrl}/v1/reseller/listdomains", [
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
        $response = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->timeout(10)
            ->get("{$this->baseUrl}/v1/reseller/domaininfo", [
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
        $response = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->timeout(15)
            ->post("{$this->baseUrl}/v1/reseller/savedomainnameservers", [
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
        $response = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->timeout(10)
            ->get("{$this->baseUrl}/v1/reseller/getdomaindnssettings", [
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
        $response = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->timeout(15)
            ->post("{$this->baseUrl}/v1/reseller/savedomaindnssettings", [
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
        $response = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->timeout(30)
            ->post("{$this->baseUrl}/v1/reseller/renewdomains", [
                'items' => [
                    ['name' => strtolower(trim($domain)), 'years' => $years],
                ],
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
        $response = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->timeout(10)
            ->post("{$this->baseUrl}/v1/reseller/domainstatus", [
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
        $response = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->timeout(5)
            ->get("{$this->baseUrl}/v1/reseller/ping");

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
