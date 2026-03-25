@extends('layouts.admin')
@section('title', 'Nueva Factura')
@section('header', 'Nueva Factura')

@section('content')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('invoiceForm', () => ({
        quoteId: '{{ old('quote_id', $quote?->id ?? '') }}',
        services: @json($services),
        items: @json(old('items', [['description'=>'','quantity'=>1,'unit_price'=>'','sat_product_key'=>'80101501','sat_unit_key'=>'E48','sat_unit_name'=>'Servicio','tax_object'=>'02','iva_exempt'=>false]])),
        ivaRate: {{ (float)(\App\Models\Setting::get('iva_percentage', 16)) / 100 }},
        get subtotal() { return this.items.reduce((s,i) => s + (parseFloat(i.quantity)||0) * (parseFloat(i.unit_price)||0), 0); },
        get iva()      { return this.subtotal * this.ivaRate; },
        get total()    { return this.subtotal + this.iva; },
        addItem() { this.items.push({description:'',quantity:1,unit_price:'',sat_product_key:'80101501',sat_unit_key:'E48',sat_unit_name:'Servicio',tax_object:'02',iva_exempt:false}); },
        removeItem(i) { if(this.items.length > 1) this.items.splice(i, 1); },
        fmt(n) { return new Intl.NumberFormat('es-MX',{minimumFractionDigits:2}).format(n||0); }
    }));
});
</script>
<form action="{{ route('admin.invoices.store') }}" method="POST" class="max-w-2xl mx-auto space-y-6"
      x-data="invoiceForm">
    @csrf

    <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <h3 class="text-lg font-semibold">Datos de la Factura</h3>

        <div>
            <label class="block text-sm text-gray-600 mb-1">Cliente <span class="text-red-500">*</span></label>
            <select name="client_id" required id="clientSelect"
                class="w-full border rounded-lg px-3 py-2 text-sm @error('client_id') border-red-500 @enderror">
                <option value="">Selecciona un cliente...</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ old('client_id', $client?->id) == $c->id ? 'selected' : '' }}>
                        {{ $c->legal_name }} — {{ $c->tax_id }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm text-gray-600 mb-1">Cotización asociada (opcional)</label>
            <select name="quote_id" x-model="quoteId" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Ninguna — ingresar ítems manualmente</option>
                @if($quote)
                    <option value="{{ $quote->id }}" selected>{{ $quote->quote_number }} — ${{ number_format($quote->total, 2) }}</option>
                @endif
            </select>
            <p class="text-xs text-gray-400 mt-1">Si seleccionas una cotización, los montos y productos se toman de ella. De lo contrario agrega los ítems abajo.</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Serie</label>
                <input type="text" name="series" value="{{ old('series', 'F') }}" maxlength="10"
                    class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Folio</label>
                <input type="number" name="folio_number" value="{{ old('folio_number') }}" min="1"
                    class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Auto">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Forma de Pago <span class="text-red-500">*</span></label>
                <select name="payment_form" required class="w-full border rounded-lg px-3 py-2 text-sm">
                    @foreach($paymentForms as $code => $label)
                        <option value="{{ $code }}" {{ old('payment_form', '03') === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Método de Pago <span class="text-red-500">*</span></label>
                <select name="payment_method" required class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="PUE" {{ old('payment_method','PUE')==='PUE'?'selected':'' }}>PUE - Una sola exhibición</option>
                    <option value="PPD" {{ old('payment_method')==='PPD'?'selected':'' }}>PPD - Parcialidades o diferido</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm text-gray-600 mb-1">Uso CFDI <span class="text-red-500">*</span></label>
            <select name="use_cfdi" required class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="G03" {{ old('use_cfdi','G03')==='G03'?'selected':'' }}>G03 - Gastos en general</option>
                <option value="G01" {{ old('use_cfdi')==='G01'?'selected':'' }}>G01 - Adquisición de mercancías</option>
                <option value="I04" {{ old('use_cfdi')==='I04'?'selected':'' }}>I04 - Equipo de cómputo</option>
                <option value="I06" {{ old('use_cfdi')==='I06'?'selected':'' }}>I06 - Comunicaciones telefónicas</option>
                <option value="S01" {{ old('use_cfdi')==='S01'?'selected':'' }}>S01 - Sin efectos fiscales</option>
                <option value="CP01" {{ old('use_cfdi')==='CP01'?'selected':'' }}>CP01 - Pagos</option>
            </select>
        </div>

        <div>
            <label class="block text-sm text-gray-600 mb-1">Notas (aparece en la factura)</label>
            <textarea name="notes" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm">{{ old('notes') }}</textarea>
        </div>
    </div>

    {{-- Sección de ítems manuales (solo cuando no hay cotización) --}}
    <div class="bg-white rounded-lg shadow p-6" x-show="!quoteId">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Ítems de la Factura</h3>
            <button type="button" @click="addItem()"
                class="text-sm bg-blue-50 text-blue-700 border border-blue-200 px-3 py-1 rounded-lg hover:bg-blue-100">
                <i class="fas fa-plus mr-1"></i> Agregar ítem
            </button>
        </div>

        <div class="space-y-3">
            <template x-for="(item, idx) in items" :key="idx">
                <div class="border rounded-lg p-3 bg-gray-50 space-y-2">
                    {{-- Cargar servicio --}}
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Cargar servicio/producto</label>
                        <select @change="if($event.target.value){ let s=services.find(x=>x.id==+$event.target.value); if(s){item.description=s.name; item.unit_price=s.price;} $event.target.value=''; }"
                            class="w-full border rounded px-3 py-2 text-sm bg-white">
                            <option value="">— Seleccionar servicio (opcional) —</option>
                            <template x-for="svc in services" :key="svc.id">
                                <option :value="svc.id" x-text="svc.name + ' — $' + parseFloat(svc.price).toFixed(2)"></option>
                            </template>
                        </select>
                    </div>
                    {{-- Descripción --}}
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Descripción <span class="text-red-400">*</span></label>
                        <input type="text"
                            :name="`items[${idx}][description]`"
                            x-model="item.description"
                            :required="!quoteId"
                            placeholder="Descripción del servicio o producto"
                            class="w-full border rounded px-3 py-2 text-sm">
                    </div>
                    {{-- Cantidad / Precio --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Cantidad</label>
                            <input type="number" step="0.001" min="0.001"
                                :name="`items[${idx}][quantity]`"
                                x-model="item.quantity"
                                :required="!quoteId"
                                class="w-full border rounded px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Precio unitario (sin IVA)</label>
                            <input type="number" step="0.01" min="0"
                                :name="`items[${idx}][unit_price]`"
                                x-model="item.unit_price"
                                :required="!quoteId"
                                class="w-full border rounded px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Subtotal</label>
                            <div class="w-full border border-gray-200 bg-white rounded px-3 py-2 text-sm text-gray-700 font-mono"
                                 x-text="'$' + fmt((parseFloat(item.quantity)||0)*(parseFloat(item.unit_price)||0))"></div>
                        </div>
                        <div class="flex items-end">
                            <button type="button" @click="removeItem(idx)"
                                x-show="items.length > 1"
                                class="text-red-400 hover:text-red-600 p-2">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                    {{-- Campos SAT (colapsado/avanzado) --}}
                    <details class="text-xs text-gray-500">
                        <summary class="cursor-pointer hover:text-gray-700 select-none">Datos fiscales SAT (opcional)</summary>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
                            <div>
                                <label class="block mb-1">Clave SAT producto</label>
                                <input type="text" :name="`items[${idx}][sat_product_key]`" x-model="item.sat_product_key"
                                    class="w-full border rounded px-2 py-1 font-mono text-xs" placeholder="80101501">
                            </div>
                            <div>
                                <label class="block mb-1">Clave unidad</label>
                                <input type="text" :name="`items[${idx}][sat_unit_key]`" x-model="item.sat_unit_key"
                                    class="w-full border rounded px-2 py-1 font-mono text-xs" placeholder="E48">
                            </div>
                            <div>
                                <label class="block mb-1">Nombre unidad</label>
                                <input type="text" :name="`items[${idx}][sat_unit_name]`" x-model="item.sat_unit_name"
                                    class="w-full border rounded px-2 py-1 text-xs" placeholder="Servicio">
                            </div>
                            <div>
                                <label class="block mb-1">Objeto impuesto</label>
                                <select :name="`items[${idx}][tax_object]`" x-model="item.tax_object"
                                    class="w-full border rounded px-2 py-1 text-xs">
                                    <option value="01">01 - No objeto</option>
                                    <option value="02">02 - Sí objeto</option>
                                    <option value="03">03 - Sí objeto, no obligado</option>
                                </select>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 mt-2">
                            <input type="checkbox" :name="`items[${idx}][iva_exempt]`" x-model="item.iva_exempt" value="1">
                            <span>IVA exento (tasa 0%)</span>
                        </label>
                    </details>
                </div>
            </template>
        </div>

        {{-- Totales --}}
        <div class="mt-4 flex justify-end">
            <div class="w-64 space-y-1 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Subtotal:</span>
                    <span class="font-mono" x-text="'$' + fmt(subtotal)"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">IVA ({{ \App\Models\Setting::get('iva_percentage', 16) }}%):</span>
                    <span class="font-mono" x-text="'$' + fmt(iva)"></span>
                </div>
                <div class="flex justify-between font-semibold border-t pt-1">
                    <span>Total:</span>
                    <span class="font-mono" x-text="'$' + fmt(total)"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Crear Factura
        </button>
        <a href="{{ route('admin.invoices.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
    </div>
</form>
@endsection
