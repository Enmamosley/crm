@extends('layouts.admin')
@section('title', 'Cotizaciones')
@section('header', 'Cotizaciones')

@section('actions')
<a href="{{ route('admin.quotes.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
    <i class="fas fa-plus mr-1"></i> Nueva Cotización
</a>
@endsection

@section('content')
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Número</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Lead</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Total</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Estado</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Vigencia</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Fecha</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($quotes as $quote)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <a href="{{ route('admin.quotes.show', $quote) }}" class="font-medium text-blue-600 hover:underline">{{ $quote->quote_number }}</a>
                    </td>
                    <td class="px-6 py-4 text-sm">{{ $quote->lead->name ?? 'N/A' }}</td>
                    <td class="px-6 py-4 font-semibold">${{ number_format($quote->total, 2) }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full
                            {{ $quote->status === 'borrador' ? 'bg-gray-100 text-gray-600' : '' }}
                            {{ $quote->status === 'enviada' ? 'bg-blue-100 text-blue-600' : '' }}
                            {{ $quote->status === 'aceptada' ? 'bg-green-100 text-green-600' : '' }}
                            {{ $quote->status === 'rechazada' ? 'bg-red-100 text-red-600' : '' }}
                            {{ $quote->status === 'vencida' ? 'bg-yellow-100 text-yellow-600' : '' }}
                        ">{{ ucfirst($quote->status) }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm {{ $quote->isExpired() ? 'text-red-500' : 'text-gray-500' }}">
                        {{ $quote->valid_until->format('d/m/Y') }}
                        @if($quote->isExpired()) <span class="text-xs">(vencida)</span> @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $quote->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <a href="{{ route('admin.quotes.pdf', $quote) }}" class="text-gray-500 hover:text-green-600" title="Descargar PDF"><i class="fas fa-file-pdf"></i></a>
                        <a href="{{ route('admin.quotes.edit', $quote) }}" class="text-gray-500 hover:text-blue-600"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('admin.quotes.destroy', $quote) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?')">
                            @csrf @method('DELETE')
                            <button class="text-gray-500 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">No hay cotizaciones.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-6 py-4 border-t">{{ $quotes->links() }}</div>
</div>
@endsection
