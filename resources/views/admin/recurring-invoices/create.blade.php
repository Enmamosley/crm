@extends('layouts.admin')
@section('title', 'Nueva Factura Recurrente')
@section('header', 'Crear Factura Recurrente')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.recurring-invoices.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                    <select name="client_id" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Seleccionar cliente</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id', $selectedClient?->id) == $client->id ? 'selected' : '' }}>
                                {{ $client->legal_name }} ({{ $client->tax_id }})
                            </option>
                        @endforeach
                    </select>
                    @error('client_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Serie</label>
                    <input type="text" name="series" value="{{ old('series', 'F') }}" required class="w-full border rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Forma de Pago</label>
                    <select name="payment_form" required class="w-full border rounded-lg px-3 py-2">
                        <option value="03" {{ old('payment_form') === '03' ? 'selected' : '' }}>03 - Transferencia</option>
                        <option value="04" {{ old('payment_form') === '04' ? 'selected' : '' }}>04 - Tarjeta crédito</option>
                        <option value="28" {{ old('payment_form') === '28' ? 'selected' : '' }}>28 - Tarjeta débito</option>
                        <option value="01" {{ old('payment_form') === '01' ? 'selected' : '' }}>01 - Efectivo</option>
                        <option value="99" {{ old('payment_form') === '99' ? 'selected' : '' }}>99 - Por definir</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Método de Pago</label>
                    <select name="payment_method" required class="w-full border rounded-lg px-3 py-2">
                        <option value="PUE" {{ old('payment_method') === 'PUE' ? 'selected' : '' }}>PUE - Pago en una exhibición</option>
                        <option value="PPD" {{ old('payment_method') === 'PPD' ? 'selected' : '' }}>PPD - Pago en parcialidades</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Uso CFDI</label>
                    <select name="use_cfdi" required class="w-full border rounded-lg px-3 py-2">
                        <option value="G03">G03 - Gastos en general</option>
                        <option value="G01">G01 - Adquisición de mercancías</option>
                        <option value="I04">I04 - Equipo de cómputo</option>
                        <option value="S01">S01 - Sin efectos fiscales</option>
                    </select>
                </div>
            </div>

            <hr class="mb-6">
            <h3 class="font-semibold text-gray-800 mb-4">Montos</h3>

            <div class="grid grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subtotal *</label>
                    <input type="number" name="subtotal" value="{{ old('subtotal') }}" step="0.01" min="0" required class="w-full border rounded-lg px-3 py-2" id="subtotal">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IVA *</label>
                    <input type="number" name="iva_amount" value="{{ old('iva_amount') }}" step="0.01" min="0" required class="w-full border rounded-lg px-3 py-2" id="iva_amount">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Total *</label>
                    <input type="number" name="total" value="{{ old('total') }}" step="0.01" min="0" required class="w-full border rounded-lg px-3 py-2" id="total">
                </div>
            </div>

            <hr class="mb-6">
            <h3 class="font-semibold text-gray-800 mb-4">Programación</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Frecuencia *</label>
                    <select name="frequency" required class="w-full border rounded-lg px-3 py-2">
                        <option value="monthly" {{ old('frequency') === 'monthly' ? 'selected' : '' }}>Mensual</option>
                        <option value="quarterly" {{ old('frequency') === 'quarterly' ? 'selected' : '' }}>Trimestral</option>
                        <option value="yearly" {{ old('frequency') === 'yearly' ? 'selected' : '' }}>Anual</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Día del mes</label>
                    <input type="number" name="day_of_month" value="{{ old('day_of_month', 1) }}" min="1" max="28" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Próxima emisión *</label>
                    <input type="date" name="next_issue_date" value="{{ old('next_issue_date') }}" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha fin <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>

            <div class="flex items-center gap-4 mb-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="auto_stamp" value="0">
                    <input type="checkbox" name="auto_stamp" value="1" {{ old('auto_stamp') ? 'checked' : '' }} class="rounded">
                    <span class="text-sm text-gray-700">Timbrar automáticamente al generar</span>
                </label>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                <textarea name="notes" rows="2" class="w-full border rounded-lg px-3 py-2">{{ old('notes') }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Crear Programación</button>
                <a href="{{ route('admin.recurring-invoices.index') }}" class="px-6 py-2 border rounded-lg hover:bg-gray-50">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('subtotal')?.addEventListener('input', calcTotal);
function calcTotal() {
    const sub = parseFloat(document.getElementById('subtotal').value) || 0;
    const iva = sub * {{ $ivaPercentage / 100 }};
    document.getElementById('iva_amount').value = iva.toFixed(2);
    document.getElementById('total').value = (sub + iva).toFixed(2);
}
</script>
@endsection
