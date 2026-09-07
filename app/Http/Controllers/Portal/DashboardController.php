<?php

namespace App\Http\Controllers\Portal;

use App\Models\ClientDocument;
use App\Models\Order;
use App\Models\Setting;
use App\Services\FacturapiService;
use App\Services\TwentyIService;
use App\Support\FileResponse;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Panel del cliente y descargas: facturas (PDF/XML), recibo de pago y documentos.
 */

class DashboardController extends PortalController
{
    public function __construct(
        private FacturapiService $facturapi,
        private TwentyIService $twentyi,
    ) {}

    public function show(string $token)
    {
        $client = $this->client()
            ->load(['lead.quotes', 'invoices.quote', 'invoices.fiscalDocument', 'documents', 'clientServices.service']);

        $mailboxes = [];
        $emailDomain = null;
        $hasEmailService = $client->hasEmailService();

        if ($hasEmailService && $client->twentyi_package_id && Setting::get('twentyi_api_key')) {
            try {
                $service     = $this->twentyi;
                $emailDomain = $service->getDomain($client);
                $mailboxes   = $service->listMailboxes($client);
            } catch (\Throwable) {}
        }

        $awaitingDomain = $client->awaitsDomain();

        return view('portal.dashboard', compact('client', 'mailboxes', 'emailDomain', 'hasEmailService', 'awaitingDomain'));
    }

    public function downloadInvoicePdf(string $token, Order $order)
    {
        $client = $this->client();

        $this->authorizeOwnership($client, $order);

        if (!$order->isStamped()) {
            abort(404, 'Factura no disponible.');
        }

        // CFDI con archivos locales (externo o Finkok): servir el guardado
        $doc = $order->fiscalDocument;
        if ($doc && $doc->pdf_path) {
            return \App\Support\FileResponse::download('local', $doc->pdf_path, 'factura-' . $order->folio() . '.pdf', 'application/pdf');
        }
        if ($doc && $doc->source !== 'facturapi') {
            abort(404, 'Esta factura no tiene PDF disponible.');
        }

        $pdf = $this->facturapi->downloadPdf($order);

        if (!$pdf) {
            abort(500, 'No se pudo obtener el PDF.');
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="factura-' . $order->folio() . '.pdf"',
        ]);
    }

    /** Recibo de pago (no fiscal) descargable — para cualquier orden pagada, tenga o no CFDI. */
    public function downloadReceipt(string $token, Order $order)
    {
        $client = $this->client();

        $this->authorizeOwnership($client, $order);
        abort_unless($order->isPaid(), 404, 'Recibo disponible sólo para pagos confirmados.');

        $settings = [
            'company_name'    => Setting::get('company_name', ''),
            'company_logo'    => Setting::get('company_logo', ''),
            'company_rfc'     => Setting::get('company_rfc', ''),
            'company_address' => Setting::get('company_address', ''),
            'company_phone'   => Setting::get('company_phone', ''),
            'company_email'   => Setting::get('company_email', ''),
        ];

        $payment = $order->payments()->where('status', 'approved')->latest('paid_at')->first();
        $ref = $order->folio_number ? $order->folio() : ('ORD-' . $order->id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', compact('order', 'client', 'settings', 'payment', 'ref'));

        return $pdf->download('recibo-' . $ref . '.pdf');
    }

    public function downloadInvoiceXml(string $token, Order $order)
    {
        $client = $this->client();

        $this->authorizeOwnership($client, $order);

        if (!$order->isStamped()) {
            abort(404, 'Factura no disponible.');
        }

        // CFDI con archivos locales (externo o Finkok): servir el guardado
        $doc = $order->fiscalDocument;
        if ($doc && ($doc->xml_path || $doc->source !== 'facturapi')) {
            return \App\Support\FileResponse::download('local', $doc->xml_path, 'factura-' . $order->folio() . '.xml', 'application/xml');
        }

        $xml = $this->facturapi->downloadXml($order);

        if (!$xml) {
            abort(500, 'No se pudo obtener el XML.');
        }

        return response($xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="factura-' . $order->folio() . '.xml"',
        ]);
    }

    public function downloadDocument(string $token, ClientDocument $document)
    {
        $client = $this->client();

        $this->authorizeOwnership($client, $document);

        return \App\Support\FileResponse::download('local', $document->file_path, $document->name, $document->file_type);
    }
}
