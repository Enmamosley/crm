<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Lead;
use App\Models\Payment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);

        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', now()->format('Y-m-d'));

        $invoices = Order::with('client')
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"])
            ->get();

        $payments = Payment::with('order.client')
            ->where('status', 'approved')
            ->whereBetween('paid_at', [$from, "{$to} 23:59:59"])
            ->get();

        $stats = [
            'total_facturado'    => $invoices->whereNotNull('paid_at')->sum('total'),
            'total_cobrado'      => $payments->sum('amount'),
            'facturas_emitidas'  => $invoices->count(),
            'facturas_pendientes' => $invoices->whereNull('paid_at')->whereIn('status', ['sent', 'draft'])->count(),
            'pagos_recibidos'    => $payments->count(),
        ];

        // Chart data: payments grouped by day
        $paymentsChart = $payments->groupBy(fn ($p) => $p->paid_at->format('Y-m-d'))
            ->map(fn ($group) => round($group->sum('amount'), 2))
            ->sortKeys();

        // Chart data: invoices by status
        $invoicesByStatus = $invoices->groupBy('status')
            ->map(fn ($group) => $group->count());

        // Chart data: leads by status (global)
        $leadsByStatus = Lead::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $charts = [
            'payments_labels' => $paymentsChart->keys()->values(),
            'payments_data'   => $paymentsChart->values(),
            'invoice_labels'  => $invoicesByStatus->keys()->values(),
            'invoice_data'    => $invoicesByStatus->values(),
            'leads_labels'    => $leadsByStatus->keys()->values(),
            'leads_data'      => $leadsByStatus->values(),
        ];

        return view('admin.reports.index', compact('from', 'to', 'stats', 'invoices', 'payments', 'charts'));
    }

    public function exportInvoices(Request $request): StreamedResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);

        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', now()->format('Y-m-d'));

        $invoices = Order::with('client')
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"])
            ->get();

        return $this->streamCsv("facturas_{$from}_{$to}.csv", [
            'Folio', 'Cliente', 'RFC', 'Subtotal', 'IVA', 'Total', 'Estado', 'Método Pago', 'Pagada', 'Timbrada', 'Creada',
        ], $invoices->map(fn($i) => [
            $i->folio(),
            $i->client->legal_name ?? '',
            $i->client->tax_id ?? '',
            $i->subtotal,
            $i->iva_amount,
            $i->total,
            $i->status,
            $i->payment_method,
            $i->paid_at?->format('d/m/Y') ?? 'Pendiente',
            $i->stamped_at?->format('d/m/Y') ?? 'No',
            $i->created_at->format('d/m/Y'),
        ]));
    }

    public function exportPayments(Request $request): StreamedResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);

        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', now()->format('Y-m-d'));

        $payments = Payment::with('invoice.client')
            ->where('status', 'approved')
            ->whereBetween('paid_at', [$from, "{$to} 23:59:59"])
            ->get();

        return $this->streamCsv("pagos_{$from}_{$to}.csv", [
            'ID', 'Factura', 'Cliente', 'Monto', 'Moneda', 'Tipo Pago', 'MP ID', 'Fecha Pago',
        ], $payments->map(fn($p) => [
            $p->id,
            $p->invoice->folio(),
            $p->invoice->client->legal_name ?? '',
            $p->amount,
            $p->currency,
            $p->payment_type,
            $p->mp_payment_id,
            $p->paid_at?->format('d/m/Y H:i'),
        ]));
    }

    public function exportLeads(Request $request): StreamedResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);

        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', now()->format('Y-m-d'));

        $leads = Lead::whereBetween('created_at', [$from, "{$to} 23:59:59"])->get();

        return $this->streamCsv("leads_{$from}_{$to}.csv", [
            'ID', 'Nombre', 'Email', 'Teléfono', 'Empresa', 'Fuente', 'Estado', 'Creado',
        ], $leads->map(fn($l) => [
            $l->id,
            $l->name,
            $l->email,
            $l->phone,
            $l->business,
            $l->source,
            $l->status,
            $l->created_at->format('d/m/Y'),
        ]));
    }

    private function streamCsv(string $filename, array $headers, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array) $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
