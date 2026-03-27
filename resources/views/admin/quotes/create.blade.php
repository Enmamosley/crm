@extends('layouts.admin')
@section('title', 'Nueva Cotización')
@section('header', 'Crear Cotización')

@section('content')
<form action="{{ route('admin.quotes.store') }}" method="POST" id="quoteForm">
    @csrf
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Formulario principal -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Datos de la Cotización</h3>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lead *</label>
                    <select name="lead_id" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Seleccionar lead...</option>
                        @foreach($leads as $lead)
                            <option value="{{ $lead->id }}" {{ ($selectedLead && $selectedLead->id == $lead->id) ? 'selected' : '' }}>
                                {{ $lead->name }} - {{ $lead->business ?? $lead->email ?? 'Sin datos' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <textarea name="notes" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Notas adicionales...">{{ old('notes') }}</textarea>
                </div>
            </div>

            <!-- Items -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Servicios</h3>
                    <div class="flex gap-2">
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open"
                                    class="bg-indigo-600 text-white px-3 py-1 rounded-lg hover:bg-indigo-700 text-sm inline-flex items-center gap-1">
                                <i class="fas fa-box mr-1"></i> Agregar Paquete
                                <i class="fas fa-chevron-down text-xs"></i>
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak
                                 class="absolute right-0 mt-1 w-64 bg-white border rounded-lg shadow-lg z-10 max-h-64 overflow-y-auto">
                                @if($bundles->isEmpty())
                                <p class="px-4 py-3 text-sm text-gray-400">No hay paquetes activos.</p>
                                @else
                                @foreach($bundles as $bundle)
                                <button type="button"
                                        @click="open = false"
                                        onclick="addBundle({{ $bundle->id }})"
                                        class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 border-b last:border-0">
                                    <span class="font-medium text-gray-800">{{ $bundle->name }}</span>
                                    <span class="block text-xs text-gray-400">{{ $bundle->items->count() }} servicios</span>
                                </button>
                                @endforeach
                                @endif
                            </div>
                        </div>
                        <button type="button" onclick="addItem()" class="bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700 text-sm">
                            <i class="fas fa-plus mr-1"></i> Agregar Servicio
                        </button>
                    </div>
                </div>

                <div id="items-container">
                    <div class="items-row grid grid-cols-12 gap-2 mb-3 items-end" data-index="0">
                        <div class="col-span-5">
                            <label class="block text-xs text-gray-500 mb-1">Servicio</label>
                            <select name="items[0][service_id]" required class="w-full border rounded-lg px-2 py-2 text-sm service-select" onchange="updatePrice(this)">
                                <option value="">Seleccionar...</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" data-price="{{ $service->price }}">
                                        [{{ $service->category->name ?? '' }}] {{ $service->name }} - ${{ number_format($service->price, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Cantidad</label>
                            <input type="number" name="items[0][quantity]" value="1" min="1" required class="w-full border rounded-lg px-2 py-2 text-sm qty-input" onchange="calculateRow(this)">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Precio Unit.</label>
                            <input type="number" name="items[0][unit_price]" step="0.01" min="0" required class="w-full border rounded-lg px-2 py-2 text-sm price-input" onchange="calculateRow(this)">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Total</label>
                            <input type="text" readonly class="w-full border rounded-lg px-2 py-2 text-sm bg-gray-50 row-total" value="$0.00">
                        </div>
                        <div class="col-span-1">
                            <button type="button" onclick="removeItem(this)" class="text-red-500 hover:text-red-700 p-2"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen -->
        <div>
            <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                <h3 class="text-lg font-semibold mb-4">Resumen</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Subtotal:</span>
                        <span id="subtotal" class="font-medium">$0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">IVA ({{ $ivaPercentage }}%):</span>
                        <span id="iva" class="font-medium">$0.00</span>
                    </div>
                    <div class="flex justify-between border-t pt-3 text-lg">
                        <span class="font-semibold">Total:</span>
                        <span id="total" class="font-bold text-green-600">$0.00</span>
                    </div>
                </div>

                <div class="mt-6 space-y-3">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-1"></i> Generar Cotización
                    </button>
                    <a href="{{ route('admin.quotes.index') }}" class="block text-center w-full bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    const ivaPercentage = {{ $ivaPercentage }};
    let itemIndex = 1;
    const bundlesData = @json($bundles->map(fn($b) => [
        'id' => $b->id,
        'services' => $b->services->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'price' => (float) $s->price,
            'quantity' => $s->pivot->quantity,
        ])
    ])->keyBy('id'));

    function addBundle(bundleId) {
        const bundle = bundlesData[bundleId];
        if (!bundle) return;
        bundle.services.forEach(service => {
            addItemWithValues(service.id, service.quantity, service.price);
        });
    }

    function addItemWithValues(serviceId, qty, price) {
        const container = document.getElementById('items-container');
        const firstRow = container.querySelector('.items-row');
        const newRow = firstRow.cloneNode(true);
        newRow.dataset.index = itemIndex;
        newRow.querySelectorAll('select, input').forEach(el => {
            el.name = el.name.replace(/\[\d+\]/, `[${itemIndex}]`);
        });
        const sel = newRow.querySelector('.service-select');
        sel.value = serviceId;
        newRow.querySelector('.qty-input').value = qty;
        newRow.querySelector('.price-input').value = price.toFixed(2);
        newRow.querySelector('.row-total').value = '$' + (qty * price).toFixed(2);
        container.appendChild(newRow);
        itemIndex++;
        calculateTotals();
    }

    function addItem() {
        const container = document.getElementById('items-container');
        const firstRow = container.querySelector('.items-row');
        const newRow = firstRow.cloneNode(true);

        newRow.dataset.index = itemIndex;
        newRow.querySelectorAll('select, input').forEach(el => {
            el.name = el.name.replace(/\[\d+\]/, `[${itemIndex}]`);
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else if (el.classList.contains('qty-input')) el.value = 1;
            else if (el.classList.contains('price-input')) el.value = '';
            else if (el.classList.contains('row-total')) el.value = '$0.00';
        });

        container.appendChild(newRow);
        itemIndex++;
    }

    function removeItem(btn) {
        const container = document.getElementById('items-container');
        if (container.children.length > 1) {
            btn.closest('.items-row').remove();
            calculateTotals();
        }
    }

    function updatePrice(select) {
        const row = select.closest('.items-row');
        const option = select.options[select.selectedIndex];
        const price = option.dataset.price || 0;
        row.querySelector('.price-input').value = parseFloat(price).toFixed(2);
        calculateRow(select);
    }

    function calculateRow(el) {
        const row = el.closest('.items-row');
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const total = qty * price;
        row.querySelector('.row-total').value = '$' + total.toFixed(2);
        calculateTotals();
    }

    function calculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.items-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            subtotal += qty * price;
        });

        const iva = subtotal * (ivaPercentage / 100);
        const total = subtotal + iva;

        document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
        document.getElementById('iva').textContent = '$' + iva.toFixed(2);
        document.getElementById('total').textContent = '$' + total.toFixed(2);
    }
</script>
@endpush
@endsection
