@extends('layouts.admin')
@section('title', 'Facturas Recurrentes')
@section('header', 'Facturas Recurrentes')

@section('content')
<div class="mb-6 flex justify-end">
    <a href="{{ route('admin.recurring-invoices.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
        <i class="fas fa-plus mr-1"></i> Nueva Programación
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-4 py-3">Cliente</th>
                <th class="text-center px-4 py-3">Frecuencia</th>
                <th class="text-right px-4 py-3">Total</th>
                <th class="text-center px-4 py-3">Próxima Emisión</th>
                <th class="text-center px-4 py-3">Estado</th>
                <th class="text-center px-4 py-3">Auto-timbrar</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($schedules as $schedule)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">{{ $schedule->client->legal_name }}</td>
                <td class="px-4 py-3 text-center">
                    @php
                        $freqLabels = ['monthly'=>'Mensual','quarterly'=>'Trimestral','yearly'=>'Anual'];
                    @endphp
                    {{ $freqLabels[$schedule->frequency] ?? $schedule->frequency }}
                </td>
                <td class="px-4 py-3 text-right font-semibold">${{ number_format($schedule->total, 2) }}</td>
                <td class="px-4 py-3 text-center">{{ $schedule->next_issue_date->format('d/m/Y') }}</td>
                <td class="px-4 py-3 text-center">
                    @if($schedule->active)
                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Activa</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">Inactiva</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-center">
                    @if($schedule->auto_stamp)
                        <i class="fas fa-check text-green-500"></i>
                    @else
                        <i class="fas fa-times text-gray-300"></i>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.recurring-invoices.edit', $schedule) }}" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i></a>
                    <form method="POST" action="{{ route('admin.recurring-invoices.destroy', $schedule) }}" class="inline" onsubmit="return confirm('¿Eliminar esta programación?')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin programaciones recurrentes.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4 border-t">{{ $schedules->links() }}</div>
</div>
@endsection
