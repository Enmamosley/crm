@extends('layouts.admin')
@section('title', 'Editar Cotización')
@section('header', 'Editar Cotización: ' . $quote->quote_number)

@section('content')
<form action="{{ route('admin.quotes.update', $quote) }}" method="POST" id="quoteForm">
    @csrf @method('PUT')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Datos de la Cotización</h3>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lead</label>
                    <input type="text" value="{{ $quote->lead->name }}" disabled class="w-full border rounded-lg px-3 py-2 bg-gray-50">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <textarea name="notes" rows="3" class="w-full border rounded-lg px-3 py-2">{{ old('notes', $quote->notes) }}</textarea>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Servicios</h3>
                    <button type="button" onclick="addItem()" class="bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700 text-sm">
                        <i class="fas fa-plus mr-1"></i> Agregar
                    </button>
                </div>

                <div id="items-container">
                    @foreach($quote->items as $i => $item)
                    <div class="items-row grid grid-cols-12 gap-2 mb-3 items-end" data-index="{{ $i }}">
                        <div class="col-span-5">
                            <label class="block text-xs text-gray-500 mb-1">Servicio</label>
                            <select name="items[{{ $i }}][service_id]" required class="w-full border rounded-lg px-2 py-2 text-sm service-select" onchange="updatePrice(this)">
                                <option value="">Seleccionar...</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" data-price="{{ $service->price }}" {{ $item->service_id == $service->id ? 'selected' : '' }}>
                                        [{{ $service->category->name ?? '' }}] {{ $service->name }} - ${{ number_format($service->price, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Cantidad</label>
                            <input type="number" name="items[{{ $i }}][quantity]" value="{{ $item->quantity }}" min="1" required class="w-full border rounded-lg px-2 py-2 text-sm qty-input" onchange="calculateRow(this)">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Precio Unit.</label>
                            <input type="number" name="items[{{ $i }}][unit_price]" value="{{ $item->unit_price }}" step="0.01" min="0" required class="w-full border rounded-lg px-2 py-2 text-sm price-input" onchange="calculateRow(this)">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Total</label>
                            <input type="text" readonly class="w-full border rounded-lg px-2 py-2 text-sm bg-gray-50 row-total" value="${{ number_format($item->total, 2) }}">
                        </div>
                        <div class="col-span-1">
                            <button type="button" onclick="removeItem(this)" class="text-red-500 hover:text-red-700 p-2"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div>
            <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                <h3 class="text-lg font-semibold mb-4">Resumen</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Subtotal:</span>
                        <span id="subtotal" class="font-medium">${{ number_format($quote->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">IVA ({{ $quote->iva_percentage }}%):</span>
                        <span id="iva" class="font-medium">${{ number_format($quote->iva_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-3 text-lg">
                        <span class="font-semibold">Total:</span>
                        <span id="total" class="font-bold text-green-600">${{ number_format($quote->total, 2) }}</span>
                    </div>
                </div>
                <div class="mt-6 space-y-3">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-1"></i> Actualizar Cotización
                    </button>
                    <a href="{{ route('admin.quotes.show', $quote) }}" class="block text-center w-full bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    const ivaPercentage = {{ $quote->iva_percentage }};
    let itemIndex = {{ count($quote->items) }};

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
        row.querySelector('.row-total').value = '$' + (qty * price).toFixed(2);
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
        document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
        document.getElementById('iva').textContent = '$' + iva.toFixed(2);
        document.getElementById('total').textContent = '$' + (subtotal + iva).toFixed(2);
    }
</script>
@endpush
@endsection
