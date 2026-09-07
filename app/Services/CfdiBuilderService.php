<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use CfdiUtils\Certificado\Certificado;
use CfdiUtils\CfdiCreator40;
use CfdiUtils\XmlResolver\XmlResolver;
use Illuminate\Support\Facades\Storage;
use PhpCfdi\Credentials\Credential;

/**
 * Construye y SELLA un CFDI 4.0 de Ingreso a partir de una Order, usando el
 * CSD cargado en Ajustes. El XML resultante (pre-CFDI sellado) se envía al PAC
 * (Finkok) para su timbrado.
 *
 * Requiere en Ajustes: company_rfc, company_legal_name (razón social SAT),
 * company_tax_system (régimen del emisor), company_zip (LugarExpedicion) y el
 * CSD (csd_cer_path, csd_key_path, csd_key_password).
 */
class CfdiBuilderService
{
    /** Construye el CFDI sellado y lo devuelve como XML string. */
    public function buildSealedXml(Order $order): string
    {
        $credential = $this->credential();
        $client     = $order->client;

        $ivaRate = (float) Setting::get('iva_percentage', 16) / 100;
        $tasa    = number_format($ivaRate, 6, '.', '');

        $clientRfc = strtoupper(trim((string) ($client?->tax_id ?? '')));

        $isPublicoGeneral = ($order->billing_preference ?? 'fiscal') === 'publico_general'
            || $clientRfc === ''
            || $clientRfc === 'XAXX010101000';

        $lugarExpedicion = (string) Setting::get('company_zip', '');
        if ($lugarExpedicion === '') {
            throw new \RuntimeException('Configura el C.P. de expedición (company_zip) en Ajustes → Facturación.');
        }

        $emisorRegimen = (string) Setting::get('company_tax_system', '');
        if ($emisorRegimen === '') {
            throw new \RuntimeException('Configura el régimen fiscal del emisor en Ajustes → Facturación.');
        }

        if (!$isPublicoGeneral && empty($client->address_zip)) {
            throw new \RuntimeException("El cliente {$client->legal_name} no tiene C.P. fiscal — requerido para CFDI 4.0.");
        }

        $creator = new CfdiCreator40([
            'Serie'             => $order->series ?: 'F',
            'Folio'             => (string) ($order->folio_number ?: $order->id),
            // El SAT exige la fecha en hora local de México (no UTC) y dentro de
            // las últimas 72h; el pequeño margen evita rechazos por desfase de reloj.
            'Fecha'             => now('America/Mexico_City')->subMinutes(2)->format('Y-m-d\TH:i:s'),
            'FormaPago'         => $order->payment_form ?: '99',
            'MetodoPago'        => $order->payment_method ?: 'PUE',
            'Moneda'            => 'MXN',
            'TipoDeComprobante' => 'I',
            'Exportacion'       => '01',
            'LugarExpedicion'   => $lugarExpedicion,
        ], null, new XmlResolver(storage_path('app/private/sat-resources')));

        $comprobante = $creator->comprobante();

        $comprobante->addEmisor([
            'Rfc'           => strtoupper((string) Setting::get('company_rfc', '')),
            'Nombre'        => mb_strtoupper((string) Setting::get('company_legal_name', Setting::get('company_name', ''))),
            'RegimenFiscal' => $emisorRegimen,
        ]);

        if ($isPublicoGeneral) {
            $comprobante->addReceptor([
                'Rfc'                     => 'XAXX010101000',
                'Nombre'                  => 'PUBLICO EN GENERAL',
                'DomicilioFiscalReceptor' => $lugarExpedicion,
                'RegimenFiscalReceptor'   => '616',
                'UsoCFDI'                 => 'S01',
            ]);
            // Obligatorio para facturas globales a público en general (regla SAT).
            // Mes/año en hora de México: en frontera de mes, UTC declararía el mes siguiente.
            $comprobante->addInformacionGlobal([
                'Periodicidad' => '01',
                'Meses'        => now('America/Mexico_City')->format('m'),
                'Año'          => now('America/Mexico_City')->format('Y'),
            ]);
        } else {
            $comprobante->addReceptor([
                'Rfc'                     => $clientRfc,
                'Nombre'                  => mb_strtoupper(trim($client->legal_name)),
                'DomicilioFiscalReceptor' => $client->address_zip,
                'RegimenFiscalReceptor'   => $client->tax_system ?: '616',
                'UsoCFDI'                 => $order->use_cfdi ?: 'G03',
            ]);
        }

        // ── Conceptos ───────────────────────────────────────────
        foreach ($order->fiscalLines() as $c) {
            $importe = round($c['quantity'] * $c['unit_price'], 2);

            $concepto = $comprobante->addConcepto([
                'ClaveProdServ' => $c['product_key'],
                'Cantidad'      => $this->money($c['quantity']),
                'ClaveUnidad'   => $c['unit_key'],
                'Unidad'        => $c['unit_name'],
                'Descripcion'   => $c['description'],
                'ValorUnitario' => $this->money($c['unit_price']),
                'Importe'       => $this->money($importe),
                'ObjetoImp'     => $c['tax_object'],
            ]);

            // '01' = no objeto de impuesto → sin nodo de impuestos
            if ($c['tax_object'] === '01') {
                continue;
            }

            if ($c['exempt']) {
                $concepto->addImpuestos()->addTraslados()->addTraslado([
                    'Base'       => $this->money($importe),
                    'Impuesto'   => '002',
                    'TipoFactor' => 'Exento',
                ]);
            } else {
                $concepto->addImpuestos()->addTraslados()->addTraslado([
                    'Base'       => $this->money($importe),
                    'Impuesto'   => '002',
                    'TipoFactor' => 'Tasa',
                    'TasaOCuota' => $tasa,
                    'Importe'    => $this->money(round($importe * $ivaRate, 2)),
                ]);
            }
        }

        // Suma SubTotal/Total/Impuestos a partir de los conceptos
        $creator->addSumasConceptos(null, 2);

        // Nunca timbrar un total distinto al cobrado (ver Order).
        $order->assertChargedTotalMatches((float) $comprobante['Total']);

        // ── Certificado + Sello ─────────────────────────────────
        $certificado = new Certificado($this->absolutePath(Setting::get('csd_cer_path')));
        $creator->putCertificado($certificado, false); // Emisor ya definido arriba

        $creator->addSello($credential->privateKey()->pem(), $credential->privateKey()->passPhrase());
        $creator->moveSatDefinitionsToComprobante();

        return $creator->asXml();
    }

    /** Credencial CSD desde los archivos cargados en Ajustes. */
    public function credential(): Credential
    {
        $cer  = Setting::get('csd_cer_path', '');
        $key  = Setting::get('csd_key_path', '');
        $pass = Setting::get('csd_key_password', '');

        if (!$cer || !$key) {
            throw new \RuntimeException('Carga el CSD (.cer y .key) en Ajustes → Facturación antes de timbrar.');
        }

        return Credential::openFiles($this->absolutePath($cer), $this->absolutePath($key), $pass);
    }

    public function isConfigured(): bool
    {
        return Setting::get('csd_cer_path')
            && Setting::get('csd_key_path')
            && Setting::get('company_rfc')
            && Setting::get('company_zip')
            && Setting::get('company_tax_system');
    }


    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function absolutePath(string $relative): string
    {
        return Storage::disk('local')->path($relative);
    }
}
