<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FiscalDocument;
use App\Models\Order;
use App\Models\Setting;
use App\Support\TaxBreakdown;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FacturapiService
{
    private string $baseUrl = 'https://www.facturapi.io/v2';

    /**
     * Se lee en cada uso, no se cachea en el constructor: los servicios viven
     * en el contenedor y un cambio en Ajustes debe verse sin reinstanciarlos.
     */
    private function apiKey(): string
    {
        return (string) Setting::get('facturapi_api_key', '');
    }

    // ──────────────────────────────────────────────
    // Clientes
    // ──────────────────────────────────────────────

    /** ¿Hay API key de Facturapi en Ajustes? */
    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * Crea o actualiza el cliente en FacturAPI y guarda el ID en la BD.
     */
    public function syncCustomer(Client $client): array
    {
        $payload = [
            'legal_name' => $client->legal_name,
            'tax_id'     => strtoupper($client->tax_id),
            'tax_system' => $client->tax_system,
            'email'      => $client->email,
            'address'    => $client->facturApiAddress(),
        ];

        if ($client->facturapi_customer_id) {
            // Actualizar
            $response = $this->put("/customers/{$client->facturapi_customer_id}", $payload);
        } else {
            // Crear
            $response = $this->post('/customers', $payload);
        }

        $data = $response->json();

        if ($response->successful()) {
            $client->update(['facturapi_customer_id' => $data['id']]);
        }

        return $data;
    }

    // ──────────────────────────────────────────────
    // Facturas
    // ──────────────────────────────────────────────

    /**
     * Timbra la factura en FacturAPI y actualiza el registro local.
     */
    public function stampInvoice(Order $order): array
    {
        $client = $order->client;

        // Asegurar que el cliente exista en FacturAPI
        if (!$client->facturapi_customer_id) {
            $syncResult = $this->syncCustomer($client);
            $client->refresh();

            if (!$client->facturapi_customer_id) {
                \Log::warning('FacturAPI syncCustomer failed, will use inline customer', ['sync_result' => $syncResult]);
            }
        }

        // Customer: público en general o datos del cliente
        if (($order->billing_preference ?? 'fiscal') === 'publico_general') {
            $customer = [
                'legal_name' => 'PUBLICO EN GENERAL',
                'tax_id'     => 'XAXX010101000',
                'tax_system' => '616',
                'use'        => 'S01',
                'address'    => ['zip' => '06300'],
            ];
        } elseif ($client->facturapi_customer_id) {
            $customer = $client->facturapi_customer_id;
        } else {
            $customer = [
                'legal_name'  => $client->legal_name,
                'tax_id'      => strtoupper($client->tax_id),
                'tax_system'  => $client->tax_system,
                'email'       => $client->email,
                'address'     => $client->facturApiAddress(),
            ];
        }

        // Los conceptos los deriva la orden, igual que para Finkok: así los dos
        // PAC timbran lo mismo. Antes, una compra directa (sin ítems ni
        // cotización) se enviaba literalmente sin conceptos.
        $ivaRate = TaxBreakdown::rate();
        $lines   = $order->fiscalLines();

        // Lo timbrado tiene que cuadrar con lo cobrado; lo comprobamos antes
        // de enviarlo, que es cuando todavía se puede corregir.
        $order->assertChargedTotalMatches(TaxBreakdown::forLines(array_map(fn (array $line) => [
            'amount' => $line['quantity'] * $line['unit_price'],
            'taxed'  => !$line['exempt'] && $line['tax_object'] !== '01',
        ], $lines), rate: $ivaRate)->total);

        $items = array_map(fn (array $line) => [
            'quantity'   => $line['quantity'],
            'tax_object' => $line['tax_object'],
            'product'    => [
                'description'  => $line['description'],
                'product_key'  => $line['product_key'],
                'unit_key'     => $line['unit_key'],
                'unit_name'    => $line['unit_name'],
                'price'        => $line['unit_price'],
                'tax_included' => false,
                'taxes'        => ($line['exempt'] || $line['tax_object'] === '01')
                    ? [['type' => 'IVA', 'rate' => 0, 'factor' => 'Exento']]
                    : [['type' => 'IVA', 'rate' => $ivaRate, 'factor' => 'Tasa']],
            ],
        ], $lines);

        $payload = array_filter([
            'customer'       => $customer,
            'items'          => $items,
            'payment_form'   => $order->payment_form,
            'payment_method' => $order->payment_method,
            'use'            => ($order->billing_preference === 'publico_general') ? 'S01' : $order->use_cfdi,
            'series'         => $order->series,
            'folio_number'   => $order->folio_number,
        ]);

        $response = $this->post('/invoices', $payload);
        $data     = $response->json();

        if ($response->successful()) {
            $order->fiscalDocument()->create([
                'facturapi_invoice_id' => $data['id'],
                'status'               => $data['status'] === 'valid' ? 'valid' : 'pending',
                'facturapi_data'       => $data,
                'stamped_at'           => now(),
            ]);
        }

        return ['success' => $response->successful(), 'data' => $data];
    }

    /**
     * Cancela una factura timbrada en FacturAPI.
     */
    public function cancelInvoice(Order $order, string $motive = '02'): array
    {
        $doc = $order->fiscalDocument;

        if (!$doc?->facturapi_invoice_id) {
            return ['success' => false, 'message' => 'La orden no tiene un CFDI timbrado.'];
        }

        $response = $this->delete("/invoices/{$doc->facturapi_invoice_id}?motive={$motive}");
        $data     = $response->json();

        if ($response->successful()) {
            $doc->update([
                'status'               => 'cancelled',
                'cancelled_at'         => now(),
                'cancellation_motive'  => $motive,
            ]);
        }

        return ['success' => $response->successful(), 'data' => $data];
    }

    // ──────────────────────────────────────────────
    // Complemento de Pago (PPD)
    // ──────────────────────────────────────────────

    /**
     * Crea un CFDI de complemento de pago para facturas PPD.
     */
    public function createPaymentComplement(Order $order, Payment $payment): array
    {
        $doc = $order->fiscalDocument;

        if (!$doc?->facturapi_invoice_id) {
            return ['success' => false, 'message' => 'La orden no tiene un CFDI timbrado.'];
        }

        $client = $order->client;
        if (!$client->facturapi_customer_id) {
            $this->syncCustomer($client);
            $client->refresh();
        }

        $payload = [
            'type'     => 'P',
            'customer' => $client->facturapi_customer_id,
            'complements' => [
                [
                    'type' => 'pago',
                    'data' => [
                        [
                            'payment_form' => $payment->satPaymentForm(),
                            'currency'     => $payment->currency ?? 'MXN',
                            'date'         => ($payment->paid_at ?? now())->toIso8601String(),
                            'amount'       => (float) $payment->amount,
                            'related_documents' => [
                                [
                                    'uuid'          => $doc->facturapi_data['uuid'] ?? '',
                                    'series'        => $order->series,
                                    'folio_number'  => $order->folio_number,
                                    'last_balance'  => (float) $order->total,
                                    'amount'        => (float) $payment->amount,
                                    'installment'   => 1,
                                    'currency'      => $payment->currency ?? 'MXN',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->post('/invoices', $payload);
        $data     = $response->json();

        return ['success' => $response->successful(), 'data' => $data];
    }

    /**
     * Descarga el PDF de la factura timbrada.
     */
    public function downloadPdf(Order $order): ?string
    {
        $id = $order->fiscalDocument?->facturapi_invoice_id;
        if (!$id) return null;
        $response = Http::withBasicAuth($this->apiKey(), '')
            ->get("{$this->baseUrl}/invoices/{$id}/pdf");
        return $response->successful() ? $response->body() : null;
    }

    public function downloadXml(Order $order): ?string
    {
        $id = $order->fiscalDocument?->facturapi_invoice_id;
        if (!$id) return null;
        $response = Http::withBasicAuth($this->apiKey(), '')
            ->get("{$this->baseUrl}/invoices/{$id}/xml");
        return $response->successful() ? $response->body() : null;
    }

    // ──────────────────────────────────────────────
    // HTTP helpers
    // ──────────────────────────────────────────────

    private function http()
    {
        return Http::withBasicAuth($this->apiKey(), '')
            ->timeout(20)
            ->connectTimeout(5)
            ->acceptJson()
            ->contentType('application/json');
    }

    private function post(string $path, array $data): Response
    {
        return $this->http()->post($this->baseUrl . $path, $data);
    }

    private function put(string $path, array $data): Response
    {
        return $this->http()->put($this->baseUrl . $path, $data);
    }

    private function delete(string $path): Response
    {
        return $this->http()->delete($this->baseUrl . $path);
    }
}
