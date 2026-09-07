<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentComplement;
use App\Models\Setting;
use App\Support\TaxBreakdown;
use CfdiUtils\Certificado\Certificado;
use CfdiUtils\CfdiCreator40;
use CfdiUtils\Elements\Pagos20\Pagos;
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


    // ──────────────────────────────────────────────
    // Complemento de pago (CFDI tipo P)
    // ──────────────────────────────────────────────

    /** Construye el REP sellado y lo devuelve como XML string. */
    public function buildSealedPaymentXml(PaymentComplement $complement): string
    {
        $credential = $this->credential();
        $creator    = $this->paymentComplementCreator($complement);

        $certificado = new Certificado($this->absolutePath(Setting::get('csd_cer_path')));
        $creator->putCertificado($certificado, false);

        $creator->addSello($credential->privateKey()->pem(), $credential->privateKey()->passPhrase());
        $creator->moveSatDefinitionsToComprobante();

        return $creator->asXml();
    }

    /**
     * Estructura del Recibo Electrónico de Pago, sin sellar.
     *
     * Un CFDI tipo P va siempre en ceros —Moneda XXX, SubTotal y Total 0, un
     * único concepto de "pago"— y lo que importa vive en el complemento
     * Pagos 2.0: el pago recibido y el documento al que se aplica.
     *
     * Se devuelve sin sello a propósito: así puede revisarse sin CSD.
     */
    public function paymentComplementCreator(PaymentComplement $complement): CfdiCreator40
    {
        $order   = $complement->order;
        $payment = $complement->payment;
        $client  = $order->client;

        $lugarExpedicion = (string) Setting::get('company_zip', '');
        if ($lugarExpedicion === '') {
            throw new \RuntimeException('Configura el C.P. de expedición (company_zip) en Ajustes → Facturación.');
        }

        $doc  = $order->fiscalDocument;
        $uuid = $doc?->uuid ?: ($doc?->facturapi_data['uuid'] ?? null);
        if (!$uuid) {
            throw new \RuntimeException('La factura timbrada no tiene UUID: no se puede relacionar el complemento.');
        }

        $formaPago = $payment->satPaymentForm();
        if ($formaPago === '99') {
            throw new \RuntimeException('La forma de pago del cobro es "99 (por definir)" y un complemento de pago no la admite.');
        }

        if (empty($client->address_zip)) {
            throw new \RuntimeException("El cliente {$client->legal_name} no tiene C.P. fiscal — requerido para el complemento de pago.");
        }

        $creator = new CfdiCreator40([
            'Serie'             => 'P' . ($order->series ?: 'F'),
            'Folio'             => (string) ($order->folio_number ?: $order->id),
            'Fecha'             => now('America/Mexico_City')->subMinutes(2)->format('Y-m-d\TH:i:s'),
            'Moneda'            => 'XXX',
            'SubTotal'          => '0',
            'Total'             => '0',
            'TipoDeComprobante' => 'P',
            'Exportacion'       => '01',
            'LugarExpedicion'   => $lugarExpedicion,
        ], null, new XmlResolver(storage_path('app/private/sat-resources')));

        $comprobante = $creator->comprobante();

        $comprobante->addEmisor([
            'Rfc'           => strtoupper((string) Setting::get('company_rfc', '')),
            'Nombre'        => mb_strtoupper((string) Setting::get('company_legal_name', Setting::get('company_name', ''))),
            'RegimenFiscal' => (string) Setting::get('company_tax_system', ''),
        ]);

        $comprobante->addReceptor([
            'Rfc'                     => strtoupper(trim((string) $client->tax_id)),
            'Nombre'                  => mb_strtoupper(trim((string) $client->legal_name)),
            'DomicilioFiscalReceptor' => $client->address_zip,
            'RegimenFiscalReceptor'   => $client->tax_system ?: '616',
            // CP01 es el único uso admitido en un CFDI de pagos.
            'UsoCFDI'                 => 'CP01',
        ]);

        // Concepto único y fijo que el SAT exige en todo CFDI de pagos.
        $comprobante->addConcepto([
            'ClaveProdServ' => '84111506',
            'Cantidad'      => '1',
            'ClaveUnidad'   => 'ACT',
            'Descripcion'   => 'Pago',
            'ValorUnitario' => '0',
            'Importe'       => '0',
            'ObjetoImp'     => '01',
        ]);

        $comprobante->addComplemento($this->pagos($complement, $uuid, $formaPago));

        return $creator;
    }

    /**
     * Nodo pago20:Pagos con el pago y el documento relacionado.
     *
     * El impuesto del documento relacionado se declara en proporción a lo
     * pagado: si el pago liquida la factura entera coincide con el de ésta, y
     * si es una parcialidad se reparte en la misma proporción.
     */
    private function pagos(PaymentComplement $complement, string $uuid, string $formaPago): Pagos
    {
        $order   = $complement->order;
        $payment = $complement->payment;

        $monto    = round((float) $complement->amount, 2);
        $saldoAnt = $complement->previousBalance();
        $moneda   = $payment->currency ?: 'MXN';

        [$baseGravada, $baseExenta] = $this->taxableSplit($order, $monto);

        $rate    = TaxBreakdown::rate();
        $tasa    = number_format($rate, 6, '.', '');
        $importe = round($baseGravada * $rate, 2);

        $sufijo = $this->rateSuffix($rate);

        $pagos = new Pagos();

        $totales = ['MontoTotalPagos' => $this->money($monto)];
        if ($baseGravada > 0) {
            $totales["TotalTrasladosBaseIVA{$sufijo}"]     = $this->money($baseGravada);
            $totales["TotalTrasladosImpuestoIVA{$sufijo}"] = $this->money($importe);
        }
        if ($baseExenta > 0) {
            $totales['TotalTrasladosBaseIVAExento'] = $this->money($baseExenta);
        }
        $pagos->addTotales($totales);

        $pago = $pagos->addPago([
            'FechaPago'    => ($payment->paid_at ?? now())->timezone('America/Mexico_City')->format('Y-m-d\TH:i:s'),
            'FormaDePagoP' => $formaPago,
            'MonedaP'      => $moneda,
            'TipoCambioP'  => '1',
            'Monto'        => $this->money($monto),
        ]);

        $docto = $pago->addDoctoRelacionado([
            'IdDocumento'      => $uuid,
            'Serie'            => $order->series ?: 'F',
            'Folio'            => (string) ($order->folio_number ?: $order->id),
            'MonedaDR'         => $moneda,
            'EquivalenciaDR'   => '1',
            'NumParcialidad'   => (string) $complement->installment,
            'ImpSaldoAnt'      => $this->money($saldoAnt),
            'ImpPagado'        => $this->money($monto),
            'ImpSaldoInsoluto' => $this->money(round($saldoAnt - $monto, 2)),
            'ObjetoImpDR'      => ($baseGravada > 0 || $baseExenta > 0) ? '02' : '01',
        ]);

        if ($baseGravada > 0 || $baseExenta > 0) {
            $traslados = $docto->addImpuestosDR()->addTrasladosDR();

            if ($baseGravada > 0) {
                $traslados->addTrasladoDR([
                    'BaseDR'       => $this->money($baseGravada),
                    'ImpuestoDR'   => '002',
                    'TipoFactorDR' => 'Tasa',
                    'TasaOCuotaDR' => $tasa,
                    'ImporteDR'    => $this->money($importe),
                ]);
            }

            if ($baseExenta > 0) {
                $traslados->addTrasladoDR([
                    'BaseDR'       => $this->money($baseExenta),
                    'ImpuestoDR'   => '002',
                    'TipoFactorDR' => 'Exento',
                ]);
            }

            $trasladosP = $pago->addImpuestosP()->addTrasladosP();

            if ($baseGravada > 0) {
                $trasladosP->addTrasladoP([
                    'BaseP'       => $this->money($baseGravada),
                    'ImpuestoP'   => '002',
                    'TipoFactorP' => 'Tasa',
                    'TasaOCuotaP' => $tasa,
                    'ImporteP'    => $this->money($importe),
                ]);
            }

            if ($baseExenta > 0) {
                $trasladosP->addTrasladoP([
                    'BaseP'       => $this->money($baseExenta),
                    'ImpuestoP'   => '002',
                    'TipoFactorP' => 'Exento',
                ]);
            }
        }

        return $pagos;
    }

    /**
     * Reparte lo pagado entre base gravada y base exenta, en la proporción en
     * que ambas pesan dentro de la factura.
     *
     * @return array{0: float, 1: float}
     */
    private function taxableSplit(Order $order, float $paid): array
    {
        $gravado = 0.0;
        $exento  = 0.0;

        foreach ($order->fiscalLines() as $line) {
            $importe = $line['quantity'] * $line['unit_price'];

            if ($line['tax_object'] === '01') {
                continue; // No objeto del impuesto: no se declara.
            }

            if ($line['exempt']) {
                $exento += $importe;
            } else {
                $gravado += $importe;
            }
        }

        $total = (float) $order->total;
        if ($total <= 0.0) {
            return [0.0, 0.0];
        }

        $proporcion = $paid / $total;

        return [round($gravado * $proporcion, 2), round($exento * $proporcion, 2)];
    }

    /**
     * Sufijo del atributo de totales que corresponde a la tasa. El SAT sólo
     * define IVA16, IVA8 e IVA0; con cualquier otra hay que emitir a mano.
     */
    private function rateSuffix(float $rate): string
    {
        return match (true) {
            abs($rate - 0.16) < 0.0001 => '16',
            abs($rate - 0.08) < 0.0001 => '8',
            abs($rate) < 0.0001        => '0',
            default => throw new \RuntimeException(sprintf(
                'La tasa de IVA configurada (%.2f%%) no es una de las que el SAT admite en un complemento de pago.',
                $rate * 100
            )),
        };
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
