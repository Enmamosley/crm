@extends('layouts.admin')
@section('title', 'Editar Factura Recurrente')
@section('header', 'Editar Programación Recurrente')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.recurring-invoices.update', $recurringInvoice) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                    <p class="px-3 py-2 bg-gray-50 border rounded-lg text-gray-600">{{ $recurringInvoice->client->legal_name }} ({{ $recurringInvoice->client->tax_id }})</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Serie</label>
                    <input type="text" name="series" value="{{ old('series', $recurringInvoice->series) }}" required class="w-full border rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Forma de Pago</label>
                    <select name="payment_form" required class="w-full border rounded-lg px-3 py-2">
                        @foreach(['03' => '03 - Transferencia', '04' => '04 - Tarjeta crédito', '28' => '28 - Tarjeta débito', '01' => '01 - Efectivo', '99' => '99 - Por definir'] as $val => $label)
                            <option value="{{ $val }}" {{ old('payment_form', $recurringInvoice->payment_form) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de Pago</label>
                    <select name="payment_method" required class="w-full border rounded-lg px-3 py-2">
                        <option value="PUE" {{ old('payment_method', $recurringInvoice->payment_method) === 'PUE' ? 'selected' : '' }}>PUE - Pago en una exhibición</option>
                        <option value="PPD" {{ old('payment_method', $recurringInvoice->payment_method) === 'PPD' ? 'selected' : '' }}>PPD - Pago en parcialidades</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Uso CFDI</label>
                    <select name="use_cfdi" required class="w-full border rounded-lg px-3 py-2">
                        @foreach(['G03' => 'G03 - Gastos en general', 'G01' => 'G01 - Adquisición de mercancías', 'I04' => 'I04 - Equipo de cómputo', 'S01' => 'S01 - Sin efectos fiscales'] as $val => $label)
                            <option value="{{ $val }}" {{ old('use_cfdi', $recurringInvoice->use_cfdi) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <hr class="mb-6">
            <h3 class="font-semibold text-gray-800 mb-4">Montos</h3>

            <div class="grid grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subtotal</label>
                    <input type="number" name="subtotal" value="{{ old('subtotal', $recurringInvoice->subtotal) }}" step="0.01" min="0" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IVA</label>
                    <input type="number" name="iva_amount" value="{{ old('iva_amount', $recurringInvoice->iva_amount) }}" step="0.01" min="0" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Total</label>
                    <input type="number" name="total" value="{{ old('total', $recurringInvoice->total) }}" step="0.01" min="0" required class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>

            <hr class="mb-6">
            <h3 class="font-semibold text-gray-800 mb-4">Programación</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Frecuencia</label>
                    <select name="frequency" required class="w-full border rounded-lg px-3 py-2">
                        <option value="monthly" {{ old('frequency', $recurringInvoice->frequency) === 'monthly' ? 'selected' : '' }}>Mensual</option>
                        <option value="quarterly" {{ old('frequency', $recurringInvoice->frequency) === 'quarterly' ? 'selected' : '' }}>Trimestral</option>
                        <option value="yearly" {{ old('frequency', $recurringInvoice->frequency) === 'yearly' ? 'selected' : '' }}>Anual</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Día del mes</label>
                    <input type="number" name="day_of_month" value="{{ old('day_of_month', $recurringInvoice->day_of_month) }}" min="1" max="28" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Próxima emisión</label>
                    <input type="date" name="next_issue_date" value="{{ old('next_issue_date', $recurringInvoice->next_issue_date?->format('Y-m-d')) }}" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha fin <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="date" name="end_date" value="{{ old('end_date', $recurringInvoice->end_date?->format('Y-m-d')) }}" class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>

            <div class="flex items-center gap-6 mb-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="auto_stamp" value="0">
                    <input type="checkbox" name="auto_stamp" value="1" {{ old('auto_stamp', $recurringInvoice->auto_stamp) ? 'checked' : '' }} class="rounded">
                    <span class="text-sm text-gray-700">Timbrar automáticamente</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" {{ old('active', $recurringInvoice->active) ? 'checked' : '' }} class="rounded">
                    <span class="text-sm text-gray-700">Activa</span>
                </label>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                <textarea name="notes" rows="2" class="w-full border rounded-lg px-3 py-2">{{ old('notes', $recurringInvoice->notes) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Guardar Cambios</button>
                <a href="{{ route('admin.recurring-invoices.index') }}" class="px-6 py-2 border rounded-lg hover:bg-gray-50">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
