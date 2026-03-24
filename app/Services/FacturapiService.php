<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FacturapiService
{
    private string $apiKey;
    private string $baseUrl = 'https://www.facturapi.io/v2';

    public function __construct()
    {
        $this->apiKey = Setting::get('facturapi_api_key', '');
    }

    // ──────────────────────────────────────────────
    // Clientes
    // ──────────────────────────────────────────────

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
    public function stampInvoice(ClientInvoice $invoice): array
    {
        $client = $invoice->client;
        $quote  = $invoice->quote?->load('items.service');
        $invoice->load('items'); // Ítems manuales si no hay cotización

        // Asegurar que el cliente exista en FacturAPI
        if (!$client->facturapi_customer_id) {
            $syncResult = $this->syncCustomer($client);
            $client->refresh();

            // Si sigue sin ID, usar objeto inline
            if (!$client->facturapi_customer_id) {
                \Log::warning('FacturAPI syncCustomer failed, will use inline customer', ['sync_result' => $syncResult]);
            }
        }

        // Customer: público en general o datos del cliente
        if (($invoice->billing_preference ?? 'fiscal') === 'publico_general') {
            // RFC genérico SAT para Público en General
            $customer = [
                'legal_name' => 'PUBLICO EN GENERAL',
                'tax_id'     => 'XAXX010101000',
                'tax_system' => '616',  // Sin obligaciones fiscales
                'use'        => 'S01',  // Sin efectos fiscales
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

        // Construir ítems
        $ivaRate = (float) Setting::get('iva_percentage', 16) / 100;
        $items = [];
        if ($quote) {
            foreach ($quote->items as $item) {
                $svc = $item->service;

                // Campos fiscales del servicio, con defaults seguros
                $productKey = $svc?->sat_product_key ?: '80101501'; // Servicios profesionales (genérico SAT)
                $unitKey    = $svc?->sat_unit_key    ?: 'E48';      // E48 = Servicio
                $unitName   = $svc?->sat_unit_name   ?: 'Servicio';
                $taxObject  = $svc?->tax_object      ?: '02';       // 02 = Sí objeto de impuesto
                $isExempt   = $svc?->iva_exempt      ?? false;

                $product = [
                    'description'  => $item->description,
                    'product_key'  => $productKey,
                    'unit_key'     => $unitKey,
                    'unit_name'    => $unitName,
                    'price'        => (float) $item->unit_price,
                    'tax_included' => false,
                ];

                if (!$isExempt && $taxObject !== '01') {
                    $product['taxes'] = [[
                        'type'   => 'IVA',
                        'rate'   => $ivaRate,
                        'factor' => 'Tasa',
                    ]];
                } else {
                    // Exento o no objeto: IVA tasa 0
                    $product['taxes'] = [[
                        'type'   => 'IVA',
                        'rate'   => 0,
                        'factor' => 'Exento',
                    ]];
                }

                $items[] = [
                    'quantity'   => $item->quantity,
                    'product'    => $product,
                    'tax_object' => $taxObject,  // nivel item, no dentro de product
                ];
            }
        } elseif ($invoice->items->isNotEmpty()) {
            // Ítems manuales (sin cotización)
            foreach ($invoice->items as $item) {
                $taxObject = $item->tax_object ?: '02';
                $isExempt  = $item->iva_exempt ?? false;

                $product = [
                    'description'  => $item->description,
                    'product_key'  => $item->sat_product_key ?: '80101501',
                    'unit_key'     => $item->sat_unit_key    ?: 'E48',
                    'unit_name'    => $item->sat_unit_name   ?: 'Servicio',
                    'price'        => (float) $item->unit_price,
                    'tax_included' => false,
                ];

                if (!$isExempt && $taxObject !== '01') {
                    $product['taxes'] = [[
                        'type'   => 'IVA',
                        'rate'   => $ivaRate,
                        'factor' => 'Tasa',
                    ]];
                } else {
                    $product['taxes'] = [[
                        'type'   => 'IVA',
                        'rate'   => 0,
                        'factor' => 'Exento',
                    ]];
                }

                $items[] = [
                    'quantity'   => (float) $item->quantity,
                    'product'    => $product,
                    'tax_object' => $taxObject,
                ];
            }
        }

        $payload = array_filter([
            'customer'       => $customer,
            'items'          => $items,
            'payment_form'   => $invoice->payment_form,
            'payment_method' => $invoice->payment_method,
            'use'            => ($invoice->billing_preference === 'publico_general') ? 'S01' : $invoice->use_cfdi,
            'series'         => $invoice->series,
            'folio_number'   => $invoice->folio_number,
        ]);

        $response = $this->post('/invoices', $payload);
        $data     = $response->json();

        if ($response->successful()) {
            $invoice->update([
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
    public function cancelInvoice(ClientInvoice $invoice, string $motive = '02'): array
    {
        if (!$invoice->facturapi_invoice_id) {
            return ['success' => false, 'message' => 'La factura no está timbrada.'];
        }

        $response = $this->delete("/invoices/{$invoice->facturapi_invoice_id}?motive={$motive}");
        $data     = $response->json();

        if ($response->successful()) {
            $invoice->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
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
    public function createPaymentComplement(ClientInvoice $invoice, Payment $payment): array
    {
        if (!$invoice->facturapi_invoice_id) {
            return ['success' => false, 'message' => 'La factura no está timbrada.'];
        }

        $client = $invoice->client;
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
                                    'uuid'                 => $invoice->facturapi_data['uuid'] ?? '',
                                    'series'               => $invoice->series,
                                    'folio_number'         => $invoice->folio_number,
                                    'last_balance'         => (float) $invoice->total,
                                    'amount'               => (float) $payment->amount,
                                    'installment'          => 1,
                                    'currency'             => $payment->currency ?? 'MXN',
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
    public function downloadPdf(ClientInvoice $invoice): ?string
    {
        if (!$invoice->facturapi_invoice_id) return null;
        $response = Http::withBasicAuth($this->apiKey, '')
            ->get("{$this->baseUrl}/invoices/{$invoice->facturapi_invoice_id}/pdf");
        return $response->successful() ? $response->body() : null;
    }

    /**
     * Descarga el XML de la factura timbrada.
     */
    public function downloadXml(ClientInvoice $invoice): ?string
    {
        if (!$invoice->facturapi_invoice_id) return null;
        $response = Http::withBasicAuth($this->apiKey, '')
            ->get("{$this->baseUrl}/invoices/{$invoice->facturapi_invoice_id}/xml");
        return $response->successful() ? $response->body() : null;
    }

    // ──────────────────────────────────────────────
    // HTTP helpers
    // ──────────────────────────────────────────────

    private function http()
    {
        return Http::withBasicAuth($this->apiKey, '')
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
