<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use CfdiUtils\Cfdi;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpCfdi\CfdiExpresiones\DiscoverExtractor;
use PhpCfdi\Finkok\FinkokEnvironment;
use PhpCfdi\Finkok\FinkokSettings;
use PhpCfdi\Finkok\QuickFinkok;
use PhpCfdi\XmlCancelacion\Models\CancelDocument;

/**
 * Timbrado de CFDI 4.0 vía Finkok (PAC). A diferencia de Facturapi, aquí el
 * CRM construye y sella el XML (CfdiBuilderService) y Finkok sólo lo timbra.
 * El XML timbrado y su PDF se guardan en disco privado y el FiscalDocument
 * queda con source='finkok' — las descargas locales ya saben servirlo.
 */
class FinkokService
{
    public function isConfigured(): bool
    {
        return Setting::get('finkok_username')
            && Setting::get('finkok_password')
            && (new CfdiBuilderService())->isConfigured();
    }

    public function environment(): string
    {
        return Setting::get('finkok_environment', 'demo');
    }

    /** Timbra la orden. Devuelve ['success' => bool, 'data' => ...] como FacturapiService. */
    public function stampInvoice(Order $order): array
    {
        if ($order->isStamped()) {
            return ['success' => false, 'data' => ['message' => 'La orden ya tiene un CFDI activo.']];
        }

        // Idempotencia: si un intento previo dejó un pre-CFDI (p.ej. timbró pero
        // falló el guardado), se reenvía el MISMO XML — Finkok devuelve el mismo
        // UUID en vez de emitir un CFDI duplicado ante el SAT.
        $preCfdiPath = "cfdi/precfdi/order-{$order->id}.xml";
        $disk = Storage::disk('local');

        if ($disk->exists($preCfdiPath)) {
            $preCfdi = $disk->get($preCfdiPath);
        } else {
            $preCfdi = (new CfdiBuilderService())->buildSealedXml($order);
            $disk->put($preCfdiPath, $preCfdi);
        }

        $result = $this->quick()->stamp($preCfdi);

        // Pre-CFDI añejo (Fecha fuera de las 72h del SAT): regenerar y reintentar una vez.
        if (!$result->uuid() && str_contains($result->faultString() . $this->alertText($result), '401')) {
            $preCfdi = (new CfdiBuilderService())->buildSealedXml($order);
            $disk->put($preCfdiPath, $preCfdi);
            $result = $this->quick()->stamp($preCfdi);
        }

        if (!$result->uuid()) {
            $detail = trim($result->faultString() . ' ' . $this->alertText($result));
            Log::error('Finkok stamp failed', ['order' => $order->id, 'detail' => $detail]);

            return ['success' => false, 'data' => ['message' => 'Finkok rechazó el timbrado: ' . ($detail ?: 'error desconocido')]];
        }

        $uuid       = $result->uuid();
        $stampedXml = $result->xml();

        $xmlPath = 'cfdi/finkok/' . $uuid . '.xml';
        Storage::disk('local')->put($xmlPath, $stampedXml);

        $pdfPath = null;
        try {
            $pdfPath = $this->generatePdf($order, $stampedXml, $uuid);
        } catch (\Throwable $e) {
            Log::warning('Finkok: PDF del CFDI no se pudo generar', ['uuid' => $uuid, 'error' => $e->getMessage()]);
        }

        $order->fiscalDocument()->create([
            'source'     => 'finkok',
            'uuid'       => $uuid,
            'xml_path'   => $xmlPath,
            'pdf_path'   => $pdfPath,
            'status'     => 'valid',
            'stamped_at' => $result->date() ? \Carbon\Carbon::parse($result->date()) : now(),
        ]);

        // Éxito completo: el pre-CFDI ya no se necesita para reintentos.
        $disk->delete($preCfdiPath);

        return ['success' => true, 'data' => ['uuid' => $uuid]];
    }

    private function alertText(\PhpCfdi\Finkok\Services\Stamping\StampingResult $result): string
    {
        return implode(' | ', array_map(
            fn ($a) => $a->errorCode() . ': ' . $a->message(),
            iterator_to_array($result->alerts())
        ));
    }

    /** Cancela el CFDI ante el SAT vía Finkok (firma con el CSD). */
    public function cancelInvoice(Order $order, string $motive = '02', ?string $substitutionUuid = null): array
    {
        $doc = $order->fiscalDocument;
        if (!$doc || !$doc->uuid) {
            return ['success' => false, 'data' => ['message' => 'La orden no tiene un CFDI de Finkok que cancelar.']];
        }

        // Motivo 01 exige el UUID del CFDI que SUSTITUYE al cancelado — nunca inventarlo.
        if ($motive === '01' && !$substitutionUuid) {
            return ['success' => false, 'data' => ['message' => 'El motivo 01 requiere el UUID del CFDI que sustituye. Usa el motivo 02 si no hay sustitución.']];
        }

        $credential = (new CfdiBuilderService())->credential();

        $document = match ($motive) {
            '01'    => CancelDocument::newWithErrorsRelated($doc->uuid, $substitutionUuid),
            '03'    => CancelDocument::newNotExecuted($doc->uuid),
            '04'    => CancelDocument::newNormativeToGlobal($doc->uuid),
            default => CancelDocument::newWithErrorsUnrelated($doc->uuid),
        };

        $result = $this->quick()->cancel($credential, $document);

        $cancelled = false;
        foreach ($result->documents() as $d) {
            if (strtoupper($d->uuid()) === strtoupper($doc->uuid)) {
                // 201 = solicitud aceptada, 202 = ya cancelado previamente
                $cancelled = in_array($d->documentStatus(), ['201', '202'], true);
            }
        }

        if (!$cancelled) {
            return ['success' => false, 'data' => ['message' => 'El SAT no aceptó la cancelación (puede requerir aceptación del receptor).']];
        }

        $doc->update([
            'status'              => 'cancelled',
            'cancellation_motive' => $motive,
            'cancelled_at'        => now(),
        ]);

        return ['success' => true, 'data' => []];
    }

    private function quick(): QuickFinkok
    {
        $env = $this->environment() === 'live'
            ? FinkokEnvironment::makeProduction()
            : FinkokEnvironment::makeDevelopment();

        return new QuickFinkok(new FinkokSettings(
            (string) Setting::get('finkok_username'),
            (string) Setting::get('finkok_password'),
            $env
        ));
    }

    /** Representación impresa: Blade + DomPDF con QR del SAT. Devuelve el path guardado. */
    private function generatePdf(Order $order, string $stampedXml, string $uuid): string
    {
        $cfdi = Cfdi::newFromString($stampedXml);
        $node = $cfdi->getNode();
        $tfd  = $node->searchNode('cfdi:Complemento', 'tfd:TimbreFiscalDigital');

        // URL de verificación del SAT (QR oficial)
        $qrUrl     = (new DiscoverExtractor())->extract($cfdi->getDocument());
        $qrPng     = (new PngWriter())->write(new QrCode(data: $qrUrl, size: 240, margin: 0))->getString();
        $qrDataUri = 'data:image/png;base64,' . base64_encode($qrPng);

        $settings = [
            'company_name'  => Setting::get('company_name', ''),
            'company_logo'  => Setting::get('company_logo', ''),
            'company_rfc'   => Setting::get('company_rfc', ''),
            'company_email' => Setting::get('company_email', ''),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.cfdi', [
            'comprobante' => $node,
            'emisor'      => $node->searchNode('cfdi:Emisor'),
            'receptor'    => $node->searchNode('cfdi:Receptor'),
            'conceptos'   => $node->searchNodes('cfdi:Conceptos', 'cfdi:Concepto'),
            'tfd'         => $tfd,
            'uuid'        => $uuid,
            'qr'          => $qrDataUri,
            'order'       => $order,
            'settings'    => $settings,
        ]);

        $pdfPath = 'cfdi/finkok/' . $uuid . '.pdf';
        Storage::disk('local')->put($pdfPath, $pdf->output());

        return $pdfPath;
    }
}
