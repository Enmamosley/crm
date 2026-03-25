@extends('layouts.admin')
@section('title', 'Facturas')
@section('header', 'Facturas')

@section('actions')
<a href="{{ route('admin.invoices.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
    <i class="fas fa-plus mr-1"></i> Nueva Factura
</a>
@endsection

@section('content')
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Folio</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Cliente</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Cotización</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Total</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Estado</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Fecha</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($invoices as $invoice)
                @php
                    $colors = ['draft'=>'gray','sent'=>'blue','pending'=>'yellow','valid'=>'green','cancelled'=>'red'];
                    $labels = ['draft'=>'Borrador','sent'=>'Pagada','pending'=>'Procesando','valid'=>'Timbrada','cancelled'=>'Cancelada'];
                    $c = $colors[$invoice->status] ?? 'gray';
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-mono font-medium text-sm">{{ $invoice->folio() ?: '-' }}</td>
                    <td class="px-6 py-4 text-sm">{{ $invoice->client?->legal_name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $invoice->quote?->quote_number ?? '-' }}</td>
                    <td class="px-6 py-4 text-right font-medium">${{ number_format($invoice->total, 2) }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                            {{ $labels[$invoice->status] ?? $invoice->status }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-xs text-gray-500">{{ $invoice->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 text-right space-x-2">
                        @if($invoice->isStamped())
                            <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="text-red-600 hover:text-red-800 text-sm" title="Descargar PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <a href="{{ route('admin.invoices.xml', $invoice) }}" class="text-orange-500 hover:text-orange-700 text-sm" title="Descargar XML">
                                <i class="fas fa-file-code"></i>
                            </a>
                        @endif
                        <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-blue-600 hover:text-blue-800 text-sm">Ver</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400">No hay facturas aún.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($invoices->hasPages())
        <div class="px-6 py-4 border-t">{{ $invoices->links() }}</div>
    @endif
</div>
@endsection
