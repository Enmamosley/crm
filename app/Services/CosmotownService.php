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
        $this->baseUrl = rtrim(Setting::get('cosmotown_base_url', 'https://irest-ote.cosmotown.com'), '/');
    }

    /**
     * Check if a domain is available for registration.
     *
     * @return array{available: bool, domain: string, price: float|null, currency: string|null, extra: string|null}
     */
    public function checkAvailability(string $domain): array
    {
        $domain = strtolower(trim($domain));

        $response = Http::withHeaders(['access_token' => $this->apiKey])
            ->timeout(10)
            ->get("{$this->baseUrl}/v1/domain/checkavail", ['domain' => $domain]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown API error {$response->status()}: {$response->body()}");
        }

        $data = $response->json();

        // Normalise the response — Cosmotown schema is undocumented; handle variations
        $available = $data['available'] ?? $data['isAvailable'] ?? false;

        return [
            'available' => (bool) $available,
            'domain'    => $domain,
            'price'     => isset($data['price']) ? (float) $data['price'] : null,
            'currency'  => $data['currency'] ?? 'USD',
            'extra'     => $data['extraInformation'] ?? $data['message'] ?? null,
            'raw'       => $data,
        ];
    }

    /**
     * Register a domain using the Cosmotown account fund.
     *
     * @return array
     */
    public function register(string $domain): array
    {
        $domain = strtolower(trim($domain));

        $response = Http::withHeaders(['access_token' => $this->apiKey])
            ->timeout(15)
            ->post("{$this->baseUrl}/v1/domain/register", ['domain' => $domain]);

        if ($response->failed()) {
            throw new \RuntimeException("Cosmotown registration error {$response->status()}: {$response->body()}");
        }

        return $response->json() ?? [];
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}
