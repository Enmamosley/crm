@extends('layouts.admin')
@section('title', 'Reportes')
@section('header', 'Reportes y Estadísticas')

@section('content')
<div class="mb-6">
    <form method="GET" class="bg-white rounded-lg shadow p-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
            <input type="date" name="from" value="{{ $from }}" class="border rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
            <input type="date" name="to" value="{{ $to }}" class="border rounded-lg px-3 py-2">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
            <i class="fas fa-filter mr-1"></i> Filtrar
        </button>
    </form>
</div>

<!-- Resumen -->
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow p-5 text-center">
        <p class="text-sm text-gray-500">Total Facturado</p>
        <p class="text-2xl font-bold text-green-600">${{ number_format($stats['total_facturado'], 2) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-5 text-center">
        <p class="text-sm text-gray-500">Total Cobrado</p>
        <p class="text-2xl font-bold text-emerald-600">${{ number_format($stats['total_cobrado'], 2) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-5 text-center">
        <p class="text-sm text-gray-500">Facturas Emitidas</p>
        <p class="text-2xl font-bold text-blue-600">{{ $stats['facturas_emitidas'] }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-5 text-center">
        <p class="text-sm text-gray-500">Facturas Pendientes</p>
        <p class="text-2xl font-bold text-orange-600">{{ $stats['facturas_pendientes'] }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-5 text-center">
        <p class="text-sm text-gray-500">Pagos Recibidos</p>
        <p class="text-2xl font-bold text-purple-600">{{ $stats['pagos_recibidos'] }}</p>
    </div>
</div>

<!-- Exportar -->
<div class="bg-white rounded-lg shadow p-6 mb-8">
    <h3 class="text-lg font-semibold mb-4"><i class="fas fa-download mr-2 text-gray-500"></i>Exportar a CSV</h3>
    <div class="flex gap-3">
        <a href="{{ route('admin.reports.export.invoices', ['from' => $from, 'to' => $to]) }}"
           class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">
            <i class="fas fa-file-csv mr-1"></i> Facturas
        </a>
        <a href="{{ route('admin.reports.export.payments', ['from' => $from, 'to' => $to]) }}"
           class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
            <i class="fas fa-file-csv mr-1"></i> Pagos
        </a>
        <a href="{{ route('admin.reports.export.leads', ['from' => $from, 'to' => $to]) }}"
           class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm">
            <i class="fas fa-file-csv mr-1"></i> Leads
        </a>
    </div>
</div>

<!-- Gráficas -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Ingresos por Día</h3>
        <canvas id="paymentsChart" height="200"></canvas>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Facturas por Estado</h3>
        <canvas id="invoicesChart" height="200"></canvas>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Leads por Estado</h3>
        <canvas id="leadsChart" height="200"></canvas>
    </div>
</div>

<!-- Detalle de facturas -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-8">
    <div class="p-6 border-b">
        <h3 class="text-lg font-semibold">Facturas del Período</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-4 py-3">Folio</th>
                    <th class="text-left px-4 py-3">Cliente</th>
                    <th class="text-right px-4 py-3">Total</th>
                    <th class="text-center px-4 py-3">Estado</th>
                    <th class="text-center px-4 py-3">Pagada</th>
                    <th class="text-right px-4 py-3">Fecha</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($invoices as $invoice)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium">{{ $invoice->folio() ?: '-' }}</td>
                    <td class="px-4 py-3">{{ $invoice->client->legal_name ?? '-' }}</td>
                    <td class="px-4 py-3 text-right">${{ number_format($invoice->total, 2) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-xs font-medium
                            {{ $invoice->status === 'valid' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $invoice->status === 'draft' ? 'bg-gray-100 text-gray-700' : '' }}
                            {{ $invoice->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}
                        ">{{ ucfirst($invoice->status) }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($invoice->paid_at)
                            <span class="text-green-600"><i class="fas fa-check"></i></span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right text-gray-500">{{ $invoice->created_at->format('d/m/Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Sin facturas en este período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Detalle de pagos -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-semibold">Pagos del Período</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-4 py-3">ID</th>
                    <th class="text-left px-4 py-3">Factura</th>
                    <th class="text-left px-4 py-3">Cliente</th>
                    <th class="text-right px-4 py-3">Monto</th>
                    <th class="text-center px-4 py-3">Tipo</th>
                    <th class="text-right px-4 py-3">Fecha</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($payments as $payment)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium">#{{ $payment->id }}</td>
                    <td class="px-4 py-3">{{ $payment->order?->folio() ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $payment->order?->client->legal_name ?? '-' }}</td>
                    <td class="px-4 py-3 text-right font-semibold">${{ number_format($payment->amount, 2) }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $payment->payment_type ?? '-' }}</td>
                    <td class="px-4 py-3 text-right text-gray-500">{{ $payment->paid_at?->format('d/m/Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Sin pagos en este período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const colors = ['#3b82f6','#22c55e','#eab308','#ef4444','#a855f7','#f97316','#06b6d4','#ec4899'];

// Ingresos por día
new Chart(document.getElementById('paymentsChart'), {
    type: 'bar',
    data: {
        labels: @json($charts['payments_labels']),
        datasets: [{
            label: 'Ingresos ($)',
            data: @json($charts['payments_data']),
            backgroundColor: '#3b82f6',
            borderRadius: 4,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

// Facturas por estado
new Chart(document.getElementById('invoicesChart'), {
    type: 'doughnut',
    data: {
        labels: @json($charts['invoice_labels']),
        datasets: [{
            data: @json($charts['invoice_data']),
            backgroundColor: colors,
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// Leads por estado
new Chart(document.getElementById('leadsChart'), {
    type: 'doughnut',
    data: {
        labels: @json($charts['leads_labels']),
        datasets: [{
            data: @json($charts['leads_data']),
            backgroundColor: ['#3b82f6','#eab308','#a855f7','#22c55e','#ef4444'],
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
@endpush
