@extends('layouts.admin')
@section('title', 'Nueva Factura Recurrente')
@section('header', 'Crear Factura Recurrente')

@section('content')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('recurringForm', () => ({
        services: @json($services),
        items: @json(old('items', [[
            'description'     => '',
            'quantity'        => 1,
            'unit_price'      => '',
            'sat_product_key' => '80101501',
            'sat_unit_key'    => 'E48',
            'sat_unit_name'   => 'Servicio',
            'tax_object'      => '02',
            'iva_exempt'      => false,
        ]])),
        ivaRate: {{ $ivaPercentage / 100 }},
        get subtotal() { return this.items.reduce((s,i) => s + (parseFloat(i.quantity)||0) * (parseFloat(i.unit_price)||0), 0); },
        get iva()      { return this.subtotal * this.ivaRate; },
        get total()    { return this.subtotal + this.iva; },
        addItem() { this.items.push({description:'',quantity:1,unit_price:'',sat_product_key:'80101501',sat_unit_key:'E48',sat_unit_name:'Servicio',tax_object:'02',iva_exempt:false}); },
        removeItem(i) { if(this.items.length > 1) this.items.splice(i, 1); },
        fillFromService(i, id) {
            const s = this.services.find(sv => sv.id == id);
            if (s) { this.items[i].description = s.name; this.items[i].unit_price = s.price; }
        },
        fmt(n) { return new Intl.NumberFormat('es-MX',{minimumFractionDigits:2}).format(n||0); }
    }));
});
</script>

<form method="POST" action="{{ route('admin.recurring-invoices.store') }}" class="max-w-3xl space-y-6" x-data="recurringForm">
    @csrf

    {{-- Datos generales --}}
    <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Datos generales</h3>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
            <select name="client_id" required class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Seleccionar cliente</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{ old('client_id', $selectedClient?->id) == $client->id ? 'selected' : '' }}>
                        {{ $client->name ?? $client->legal_name }} — {{ $client->tax_id }}
                    </option>
                @endforeach
            </select>
            @error('client_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Serie</label>
                <input type="text" name="series" value="{{ old('series', 'F') }}" required maxlength="10"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Forma de Pago</label>
                <select name="payment_form" required class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="03" {{ old('payment_form','03')==='03' ? 'selected':'' }}>03 - Transferencia</option>
                    <option value="04" {{ old('payment_form')==='04' ? 'selected':'' }}>04 - Tarjeta crédito</option>
                    <option value="28" {{ old('payment_form')==='28' ? 'selected':'' }}>28 - Tarjeta débito</option>
                    <option value="01" {{ old('payment_form')==='01' ? 'selected':'' }}>01 - Efectivo</option>
                    <option value="99" {{ old('payment_form')==='99' ? 'selected':'' }}>99 - Por definir</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Método de Pago</label>
                <select name="payment_method" required class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="PUE" {{ old('payment_method','PUE')==='PUE' ? 'selected':'' }}>PUE - Pago en una exhibición</option>
                    <option value="PPD" {{ old('payment_method')==='PPD' ? 'selected':'' }}>PPD - Pago en parcialidades</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Uso CFDI</label>
                <select name="use_cfdi" required class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="G03" {{ old('use_cfdi','G03')==='G03' ? 'selected':'' }}>G03 - Gastos en general</option>
                    <option value="G01" {{ old('use_cfdi')==='G01' ? 'selected':'' }}>G01 - Adquisición de mercancías</option>
                    <option value="I04" {{ old('use_cfdi')==='I04' ? 'selected':'' }}>I04 - Equipo de cómputo</option>
                    <option value="S01" {{ old('use_cfdi')==='S01' ? 'selected':'' }}>S01 - Sin efectos fiscales</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Servicios / Ítems --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">Servicios a facturar</h3>
            <button type="button" @click="addItem()"
                class="text-xs bg-blue-50 text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-100">
                + Agregar ítem
            </button>
        </div>
        @error('items') <p class="text-red-500 text-xs mb-3">{{ $message }}</p> @enderror

        <div class="space-y-4">
            <template x-for="(item, i) in items" :key="i">
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 relative">
                    <button type="button" @click="removeItem(i)" x-show="items.length > 1"
                        class="absolute top-2 right-2 text-red-400 hover:text-red-600 text-xs font-bold">✕</button>

                    @if($services->isNotEmpty())
                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Servicio predefinido (opcional)</label>
                        <select class="w-full border rounded px-2 py-1.5 text-sm bg-white"
                            @change="fillFromService(i, $event.target.value)">
                            <option value="">— Seleccionar servicio —</option>
                            @foreach($services as $svc)
                                <option value="{{ $svc->id }}">{{ $svc->name }} (${{ number_format($svc->price, 2) }})</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-3">
                            <label class="block text-xs text-gray-500 mb-1">Descripción *</label>
                            <input type="text" :name="`items[${i}][description]`" x-model="item.description"
                                required class="w-full border rounded px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Cantidad *</label>
                            <input type="number" :name="`items[${i}][quantity]`" x-model="item.quantity"
                                min="0.001" step="0.001" required class="w-full border rounded px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Precio unitario *</label>
                            <input type="number" :name="`items[${i}][unit_price]`" x-model="item.unit_price"
                                min="0" step="0.01" required class="w-full border rounded px-2 py-1.5 text-sm">
                        </div>
                        <div class="flex items-end">
                            <p class="text-sm text-gray-700 pb-1.5">
                                = <span class="font-semibold" x-text="fmt((parseFloat(item.quantity)||0)*(parseFloat(item.unit_price)||0))"></span>
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Clave SAT</label>
                            <input type="text" :name="`items[${i}][sat_product_key]`" x-model="item.sat_product_key"
                                class="w-full border rounded px-2 py-1.5 text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Clave unidad</label>
                            <input type="text" :name="`items[${i}][sat_unit_key]`" x-model="item.sat_unit_key"
                                class="w-full border rounded px-2 py-1.5 text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Nombre unidad</label>
                            <input type="text" :name="`items[${i}][sat_unit_name]`" x-model="item.sat_unit_name"
                                class="w-full border rounded px-2 py-1.5 text-sm">
                        </div>
                    </div>
                    <input type="hidden" :name="`items[${i}][tax_object]`" x-model="item.tax_object">
                    <input type="hidden" :name="`items[${i}][iva_exempt]`" :value="item.iva_exempt ? 1 : 0">
                </div>
            </template>
        </div>

        {{-- Totales --}}
        <div class="mt-4 border-t pt-4 text-sm text-right space-y-1">
            <p class="text-gray-500">Subtotal: <span class="font-medium text-gray-800" x-text="'$' + fmt(subtotal)"></span></p>
            <p class="text-gray-500">IVA ({{ $ivaPercentage }}%): <span class="font-medium text-gray-800" x-text="'$' + fmt(iva)"></span></p>
            <p class="font-bold text-base">Total: <span class="text-green-700" x-text="'$' + fmt(total)"></span></p>
        </div>
    </div>

    {{-- Programación --}}
    <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Programación</h3>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Frecuencia *</label>
                <select name="frequency" required class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="monthly"   {{ old('frequency','monthly')==='monthly'   ? 'selected':'' }}>Mensual</option>
                    <option value="quarterly" {{ old('frequency')==='quarterly' ? 'selected':'' }}>Trimestral</option>
                    <option value="yearly"    {{ old('frequency')==='yearly'    ? 'selected':'' }}>Anual</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Día del mes</label>
                <input type="number" name="day_of_month" value="{{ old('day_of_month', 1) }}"
                    min="1" max="28" required class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Próxima emisión *</label>
                <input type="date" name="next_issue_date" value="{{ old('next_issue_date') }}"
                    required class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha fin <span class="text-gray-400 font-normal">(opcional)</span></label>
                <input type="date" name="end_date" value="{{ old('end_date') }}"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div>
            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                <input type="hidden" name="auto_stamp" value="0">
                <input type="checkbox" name="auto_stamp" value="1" {{ old('auto_stamp') ? 'checked':'' }} class="rounded">
                Timbrar automáticamente al generar
            </label>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
            <textarea name="notes" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm">{{ old('notes') }}</textarea>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
            Crear Programación
        </button>
        <a href="{{ route('admin.recurring-invoices.index') }}" class="px-6 py-2 border rounded-lg hover:bg-gray-50">
            Cancelar
        </a>
    </div>
</form>
@endsection
