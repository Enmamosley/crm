@extends('layouts.admin')
@section('title', 'Factura Recurrente #' . $recurringInvoice->id)
@section('header', 'Detalle Programación Recurrente')

@section('actions')
    <a href="{{ route('admin.recurring-invoices.edit', $recurringInvoice) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
        <i class="fas fa-edit mr-1"></i> Editar
    </a>
@endsection

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Información General</h3>
            @if($recurringInvoice->active)
                <span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Activa</span>
            @else
                <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactiva</span>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-500">Cliente</span>
                <p class="font-medium">{{ $recurringInvoice->client->legal_name }}</p>
                <p class="text-gray-500">{{ $recurringInvoice->client->tax_id }}</p>
            </div>
            <div>
                <span class="text-gray-500">Serie</span>
                <p class="font-medium">{{ $recurringInvoice->series }}</p>
            </div>
            <div>
                <span class="text-gray-500">Forma de Pago</span>
                <p class="font-medium">{{ $recurringInvoice->payment_form }}</p>
            </div>
            <div>
                <span class="text-gray-500">Método de Pago</span>
                <p class="font-medium">{{ $recurringInvoice->payment_method }}</p>
            </div>
            <div>
                <span class="text-gray-500">Uso CFDI</span>
                <p class="font-medium">{{ $recurringInvoice->use_cfdi }}</p>
            </div>
            <div>
                <span class="text-gray-500">Timbrado automático</span>
                <p class="font-medium">{{ $recurringInvoice->auto_stamp ? 'Sí' : 'No' }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Montos</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Subtotal</span>
                    <span class="font-medium">${{ number_format($recurringInvoice->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">IVA</span>
                    <span class="font-medium">${{ number_format($recurringInvoice->iva_amount, 2) }}</span>
                </div>
                <hr>
                <div class="flex justify-between text-base">
                    <span class="font-semibold">Total</span>
                    <span class="font-bold text-green-600">${{ number_format($recurringInvoice->total, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Programación</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Frecuencia</span>
                    <span class="font-medium">
                        @switch($recurringInvoice->frequency)
                            @case('monthly') Mensual @break
                            @case('quarterly') Trimestral @break
                            @case('yearly') Anual @break
                        @endswitch
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Día del mes</span>
                    <span class="font-medium">{{ $recurringInvoice->day_of_month }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Próxima emisión</span>
                    <span class="font-medium">{{ $recurringInvoice->next_issue_date->format('d/m/Y') }}</span>
                </div>
                @if($recurringInvoice->end_date)
                <div class="flex justify-between">
                    <span class="text-gray-500">Fecha fin</span>
                    <span class="font-medium">{{ $recurringInvoice->end_date->format('d/m/Y') }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    @if($recurringInvoice->notes)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Notas</h3>
        <p class="text-gray-600 text-sm">{{ $recurringInvoice->notes }}</p>
    </div>
    @endif

    @if($recurringInvoice->quote)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Cotización Vinculada</h3>
        <a href="{{ route('admin.quotes.show', $recurringInvoice->quote) }}" class="text-blue-600 hover:underline">
            {{ $recurringInvoice->quote->quote_number }}
        </a>
    </div>
    @endif

    <div class="flex gap-3">
        <a href="{{ route('admin.recurring-invoices.index') }}" class="px-4 py-2 border rounded-lg hover:bg-gray-50 text-sm">
            <i class="fas fa-arrow-left mr-1"></i> Volver
        </a>
        <form action="{{ route('admin.recurring-invoices.destroy', $recurringInvoice) }}" method="POST" onsubmit="return confirm('¿Eliminar esta programación?')">
            @csrf @method('DELETE')
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                <i class="fas fa-trash mr-1"></i> Eliminar
            </button>
        </form>
    </div>
</div>
@endsection
